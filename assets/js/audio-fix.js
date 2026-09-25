/**
 * Client-side audio normaliser — converts raw AAC (.aac / .acc) to MP3 in the
 * browser BEFORE upload. Raw ADTS-AAC doesn't play in `<audio>` on Firefox and
 * several Android browsers, and shared hosts have no ffmpeg to transcode.
 *
 * v2: encodes to MP3 via lamejs (mono, 112kbps ≈ 0.8MB/min) instead of WAV —
 * WAV output was ~11MB/min and blew past the 10MB voice-upload limit, so the
 * upload was rejected and pages ended up with no audio at all.
 *
 * Fallback chain: lamejs MP3 → downsampled mono WAV (only if it fits 9MB) →
 * original file. Only .aac/.acc are touched. Sets `data-compressing` so the
 * shared submit-guard in img-compress.js waits for conversion to finish.
 */
(function () {
  var LAME_URLS = [
    'https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js',
    'https://unpkg.com/lamejs@1.2.1/lame.min.js'
  ];
  var lamePromise = null;

  function loadLame() {
    if (window.lamejs) return Promise.resolve(true);
    if (lamePromise) return lamePromise;
    lamePromise = new Promise(function (resolve) {
      var i = 0;
      (function tryNext() {
        if (window.lamejs) return resolve(true);
        if (i >= LAME_URLS.length) return resolve(false);
        var s = document.createElement('script');
        s.src = LAME_URLS[i++];
        s.onload = function () { resolve(!!window.lamejs); };
        s.onerror = tryNext;
        document.head.appendChild(s);
      })();
    });
    return lamePromise;
  }

  function needsFix(file) {
    var ext = (file.name || '').split('.').pop().toLowerCase();
    return ext === 'aac' || ext === 'acc' || file.type === 'audio/aac' || file.type === 'audio/x-aac' || file.type === 'audio/x-acc';
  }

  // Repair an ADTS stream before decoding: flip the MPEG-2 ID bit to MPEG-4
  // in every frame header AND drop degenerate tiny frames (<20 bytes) that
  // poison decoders (some recorder apps emit 9-byte "empty" frames).
  function patchAdts(buf) {
    var b = new Uint8Array(buf.slice(0));
    if (b.length < 7 || b[0] !== 0xFF || (b[1] & 0xF0) !== 0xF0) return buf;
    var keep = [];
    var pos = 0;
    while (pos + 7 <= b.length) {
      if (b[pos] !== 0xFF || (b[pos + 1] & 0xF0) !== 0xF0) break;
      if (b[pos + 1] & 0x08) b[pos + 1] &= 0xF7;
      var frameLen = ((b[pos + 3] & 0x03) << 11) | (b[pos + 4] << 3) | (b[pos + 5] >> 5);
      if (frameLen < 7) break;
      if (frameLen >= 20) keep.push(b.subarray(pos, pos + frameLen));
      pos += frameLen;
    }
    if (!keep.length) return b.buffer;
    var total = 0;
    for (var i = 0; i < keep.length; i++) total += keep[i].length;
    var clean = new Uint8Array(total);
    var o = 0;
    for (var j = 0; j < keep.length; j++) { clean.set(keep[j], o); o += keep[j].length; }
    return clean.buffer;
  }

  // mix to mono Float32 + convert to Int16
  function toMonoInt16(audioBuffer) {
    var len = audioBuffer.length;
    var chs = audioBuffer.numberOfChannels || 1;
    var out = new Int16Array(len);
    var c0 = audioBuffer.getChannelData(0);
    var c1 = chs > 1 ? audioBuffer.getChannelData(1) : null;
    for (var i = 0; i < len; i++) {
      var s = c1 ? (c0[i] + c1[i]) / 2 : c0[i];
      s = Math.max(-1, Math.min(1, s));
      out[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    return out;
  }

  function encodeMp3(audioBuffer) {
    var samples = toMonoInt16(audioBuffer);
    var enc = new lamejs.Mp3Encoder(1, audioBuffer.sampleRate, 112);
    var chunks = [];
    var BLOCK = 1152 * 20;
    for (var i = 0; i < samples.length; i += BLOCK) {
      var mp3buf = enc.encodeBuffer(samples.subarray(i, i + BLOCK));
      if (mp3buf.length) chunks.push(mp3buf);
    }
    var end = enc.flush();
    if (end.length) chunks.push(end);
    return new Blob(chunks, { type: 'audio/mpeg' });
  }

  function encodeWavMono(audioBuffer) {
    var samples = toMonoInt16(audioBuffer);
    var sampleRate = audioBuffer.sampleRate;
    var dataSize = samples.length * 2;
    var view = new DataView(new ArrayBuffer(44 + dataSize));
    var p = 0;
    function w(s) { for (var i = 0; i < s.length; i++) view.setUint8(p++, s.charCodeAt(i)); }
    function u32(v) { view.setUint32(p, v, true); p += 4; }
    function u16(v) { view.setUint16(p, v, true); p += 2; }
    w('RIFF'); u32(36 + dataSize); w('WAVE'); w('fmt '); u32(16); u16(1); u16(1);
    u32(sampleRate); u32(sampleRate * 2); u16(2); u16(16); w('data'); u32(dataSize);
    for (var i = 0; i < samples.length; i++) { view.setInt16(p, samples[i], true); p += 2; }
    return new Blob([view], { type: 'audio/wav' });
  }

  function convertOne(file) {
    return new Promise(function (resolve) {
      if (!needsFix(file)) return resolve(file);
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC || !file.arrayBuffer) return resolve(file);
      var ctx;
      try { ctx = new AC(); } catch (e) { return resolve(file); }
      var done = function (out) { try { ctx.close(); } catch (e) {} resolve(out); };
      var failed = function () {
        // Can't convert this AAC in this browser — warn instead of silently
        // publishing audio that won't play for the recipient.
        try {
          alert('⚠️ This .aac file could not be converted for web playback.\nPlease upload the audio as MP3 (or record again) so it plays on every device.');
        } catch (e) {}
        done(null);
      };
      file.arrayBuffer().then(function (buf) {
        var onAudio = function (audio) {
          loadLame().then(function (hasLame) {
            try {
              var base = (file.name || 'audio').replace(/\.[^.]+$/, '');
              if (hasLame) {
                var mp3 = encodeMp3(audio);
                if (mp3 && mp3.size > 0) return done(new File([mp3], base + '.mp3', { type: 'audio/mpeg' }));
              }
              var wav = encodeWavMono(audio);
              if (wav.size <= 9 * 1024 * 1024) return done(new File([wav], base + '.wav', { type: 'audio/wav' }));
              failed(); // too big even as mono WAV
            } catch (e) { failed(); }
          });
        };
        // decode the MPEG-4-patched bytes first; fall back to the raw bytes
        ctx.decodeAudioData(patchAdts(buf), onAudio, function () {
          var c2;
          try { c2 = new AC(); } catch (e) { return failed(); }
          c2.decodeAudioData(buf.slice(0), function (audio) { try { c2.close(); } catch (e) {} onAudio(audio); },
            function () { try { c2.close(); } catch (e) {} failed(); });
        });
      }).catch(function () { failed(); });
    });
  }

  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp || inp.tagName !== 'INPUT' || inp.type !== 'file' || !inp.files || !inp.files.length) return;
    if ((inp.accept || '').indexOf('audio') === -1) return;
    if (typeof DataTransfer === 'undefined' || typeof File === 'undefined') return;
    var files = Array.prototype.slice.call(inp.files);
    if (!files.some(needsFix)) return;
    loadLame(); // start fetching the encoder immediately
    inp.dataset.compressing = '1';
    Promise.all(files.map(convertOne)).then(function (out) {
      try {
        var dt = new DataTransfer();
        var changed = false;
        for (var i = 0; i < out.length; i++) {
          if (out[i] === null) { changed = true; continue; } // unconvertible — drop it
          dt.items.add(out[i]);
          if (out[i] !== files[i]) changed = true;
        }
        if (changed) inp.files = dt.files;
      } catch (err) { /* keep originals */ }
      delete inp.dataset.compressing;
    });
  });
})();
