/* --- admin.js --- */

(function () {
  'use strict';

  var cfg = window.beryonAdmin || {};
  var strings = cfg.strings || {};

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  

  function initValidationFocus() {
    var invalid = document.querySelector('.beryon-field.is-invalid, .form-field.error, .beryon-repeater-row input:invalid');
    if (invalid && typeof invalid.scrollIntoView === 'function') {
      invalid.scrollIntoView({ block: 'center' });
      invalid.focus({ preventScroll: true });
    }
  }

  

  function reindex(repeater) {
    var rows = repeater.querySelectorAll('[data-row]');
    rows.forEach(function (row, index) {
      row.querySelectorAll('[name]').forEach(function (field) {
        field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
      });
      var number = row.querySelector('.beryon-row-number');
      if (number) {
        number.textContent = String(index + 1);
      }
    });

    var add = repeater.querySelector('[data-repeater-add]');
    var max = parseInt(repeater.getAttribute('data-max') || '0', 10);
    if (add) {
      add.disabled = max > 0 && rows.length >= max;
    }
  }

  function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
      var rowsWrap = repeater.querySelector('[data-rows]');
      var template = repeater.querySelector('[data-row-template]');
      var add = repeater.querySelector('[data-repeater-add]');

      if (!rowsWrap || !template) {
        return;
      }

      if (add) {
        add.addEventListener('click', function () {
          var max = parseInt(repeater.getAttribute('data-max') || '0', 10);
          var count = rowsWrap.querySelectorAll('[data-row]').length;
          if (max > 0 && count >= max) {
            return;
          }

          var html = template.innerHTML.replace(/__INDEX__/g, String(count));
          var holder = document.createElement('div');
          holder.innerHTML = html.trim();
          var row = holder.firstElementChild;
          if (row) {
            rowsWrap.appendChild(row);
            reindex(repeater);
            var first = row.querySelector('input,textarea,select');
            if (first) {
              first.focus();
            }
            bindRowRemove(repeater, row);
            initMediaPickers(row);
          }
        });
      }

      rowsWrap.querySelectorAll('[data-row]').forEach(function (row) {
        bindRowRemove(repeater, row);
      });

      reindex(repeater);
    });
  }

  function bindRowRemove(repeater, row) {
    var remove = row.querySelector('[data-row-remove]');
    if (!remove || remove.dataset.beryonBound === '1') {
      return;
    }
    remove.dataset.beryonBound = '1';
    remove.addEventListener('click', function () {
      var label = strings.confirmRow || 'این ردیف حذف شود؟';
      if (!window.confirm(label)) {
        return;
      }
      row.remove();
      reindex(repeater);
    });
  }

  

  function initMediaPickers(scope) {
    var root = scope || document;

    root.querySelectorAll('[data-media]').forEach(function (wrap) {
      if (wrap.dataset.beryonBound === '1') {
        return;
      }
      wrap.dataset.beryonBound = '1';

      var input = wrap.querySelector('input[type="hidden"]');
      var textInput = wrap.querySelector('input[type="text"]');
      var field = input || textInput;
      var preview = wrap.querySelector('[data-media-preview]');
      var select = wrap.querySelector('[data-media-select]');
      var clear = wrap.querySelector('[data-media-clear]');
      var frame = null;

      
      var paint = function (url) {
        var value = field ? field.value : '';
        if (preview) {
          preview.innerHTML = url
            ? '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="" loading="lazy" decoding="async">'
            : '<span>' + (strings.noImage || 'بدون تصویر') + '</span>';
        }
        if (clear) {
          clear.disabled = !value;
        }
      };

      if (select) {
        select.addEventListener('click', function (event) {
          event.preventDefault();

          if (typeof window.wp === 'undefined' || !window.wp.media) {
            if (field) {
              field.focus();
            }
            return;
          }

          if (frame) {
            frame.open();
            return;
          }

          frame = window.wp.media({
            title: strings.mediaTitle || 'انتخاب تصویر',
            multiple: false,
            library: { type: 'image' }
          });

          frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            if (!attachment || !field) {
              return;
            }
            var url = (attachment.sizes && attachment.sizes.large && attachment.sizes.large.url) || attachment.url;
            field.value = input ? String(attachment.id) : url;
            field.dispatchEvent(new Event('change', { bubbles: true }));
            paint(url);
          });

          frame.open();
        });
      }

      if (clear) {
        clear.addEventListener('click', function (event) {
          event.preventDefault();
          if (field) {
            field.value = '';
            field.dispatchEvent(new Event('change', { bubbles: true }));
          }
          paint('');
        });
      }

      if (field) {
        field.addEventListener('change', function () {
          paint(field.value.indexOf('http') === 0 ? field.value : '');
        });
      }
    });
  }

  /* ============================================================== *
   * ۴) همگام‌سازی انتخابگر رنگ با فیلد متنی
   * ============================================================== */

  function initColorSync() {
    document.querySelectorAll('[data-sync]').forEach(function (picker) {
      var target = document.getElementById(picker.getAttribute('data-sync'));
      if (!target) {
        return;
      }
      picker.addEventListener('input', function () {
        target.value = picker.value;
        target.dispatchEvent(new Event('change', { bubbles: true }));
      });
      target.addEventListener('input', function () {
        if (/^#[0-9a-f]{6}$/i.test(target.value)) {
          picker.value = target.value;
        }
      });
    });

    if (window.jQuery && typeof window.jQuery.fn.wpColorPicker === 'function') {
      window.jQuery('.beryon-color-wp').wpColorPicker();
    }
  }

  /* ============================================================== *
   * ۵) درخواست‌های AJAX پنل
   * ============================================================== */

  function post(action, extra) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce || '');
    Object.keys(extra || {}).forEach(function (key) {
      body.set(key, extra[key]);
    });

    return window.fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, data: { message: strings.badResponse || 'پاسخ نامعتبر از سرور.' } };
      });
    });
  }

  function resultBox(button) {
    var scope = button.closest('.beryon-card') || button.closest('.wrap') || document;
    var box = scope.querySelector('[data-beryon-result]');
    if (!box) {
      box = document.createElement('div');
      box.className = 'beryon-result';
      box.setAttribute('data-beryon-result', '');
      box.setAttribute('role', 'status');
      button.parentElement.appendChild(box);
    }
    return box;
  }

  function render(button, payload) {
    var box = resultBox(button);
    var data = (payload && payload.data) || payload || {};
    var ok = payload && (payload.success === true || data.ok === true);

    box.hidden = false;
    box.className = 'beryon-result ' + (ok ? 'is-ok' : 'is-error');

    var lines = [];
    if (data.message) {
      lines.push(String(data.message));
    }
    if (data.version) {
      lines.push((strings.version || 'نسخه') + ': ' + data.version);
    }
    if (data.notes) {
      lines.push(String(data.notes));
    }
    if (typeof data.total === 'number') {
      lines.push((strings.files || 'فایل‌ها') + ': ' + data.total + ' | ' + (strings.changed || 'تغییر یافته') + ': ' + (data.modified || data.changed || 0));
    }
    if (Array.isArray(data.log)) {
      lines = lines.concat(data.log.slice(0, 12).map(String));
    }

    box.innerHTML = '<pre class="beryon-code">' +
      lines.map(function (line) {
        return line.replace(/[&<>]/g, function (c) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
        });
      }).join('\n') + '</pre>';
  }

  function initAjaxActions() {
    document.querySelectorAll('[data-beryon-action]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        var action = button.getAttribute('data-beryon-action');
        var ajaxAction = 'beryon_' + action.replace(/-/g, '_');
        var extra = {};

        if (button.dataset.file) {
          extra.file = button.dataset.file;
          var label = strings.confirmRollback || 'بازگردانی این پشتیبان، تغییرات بعد از آن را از بین می‌برد. ادامه می‌دهید؟';
          if (action === 'rollback' && !window.confirm(label)) {
            return;
          }
        }

        var original = button.textContent;
        button.disabled = true;
        button.textContent = strings.working || 'در حال پردازش…';

        post(ajaxAction, extra)
          .then(function (payload) {
            render(button, payload);
          })
          .catch(function () {
            render(button, { success: false, data: { message: strings.error || 'خطا در برقراری ارتباط با سرور.' } });
          })
          .then(function () {
            button.disabled = false;
            button.textContent = original;
          });
      });
    });
  }

  /* ============================================================== *
   * ۶) وضعیت دکمه ذخیره + تأیید عملیات مخرب
   * ============================================================== */

  function initForms() {
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        form.querySelectorAll('[data-loading]').forEach(function (button) {
          button.disabled = true;
          button.classList.add('is-busy');
          button.dataset.label = button.value || button.textContent;
          button.value = strings.saving || 'در حال ذخیره…';
          button.textContent = strings.saving || 'در حال ذخیره…';
        });
      });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      });
    });

    /* بررسی اندازه فایل پچ پیش از آپلود */
    var patchInput = document.getElementById('beryon-patch-file');
    if (patchInput && cfg.maxPatchSize) {
      patchInput.addEventListener('change', function () {
        var file = patchInput.files && patchInput.files[0];
        if (file && file.size > cfg.maxPatchSize) {
          window.alert(
            (strings.tooLarge || 'حجم فایل پچ بیش از حد مجاز است.') +
            ' (' + Math.round(file.size / 1048576) + 'MB)'
          );
          patchInput.value = '';
        }
      });
    }
  }

  /* ============================================================== *
   * اجرای اولیه
   * ============================================================== */

  ready(function () {
    initValidationFocus();
    initRepeaters();
    initMediaPickers(document);
    initColorSync();
    initAjaxActions();
    initForms();
  });
})();
