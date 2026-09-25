/**
 * AAC Playback Fix — Runtime decoder for raw ADTS-AAC audio files.
 *
 * Problem: Raw .aac (ADTS) files don't play in Firefox, many Chrome/Android
 * builds, and some Safari versions. Only AAC-inside-MP4 (m4a) is universal.
 *
 * Solution: For every <audio> whose src ends in .aac or .acc, this script:
 *   1. Fetches the raw bytes
 *   2. Decodes via AudioContext.decodeAudioData() (browser's own codec)
 *   3. Re-encodes as 16-bit PCM WAV
 *   4. Replaces the src with a blob: URL that plays everywhere
 *
 * Also intercepts dynamic audio.src assignments and audio.play() calls so
 * that code like `audio.src='file.aac'; audio.play()` works seamlessly — the
 * play is deferred until transcoding completes.
 *
 * Falls back gracefully — if decoding fails, the element is left as-is.
 *
 * Include on any page that plays audio. Self-initialises; handles dynamically
 * added <audio> elements via MutationObserver.
 */
(function () {
    'use strict';

    // ── WAV encoder ─────────────────────────────────────────────────────
    function encodeWav(audioBuffer) {
        var numCh = Math.min(audioBuffer.numberOfChannels || 1, 2);
        var sampleRate = audioBuffer.sampleRate;
        var length = audioBuffer.length;
        var chans = [];
        for (var c = 0; c < numCh; c++) chans.push(audioBuffer.getChannelData(c));
        var blockAlign = numCh * 2;
        var dataSize = length * blockAlign;
        var view = new DataView(new ArrayBuffer(44 + dataSize));
        var p = 0;
        function w(s) { for (var i = 0; i < s.length; i++) view.setUint8(p++, s.charCodeAt(i)); }
        function u32(v) { view.setUint32(p, v, true); p += 4; }
        function u16(v) { view.setUint16(p, v, true); p += 2; }
        w('RIFF'); u32(36 + dataSize); w('WAVE'); w('fmt '); u32(16); u16(1); u16(numCh);
        u32(sampleRate); u32(sampleRate * blockAlign); u16(blockAlign); u16(16); w('data'); u32(dataSize);
        for (var i = 0; i < length; i++) {
            for (var ch = 0; ch < numCh; ch++) {
                var s = Math.max(-1, Math.min(1, chans[ch][i]));
                view.setInt16(p, s < 0 ? s * 0x8000 : s * 0x7FFF, true); p += 2;
            }
        }
        return new Blob([view], { type: 'audio/wav' });
    }

    // ── Check if a URL points to a raw AAC file ─────────────────────────
    function isAacUrl(src) {
        if (!src) return false;
        var clean = src.split('?')[0].split('#')[0].toLowerCase();
        return clean.endsWith('.aac') || clean.endsWith('.acc');
    }

    // Track elements already being processed
    var processed = new WeakSet();

    // Map of audio elements to their transcode promises (for play() deferral)
    var transcodePromises = new WeakMap();

    // Cache: originalUrl → blobUrl (so re-loading same .aac is instant)
    var blobCache = {};

    // ── Core fix: convert an <audio> element's AAC src to WAV blob ──────
    function fixAudioElement(el) {
        if (!el || !el.src || processed.has(el)) return;
        if (!isAacUrl(el.src)) return;
        processed.add(el);

        var originalSrc = el.src;

        // Check cache first — if we already transcoded this URL, use the blob
        if (blobCache[originalSrc]) {
            applyWav(el, null, originalSrc, blobCache[originalSrc]);
            return;
        }

        // Create and store the transcode promise so play() can wait on it
        var transcodePromise = doTranscode(el, originalSrc);
        transcodePromises.set(el, transcodePromise);
    }

    function doTranscode(el, originalSrc) {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return Promise.resolve(false);

        return fetch(originalSrc)
            .then(function (resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.arrayBuffer();
            })
            .then(function (buf) {
                return new Promise(function (resolve) {
                    var ctx;
                    try { ctx = new AC(); } catch (e) { return resolve(false); }

                    function onDecode(audio) {
                        try {
                            var wav = encodeWav(audio);
                            var blobUrl = URL.createObjectURL(wav);
                            blobCache[originalSrc] = blobUrl;
                            applyWav(el, audio, originalSrc, blobUrl);
                            resolve(true);
                        } catch (e) {
                            resolve(false);
                        }
                        try { ctx.close(); } catch (e) {}
                    }
                    function onError() {
                        resolve(false);
                        try { ctx.close(); } catch (e) {}
                    }

                    var decode = ctx.decodeAudioData(buf);
                    if (decode && typeof decode.then === 'function') {
                        decode.then(onDecode).catch(onError);
                    } else {
                        ctx.decodeAudioData(buf, onDecode, onError);
                    }
                });
            })
            .catch(function () {
                return false;
            });
    }

    function applyWav(audioEl, decodedAudio, origSrc, blobUrl) {
        try {
            var wasPlaying = !audioEl.paused;
            var currentTime = audioEl.currentTime || 0;
            audioEl.dataset.aacFixing = '1'; // guard against src interceptor re-entry
            audioEl.src = blobUrl;
            delete audioEl.dataset.aacFixing;
            audioEl.dataset.aacFixed = '1';
            audioEl.dataset.originalAacSrc = origSrc;
            audioEl.load();

            // Auto-play if the element was playing or had a deferred play()
            if (wasPlaying || audioEl._aacPendingPlay) {
                delete audioEl._aacPendingPlay;
                audioEl.addEventListener('canplay', function onCanPlay() {
                    audioEl.removeEventListener('canplay', onCanPlay);
                    try { audioEl.currentTime = currentTime; } catch (e) {}
                    audioEl.play().catch(function () {});
                });
            }

            // Clean up the transcode promise
            transcodePromises.delete(audioEl);
        } catch (e) {
            // Keep original src
            transcodePromises.delete(audioEl);
        }
    }

    // ── Intercept audio.src setter for dynamic track changes ────────────
    var origSrcDescriptor = Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype, 'src');
    if (origSrcDescriptor && origSrcDescriptor.set) {
        Object.defineProperty(HTMLMediaElement.prototype, 'src', {
            get: origSrcDescriptor.get,
            set: function (val) {
                origSrcDescriptor.set.call(this, val);
                if (this.tagName === 'AUDIO' && isAacUrl(val) && !this.dataset.aacFixing) {
                    processed.delete(this);
                    transcodePromises.delete(this);
                    delete this._aacPendingPlay;
                    fixAudioElement(this);
                }
            },
            enumerable: true,
            configurable: true
        });
    }

    // ── Intercept audio.play() to defer if transcode is in progress ─────
    var origPlay = HTMLMediaElement.prototype.play;
    HTMLMediaElement.prototype.play = function () {
        var self = this;

        // Only intercept for audio elements with an active transcode
        if (self.tagName === 'AUDIO' && transcodePromises.has(self)) {
            self._aacPendingPlay = true;
            // Return a promise that resolves after transcode + play
            return transcodePromises.get(self).then(function (fixed) {
                if (fixed) {
                    // applyWav will handle the play via _aacPendingPlay flag
                    // If applyWav already ran, the element is now playable
                    if (self.dataset.aacFixed) {
                        return origPlay.call(self);
                    }
                    // Otherwise applyWav will handle it when it completes
                    return Promise.resolve();
                }
                // Transcode failed — try native play anyway
                return origPlay.call(self);
            }).catch(function () {
                return origPlay.call(self);
            });
        }

        // Also handle: src was just set to .aac but fixAudioElement hasn't
        // been called yet (e.g. setAttribute('src', ...) bypassing our setter)
        if (self.tagName === 'AUDIO' && isAacUrl(self.src) && !self.dataset.aacFixed && !self.dataset.aacFixing) {
            self._aacPendingPlay = true;
            processed.delete(self);
            fixAudioElement(self);
            var p = transcodePromises.get(self);
            if (p) {
                return p.then(function (fixed) {
                    if (fixed && self.dataset.aacFixed) {
                        return origPlay.call(self);
                    }
                    return origPlay.call(self);
                }).catch(function () {
                    return origPlay.call(self);
                });
            }
        }

        return origPlay.call(self);
    };

    // ── Process all existing <audio> elements ───────────────────────────
    function processAll() {
        var audios = document.querySelectorAll('audio');
        for (var i = 0; i < audios.length; i++) {
            fixAudioElement(audios[i]);
        }
    }

    // ── MutationObserver for dynamically added <audio> elements ─────────
    function startObserver() {
        if (typeof MutationObserver === 'undefined') return;
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.tagName === 'AUDIO') {
                        fixAudioElement(node);
                    } else if (node.querySelectorAll) {
                        var nested = node.querySelectorAll('audio');
                        for (var k = 0; k < nested.length; k++) {
                            fixAudioElement(nested[k]);
                        }
                    }
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    // ── Init ────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            processAll();
            startObserver();
        });
    } else {
        processAll();
        startObserver();
    }
})();
