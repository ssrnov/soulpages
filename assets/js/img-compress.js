/**
 * Client-side image compressor — shrinks photos in the browser BEFORE upload.
 * A 10MB phone photo becomes ~300-500KB, so uploads are ~20x faster and the
 * server never has to decode a 48-megapixel image (which is what was hitting
 * shared-hosting CPU/memory limits and making "Generating your page…" hang).
 *
 * Works on any <input type="file"> that accepts images. GIFs and already-small
 * files pass through untouched. On any decode error the original file is kept.
 */
(function () {
  var MAX_DIM = 1600;            // longest edge after resize
  var SKIP_BELOW = 500 * 1024;   // files already smaller than this pass through
  var QUALITY = 0.82;

  function compressOne(file) {
    return new Promise(function (resolve) {
      if (!file.type || file.type.indexOf('image/') !== 0 || file.type === 'image/gif' || file.size <= SKIP_BELOW) {
        return resolve(file);
      }
      var done = function (out) { resolve(out && out.size < file.size ? out : file); };
      var fail = function () { resolve(file); };
      try {
        var opts = { imageOrientation: 'from-image' };
        var p = window.createImageBitmap ? createImageBitmap(file, opts).catch(function () { return createImageBitmap(file); }) : Promise.reject();
        p.then(function (bmp) {
          var scale = Math.min(1, MAX_DIM / Math.max(bmp.width, bmp.height));
          var w = Math.max(1, Math.round(bmp.width * scale));
          var h = Math.max(1, Math.round(bmp.height * scale));
          var cv = document.createElement('canvas');
          cv.width = w; cv.height = h;
          cv.getContext('2d').drawImage(bmp, 0, 0, w, h);
          if (bmp.close) bmp.close();
          cv.toBlob(function (blob) {
            if (!blob) return fail();
            var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
            try { done(new File([blob], name, { type: 'image/jpeg' })); } catch (e) { fail(); }
          }, 'image/jpeg', QUALITY);
        }).catch(fail);
      } catch (e) { fail(); }
    });
  }

  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp || inp.tagName !== 'INPUT' || inp.type !== 'file' || !inp.files || !inp.files.length) return;
    if ((inp.accept || '').indexOf('image') === -1) return;
    if (typeof DataTransfer === 'undefined' || typeof File === 'undefined') return;
    var files = Array.prototype.slice.call(inp.files);
    inp.dataset.compressing = '1';
    Promise.all(files.map(compressOne)).then(function (out) {
      try {
        var dt = new DataTransfer();
        var changed = false;
        for (var i = 0; i < out.length; i++) {
          dt.items.add(out[i]);
          if (out[i] !== files[i]) changed = true;
        }
        if (changed) inp.files = dt.files;
      } catch (err) { /* keep originals */ }
      delete inp.dataset.compressing;
    });
  });

  // Monkey-patch programmatic HTMLFormElement.prototype.submit as well
  var originalSubmit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () {
    var form = this;
    if (form.querySelector('input[type=file][data-compressing]')) {
      var tries = 0;
      var iv = setInterval(function () {
        if (!form.querySelector('input[type=file][data-compressing]') || ++tries > 100) {
          clearInterval(iv);
          originalSubmit.call(form);
        }
      }, 100);
    } else {
      originalSubmit.call(form);
    }
  };

  // If the user hits submit while photos are still compressing, wait briefly.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.querySelector('input[type=file][data-compressing]')) return;
    e.preventDefault();
    var tries = 0;
    var iv = setInterval(function () {
      if (!form.querySelector('input[type=file][data-compressing]') || ++tries > 100) {
        clearInterval(iv);
        originalSubmit.call(form);
      }
    }, 100);
  }, true);
})();

