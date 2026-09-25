/**
 * Playback fallback for already-uploaded raw AAC files (.aac / .acc).
 *
 * Two problems with stored .aac files:
 *  1. Many are MPEG-2 ADTS (syncword FFF9) — browsers only accept MPEG-4 AAC
 *     (FFF1) even when canPlayType('audio/aac') says "probably". The audio
 *     data is identical AAC-LC; only the ID bit in each frame header differs,
 *     so we flip that bit in every ADTS frame → instantly playable.
 *  2. Some browsers refuse raw ADTS entirely → decode with WebAudio and swap
 *     the element's src to an in-memory mono 22.05kHz WAV.
 *
 * Existing player logic (play/pause/timeupdate/ended) keeps working because
 * only el.src changes. On total failure the original src is left alone.
 */
(function () {
  function isAac(src) { return /\.(aac|acc)(\?.*)?$/i.test(src || ''); }

  // Repair an ADTS stream: flip the MPEG-2 ID bit to MPEG-4 in every frame
  // header AND drop degenerate tiny frames (<20 bytes) that poison decoders —
  // some recorder apps emit 9-byte "empty" frames at the start of the file.
  // Returns a rebuilt ArrayBuffer, or null if the data isn't ADTS or nothing
  // needed changing.
  function patchAdts(buf) {
    var b = new Uint8Array(buf.slice(0));
    if (b.length < 7 || b[0] !== 0xFF || (b[1] & 0xF0) !== 0xF0) return null;
    var changed = false;
    var keep = [];
    var pos = 0;
    while (pos + 7 <= b.length) {
      if (b[pos] !== 0xFF || (b[pos + 1] & 0xF0) !== 0xF0) break;
      if (b[pos + 1] & 0x08) { b[pos + 1] &= 0xF7; changed = true; } // MPEG-2 → MPEG-4
      var frameLen = ((b[pos + 3] & 0x03) << 11) | (b[pos + 4] << 3) | (b[pos + 5] >> 5);
      if (frameLen < 7) break;
      if (frameLen >= 20) keep.push(b.subarray(pos, pos + frameLen));
      else changed = true; // dropping a junk frame
      pos += frameLen;
    }
    if (!changed || !keep.length) return null;
    var total = 0;
    for (var i = 0; i < keep.length; i++) total += keep[i].length;
    var clean = new Uint8Array(total);
    var o = 0;
    for (var j = 0; j < keep.length; j++) { clean.set(keep[j], o); o += keep[j].length; }
    return clean.buffer;
  }

  // Encode an AudioBuffer as mono 16-bit WAV (channels mixed down manually).
  function encodeWav(audioBuffer) {
    var len = audioBuffer.length;
    var sampleRate = audioBuffer.sampleRate;
    var chs = audioBuffer.numberOfChannels || 1;
    var c0 = audioBuffer.getChannelData(0);
    var c1 = chs > 1 ? audioBuffer.getChannelData(1) : null;
    var dataSize = len * 2;
    var view = new DataView(new ArrayBuffer(44 + dataSize));
    var p = 0;
    function w(s) { for (var i = 0; i < s.length; i++) view.setUint8(p++, s.charCodeAt(i)); }
    function u32(v) { view.setUint32(p, v, true); p += 4; }
    function u16(v) { view.setUint16(p, v, true); p += 2; }
    w('RIFF'); u32(36 + dataSize); w('WAVE'); w('fmt '); u32(16); u16(1); u16(1);
    u32(sampleRate); u32(sampleRate * 2); u16(2); u16(16); w('data'); u32(dataSize);
    for (var i = 0; i < len; i++) {
      var s = c1 ? (c0[i] + c1[i]) / 2 : c0[i];
      s = Math.max(-1, Math.min(1, s));
      view.setInt16(p, s < 0 ? s * 0x8000 : s * 0x7FFF, true); p += 2;
    }
    return new Blob([view], { type: 'audio/wav' });
  }

  function swapSrc(el, blob) {
    var wasPlaying = !el.paused;
    el.src = URL.createObjectURL(blob);
    el.load();
    if (wasPlaying) el.play().catch(function () {});
  }

  // Try a blob as the element source; resolves true if metadata loads.
  function trySrc(el, blob) {
    return new Promise(function (resolve) {
      var probe = document.createElement('audio');
      var url = URL.createObjectURL(blob);
      var done = function (ok) { probe.src = ''; URL.revokeObjectURL(ok ? '' : url); resolve(ok && url); };
      probe.onloadedmetadata = function () { resolve(url); };
      probe.onerror = function () { URL.revokeObjectURL(url); resolve(false); };
      setTimeout(function () { resolve(false); }, 4000);
      probe.preload = 'metadata';
      probe.src = url;
    });
  }

  function decodeToWav(buf) {
    // OfflineAudioContext is exempt from autoplay policy (a regular
    // AudioContext created without a user gesture can hang decodeAudioData).
    if (!window.OfflineAudioContext) return Promise.reject(new Error('no OfflineAudioContext'));
    var off = new OfflineAudioContext(1, 1, 22050);
    return new Promise(function (resolve, reject) {
      var settled = false;
      var ok = function (a) { if (!settled) { settled = true; resolve(a); } };
      var bad = function (e) { if (!settled) { settled = true; reject(e || new Error('decode failed')); } };
      try {
        var ret = off.decodeAudioData(buf, ok, bad);
        if (ret && ret.then) ret.then(ok, bad);
      } catch (e) { bad(e); }
      setTimeout(function () { bad(new Error('decode timeout')); }, 30000);
    }).then(encodeWav);
  }

  var pending = {}; // src → in-flight repair promise (players can await it)

  function fixOne(el) {
    var src = el.currentSrc || el.src || el.getAttribute('src');
    if (!isAac(src) || !window.fetch) return Promise.resolve(false);
    if (el.dataset.aacFixed === src) return pending[src] || Promise.resolve(true);
    el.dataset.aacFixed = src;
    var p = fetch(src).then(function (r) { return r.arrayBuffer(); }).then(function (buf) {
      var patched = patchAdts(buf);
      if (patched) {
        // Repaired stream: WebAudio decode → WAV is the most reliable path
        // (native <audio> can report metadata for streams it then can't play).
        return decodeToWav(patched.slice(0)).then(function (wav) { swapSrc(el, wav); return true; })
          .catch(function () {
            // decoder unavailable → at least hand the repaired AAC to native
            return trySrc(el, new Blob([patched], { type: 'audio/aac' })).then(function (url) {
              if (url) { var wasPlaying = !el.paused; el.src = url; el.load(); if (wasPlaying) el.play().catch(function () {}); return true; }
              return false;
            });
          });
      }
      // Already MPEG-4 (or not ADTS): only intervene if this browser can't play it
      var probe = document.createElement('audio');
      if (probe.canPlayType('audio/aac')) return true; // native should handle it
      return decodeToWav(buf.slice(0)).then(function (wav) { swapSrc(el, wav); return true; });
    }).catch(function () { return false; });
    // players await this — make sure it always settles even if something hangs
    var guarded = Promise.race([p, new Promise(function (res) { setTimeout(function () { res(false); }, 45000); })]);
    pending[src] = guarded;
    return guarded;
  }

  // Players (p.php) call this before falling back to their error UI:
  // it resolves true once the element's src has been repaired.
  window.aacRepair = fixOne;

  function run() {
    var els = document.querySelectorAll('audio');
    for (var i = 0; i < els.length; i++) fixOne(els[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();

  // Normal pages create some players dynamically (new Audio / src set later),
  // so keep watching for added audio elements and src changes.
  if (window.MutationObserver) {
    new MutationObserver(function () { run(); }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['src'] });
  }
  setInterval(run, 3000); // safety net for src changes the observer misses
  // Repair on playback error too (e.g. a player revealed long after load)
  document.addEventListener('error', function (e) {
    if (e.target && e.target.tagName === 'AUDIO') fixOne(e.target);
  }, true);
})();
