/* --- app.js --- */




(function () {
  'use strict';

  
  const header = document.querySelector('.header');
  const onScroll = () => {
    if (header) header.classList.toggle('is-stuck', window.scrollY > 10);
    const toTop = document.querySelector('.to-top');
    if (toTop) toTop.classList.toggle('show', window.scrollY > 480);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  
  const mobileMenu = document.querySelector('.mobile-menu');
  const burger = document.querySelector('.burger');
  const closeMenu = document.querySelector('.mobile-menu .close');
  const overlay = document.querySelector('.mobile-menu .overlay');
  const setMenu = (open) => {
    if (!mobileMenu) return;
    mobileMenu.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
    mobileMenu.setAttribute('aria-hidden', String(!open));
  };
  if (burger) burger.addEventListener('click', () => setMenu(true));
  if (closeMenu) closeMenu.addEventListener('click', () => setMenu(false));
  if (overlay) overlay.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  
  document.querySelectorAll('[data-subtoggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const sub = document.querySelector(btn.getAttribute('data-subtoggle'));
      if (sub) sub.classList.toggle('open');
    });
  });

  
  const revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('in'));
  }

  
  const counters = document.querySelectorAll('[data-count]');
  const runCount = (el) => {
    const target = parseFloat(el.getAttribute('data-count'));
    const dur = 1600;
    const t0 = performance.now();
    const fa = (n) => n.toLocaleString('fa-IR');
    const tick = (t) => {
      const p = Math.min((t - t0) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fa(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if ('IntersectionObserver' in window && counters.length) {
    const cio = new IntersectionObserver((ens) => {
      ens.forEach((en) => { if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); } });
    }, { threshold: 0.5 });
    counters.forEach((el) => cio.observe(el));
  }

  
  document.querySelectorAll('[data-filter-wrap]').forEach((wrap) => {
    const grid = wrap.querySelector('[data-product-grid]');
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.product-card'));
    const chips = wrap.querySelectorAll('.chip');
    const search = wrap.querySelector('[data-search]');
    const countEl = wrap.querySelector('[data-count-label]');
    const noRes = wrap.querySelector('.no-result');
    const fa = (n) => n.toLocaleString('fa-IR');
    const apply = () => {
      const activeChip = wrap.querySelector('.chip.active');
      const f = activeChip ? activeChip.getAttribute('data-filter') : 'all';
      const q = search ? search.value.trim().toLowerCase() : '';
      let shown = 0;
      cards.forEach((c) => {
        const cat = c.getAttribute('data-cat') || '';
        const tags = c.getAttribute('data-tags') || '';
        const name = (c.getAttribute('data-name') || c.textContent).toLowerCase();
        const okCat = f === 'all' || cat === f || tags.split(' ').indexOf(f) > -1;
        const okQ = !q || name.indexOf(q) > -1;
        const show = okCat && okQ;
        c.style.display = show ? '' : 'none';
        if (show) shown++;
      });
      if (countEl) countEl.textContent = fa(shown) + ' محصول';
      if (noRes) noRes.classList.toggle('show', shown === 0);
    };
    chips.forEach((ch) => ch.addEventListener('click', () => {
      chips.forEach((c) => c.classList.remove('active'));
      ch.classList.add('active');
      apply();
    }));
    if (search) search.addEventListener('input', apply);
    apply();
  });

  
  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const btns = tabs.querySelectorAll('.tab-btns button');
    const panels = tabs.querySelectorAll('.tab-panel');
    btns.forEach((b) => b.addEventListener('click', () => {
      btns.forEach((x) => x.classList.remove('active'));
      panels.forEach((p) => p.classList.remove('active'));
      b.classList.add('active');
      const panel = tabs.querySelector(b.getAttribute('data-target'));
      if (panel) panel.classList.add('active');
    }));
  });

  
  document.querySelectorAll('.acc-item').forEach((item) => {
    const btn = item.querySelector('button');
    const panel = item.querySelector('.panel');
    btn.addEventListener('click', () => {
      const open = item.classList.contains('open');
      item.closest('.accordion').querySelectorAll('.acc-item.open').forEach((o) => {
        o.classList.remove('open');
        o.querySelector('.panel').style.maxHeight = null;
        o.querySelector('button').setAttribute('aria-expanded', 'false');
      });
      if (!open) {
        item.classList.add('open');
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  
  const gallery = document.querySelector('.gallery');
  if (gallery) {
    const mainImg = gallery.querySelector('.main-img img');
    gallery.querySelectorAll('.thumbs button').forEach((th) => {
      th.addEventListener('click', () => {
        gallery.querySelectorAll('.thumbs button').forEach((x) => x.classList.remove('active'));
        th.classList.add('active');
        const src = th.getAttribute('data-img');
        if (src && mainImg) { mainImg.style.opacity = 0; setTimeout(() => { mainImg.src = src; mainImg.style.opacity = 1; }, 180); }
      });
    });
  }

  
  const lightbox = document.querySelector('.lightbox');
  if (lightbox) {
    const lbImg = lightbox.querySelector('img');
    let lbLastFocus = null;
    document.querySelectorAll('[data-lightbox]').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        const src = el.getAttribute('data-lightbox') || el.querySelector('img')?.src;
        if (!src) return;
        lbImg.src = src;
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        lbLastFocus = document.activeElement;
        lightbox.querySelector('.close').focus();
      });
    });
    const closeLb = () => { lightbox.classList.remove('open'); lightbox.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (lbLastFocus) lbLastFocus.focus(); };
    lightbox.querySelector('.close').addEventListener('click', closeLb);
    lightbox.addEventListener('click', (e) => { if (e.target === lightbox) closeLb(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLb(); });
  }

  
  const faDigits = (s) => String(s).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
  const trackingCode = () => {
    const y = new Date().getFullYear();
    const rnd = Math.floor(10000 + Math.random() * 89999);
    return 'BRY-' + y + '-' + rnd;
  };
  document.querySelectorAll('form[data-form]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;
      form.querySelectorAll('[required]').forEach((inp) => {
        const field = inp.closest('.field') || inp.closest('.check-line');
        let ok = inp.value.trim() !== '';
        if (ok && inp.type === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inp.value);
        if (ok && inp.getAttribute('inputmode') === 'tel') ok = /^0?9\d{9}$/.test(inp.value.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
        if (ok && inp.type === 'checkbox') ok = inp.checked;
        if (field) field.classList.toggle('invalid', !ok);
        if (!ok) valid = false;
      });
      if (!valid) {
        const firstBad = form.querySelector('.invalid input, .invalid select, .invalid textarea');
        if (firstBad) firstBad.focus();
        return;
      }
      const scope = form.closest('.form-card') || form.closest('.container') || form.parentElement;
      const success = scope.querySelector('.form-success');
      if (success) {
        const codeEl = success.querySelector('.track');
        if (codeEl) codeEl.textContent = faDigits(trackingCode());
        form.style.display = 'none';
        success.classList.add('show');
        success.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
    
    form.addEventListener('input', (e) => {
      const field = e.target.closest('.field');
      if (field) field.classList.remove('invalid');
    });
  });

  
  document.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href');
      if (id.length > 1) {
        const el = document.querySelector(id);
        if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      }
    });
  });

  
  document.querySelectorAll('[data-year]').forEach((el) => { el.textContent = faDigits(new Date().getFullYear()); });
})();


(function () {
  'use strict';

  var cfg = window.beryonConfig || {};
  var rest = cfg.restUrl || '/wp-json/beryon/v1';
  var i18n = cfg.i18n || {};
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  

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
              
            }
            success.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
          } else {
            showNote(form, payload.message || i18n.track || 'پیام شما ثبت شد.', 'ok');
          }
          form.reset();
          return;
        }

        
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
          
          window.location.href = target;
        }
        document.body.removeChild(helper);
        done();
      }
    });
  });

  

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
