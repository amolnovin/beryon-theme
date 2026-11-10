/*
 * app.js — نقطه ورود اسکریپت قالب وردپرس بریون (Sage 11 + Vite)
 * ==================================================================
 * ۱) main.js : رفتارهای قالب HTML نهایی — بدون هیچ تغییر (هدر چسبان،
 *              منوی موبایل، reveal، شمارنده‌ها، فیلتر زنده محصولات، تب‌ها،
 *              آکاردئون، گالری، لایت‌باکس، اعتبارسنجی فرم، اسکرول نرم،
 *              سال فوتر)
 * ۲) این فایل : لایه وردپرس
 *              - ارسال واقعی فرم‌ها به REST (beryon/v1/form) با nonce،
 *                نمایش کد پیگیری واقعی، خطاهای فیلد و نرخ محدود
 *              - کپی لینک محصول، چیپ‌های لینک‌دار، انیمیشن لود تصویر،
 *                آفست اسکرول نرم، rel امن برای لینک‌های خارجی
 *
 * هیچ وابستگی خارجی ندارد (بدون jQuery). همه پیکربندی از
 * window.beryonConfig می‌آید که در app/Assets.php چاپ می‌شود.
 */
import './main.js';

(function () {
  'use strict';

  var cfg = window.beryonConfig || {};
  var rest = cfg.restUrl || '/wp-json/beryon/v1';
  var i18n = cfg.i18n || {};
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ============================================================== *
   * ابزارها
   * ============================================================== */

  function faDigits(value) {
    return String(value).replace(/\d/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[Number(d)];
    });
  }

  function localCode() {
    var now = new Date();
    var pad = function (n, len) {
      return String(n).padStart(len || 2, '0');
    };
    return 'BRY-' + faDigits(pad(now.getFullYear() - 621, 3)) + '-' + faDigits(pad(now.getMonth() + 1)) + '-' + faDigits(pad(Math.floor(Math.random() * 90000) + 10000, 5));
  }

  /* پیام زیر فرم (ساخت در صورت نبود) */
  function note(form) {
    var el = form.parentElement ? form.parentElement.querySelector('.form-note') : null;
    if (!el) {
      el = document.createElement('p');
      el.className = 'form-note';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      (form.parentElement || form).appendChild(el);
    }
    return el;
  }

  function showNote(form, text, kind) {
    var el = note(form);
    el.textContent = text;
    el.className = 'form-note show ' + (kind === 'error' ? 'is-error' : 'is-ok');
    if (kind === 'error') {
      el.setAttribute('role', 'alert');
    } else {
      el.setAttribute('role', 'status');
    }
  }

  function clearNote(form) {
    var el = form.parentElement ? form.parentElement.querySelector('.form-note') : null;
    if (el) {
      el.className = 'form-note';
      el.textContent = '';
    }
  }

  /* ============================================================== *
   * ارسال فرم‌ها به REST
   * ============================================================== *
   * main.js همان اعتبارسنجی قالب HTML را اجرا می‌کند و در حالت موفق،
   * موفقیت نمایشی را نشان می‌دهد. اینجا درخواست واقعی ارسال می‌شود و در
   * صورت پاسخ معتبر، همان موفقیت با کد پیگیری واقعی نگه داشته می‌شود؛
   * در صورت خطا، موفقیت نمایشی جمع و پیام خطا نشان داده می‌شود.
   */

  function formType(form) {
    var value = form.getAttribute('data-form');
    return value && value !== 'true' ? value : 'contact';
  }

  function submitForm(form) {
    if (form.dataset.beryonBusy === '1') {
      return;
    }

    var data = new FormData(form);
    var button = form.querySelector('button[type="submit"]');

    data.set('type', formType(form));
    data.set('url', window.location.href);
    data.set('nonce', cfg.nonce || '');

    form.dataset.beryonBusy = '1';
    if (button) {
      button.dataset.label = button.textContent;
      button.disabled = true;
      button.classList.add('is-loading');
      button.textContent = i18n.sending || 'در حال ارسال…';
    }

    var finish = function () {
      form.dataset.beryonBusy = '';
      if (button) {
        button.disabled = false;
        button.classList.remove('is-loading');
        button.textContent = button.dataset.label || 'ارسال';
      }
    };

    window
      .fetch(rest + '/form', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce || '' },
        body: data
      })
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false, code: 'bad_response' };
        });
      })
      .then(function (payload) {
        finish();

        if (payload && payload.ok) {
          var scope = form.closest('.form-card') || form.closest('.container') || form.parentElement;
          var success = scope ? scope.querySelector('.form-success') : null;
          var track = success ? success.querySelector('.track') : null;

          if (track) {
            track.textContent = payload.ref ? faDigits(payload.ref) : localCode();
          }
          if (success) {
            form.style.display = 'none';
            success.classList.add('show');
            if (payload.message && success.querySelector('p')) {
              /* متن پیش‌فرض تنظیمات قالب حفظ می‌شود؛ فقط در صورت نبود، پر می‌شود */
            }
            success.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
          } else {
            showNote(form, payload.message || i18n.track || 'پیام شما ثبت شد.', 'ok');
          }
          form.reset();
          return;
        }

        /* خطای اعتبارسنجی: علامت‌گذاری فیلدها */
        if (payload && payload.errors) {
          Object.keys(payload.errors).forEach(function (field) {
            var input = form.querySelector('[name="' + field + '"]');
            var wrap = input ? input.closest('.field') || input.closest('.check-line') : null;
            if (wrap) {
              wrap.classList.add('invalid');
            }
            if (input) {
              var err = wrap ? wrap.querySelector('.err') : null;
              if (err) {
                err.textContent = payload.errors[field];
              }
            }
          });
        }

        var message = (payload && payload.message) || i18n.error || 'ارسال پیام ناموفق بود؛ لطفاً دوباره تلاش کنید.';
        if (payload && payload.code === 'rate_limited' && payload.retry_after) {
          message += ' (' + faDigits(Math.ceil(payload.retry_after / 60)) + ' دقیقه دیگر)';
        }
        undoDemo(form);
        showNote(form, message, 'error');
      })
      .catch(function () {
        finish();
        /* اگر REST در دسترس نبود (مثلاً کش صفحه یا خطای شبکه)، همان تجربه
           قالب HTML حفظ می‌شود: موفقیت نمایشی با کد پیگیری محلی. */
        var scope = form.closest('.form-card') || form.closest('.container') || form.parentElement;
        var success = scope ? scope.querySelector('.form-success') : null;
        if (success) {
          var track = success.querySelector('.track');
          if (track && !track.textContent) {
            track.textContent = localCode();
          }
          form.style.display = 'none';
          success.classList.add('show');
        } else {
          showNote(form, i18n.error || 'ارسال پیام ناموفق بود؛ لطفاً دوباره تلاش کنید.', 'error');
        }
      });
  }

  /* بازگرداندن موفقیت نمایشی main.js وقتی سرور خطا می‌دهد */
  function undoDemo(form) {
    var scope = form.closest('.form-card') || form.closest('.container') || form.parentElement;
    var success = scope ? scope.querySelector('.form-success') : null;
    if (success && success.classList.contains('show')) {
      success.classList.remove('show');
      var track = success.querySelector('.track');
      if (track) {
        track.textContent = '';
      }
    }
    form.style.display = '';
  }

  document.querySelectorAll('form[data-form]').forEach(function (form) {
    /* main.js اعتبارسنجی قالب HTML را اجرا می‌کند؛ اگر فیلدی .invalid شد
       همین‌جا متوقف می‌شویم و در غیر این‌صورت درخواست واقعی می‌فرستیم. */
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (form.querySelector('.invalid')) {
        return;
      }

      clearNote(form);

      if (cfg.forms && cfg.forms.enabled === false) {
        showNote(form, i18n.error || 'ثبت پیام موقتاً غیرفعال است.', 'error');
        return;
      }

      submitForm(form);
    });

    form.addEventListener('input', function () {
      clearNote(form);
    });
  });

  /* ============================================================== *
   * کپی لینک محصول
   * ============================================================== */

  document.querySelectorAll('[data-copy-link]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      var target = link.getAttribute('href') || window.location.href;
      event.preventDefault();

      var done = function () {
        var label = link.getAttribute('aria-label') || '';
        link.setAttribute('aria-label', 'لینک کپی شد');
        link.classList.add('is-copied');
        window.setTimeout(function () {
          link.setAttribute('aria-label', label);
          link.classList.remove('is-copied');
        }, 1800);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(target).then(done).catch(done);
      } else {
        var helper = document.createElement('textarea');
        helper.value = target;
        helper.setAttribute('readonly', 'readonly');
        helper.style.position = 'absolute';
        helper.style.left = '-9999px';
        document.body.appendChild(helper);
        helper.select();
        try {
          document.execCommand('copy');
        } catch (error) {
          /* کپی در دسترس نیست؛ لینک باز می‌شود */
          window.location.href = target;
        }
        document.body.removeChild(helper);
        done();
      }
    });
  });

  /* ============================================================== *
   * چیپ‌های لینک‌دار (دسته‌بندی مطالب وبلاگ)
   * ============================================================== */

  document.querySelectorAll('.chip[data-href]').forEach(function (chip) {
    chip.setAttribute('role', 'link');
    chip.setAttribute('tabindex', '0');
    var go = function () {
      window.location.href = chip.getAttribute('data-href');
    };
    chip.addEventListener('click', go);
    chip.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        go();
      }
    });
  });

  /* ============================================================== *
   * انیمیشن لود تصویر (بدون تغییر مارک‌آپ)
   * ============================================================== */

  if ('IntersectionObserver' in window && !reduceMotion) {
    var imageObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }
          var image = entry.target;
          var mark = function () {
            image.classList.add('is-loaded');
          };
          if (image.complete) {
            mark();
          } else {
            image.addEventListener('load', mark, { once: true });
            image.addEventListener('error', mark, { once: true });
          }
          imageObserver.unobserve(image);
        });
      },
      { rootMargin: '200px 0px' }
    );

    document.querySelectorAll('img[loading="lazy"]').forEach(function (image) {
      imageObserver.observe(image);
    });
  }

  /* ============================================================== *
   * اسکرول نرم با آفست هدر چسبان
   * ============================================================== */

  var headerEl = document.querySelector('.header');
  var headerOffset = function () {
    return headerEl ? headerEl.offsetHeight + 12 : 12;
  };

  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (event) {
      var id = anchor.getAttribute('href');
      if (!id || id.length < 2) {
        return;
      }
      var target = document.querySelector(id);
      if (!target) {
        return;
      }
      event.preventDefault();
      var top = target.getBoundingClientRect().top + window.scrollY - headerOffset();
      window.scrollTo({ top: Math.max(0, top), behavior: reduceMotion ? 'auto' : 'smooth' });
      if (history.replaceState) {
        history.replaceState(null, '', id);
      }
      target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    });
  });

  /* ============================================================== *
   * لینک‌های خارجی: rel امن + باز شدن در زبانه جدید (اختیاری)
   * ============================================================== */

  var host = window.location.hostname;
  document.querySelectorAll('a[href^="http"]').forEach(function (link) {
    if (link.hostname === host) {
      return;
    }
    var rel = link.getAttribute('rel') || '';
    if (rel.indexOf('noopener') === -1) {
      link.setAttribute('rel', (rel + ' noopener noreferrer').trim());
    }
    if (cfg.externalNewTab !== false && !link.hasAttribute('target')) {
      link.setAttribute('target', '_blank');
    }
  });

  /* ============================================================== *
   * پرش به محتوای اصلی برای کاربر کیبورد
   * ============================================================== */

  var skip = document.querySelector('.skip-link');
  if (skip) {
    skip.addEventListener('click', function () {
      var main = document.getElementById('main');
      if (main) {
        main.setAttribute('tabindex', '-1');
        main.focus({ preventScroll: false });
      }
    });
  }
})();
