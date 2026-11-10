/* ============================================================
   بریون | اسکریپت اصلی قالب — بدون وابستگی external
   ============================================================ */
(function () {
  'use strict';

  /* ---------- هدر چسبان ---------- */
  const header = document.querySelector('.header');
  const onScroll = () => {
    if (header) header.classList.toggle('is-stuck', window.scrollY > 10);
    const toTop = document.querySelector('.to-top');
    if (toTop) toTop.classList.toggle('show', window.scrollY > 480);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- منوی موبایل ---------- */
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

  /* زیرمنوهای موبایل */
  document.querySelectorAll('[data-subtoggle]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const sub = document.querySelector(btn.getAttribute('data-subtoggle'));
      if (sub) sub.classList.toggle('open');
    });
  });

  /* ---------- نمایش هنگام اسکرول ---------- */
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

  /* ---------- شمارنده‌ها ---------- */
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

  /* ---------- فیلتر و جست‌وجوی محصولات ---------- */
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

  /* ---------- تب‌ها ---------- */
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

  /* ---------- آکاردئون ---------- */
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

  /* ---------- گالری محصول ---------- */
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

  /* ---------- لایت‌باکس ---------- */
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

  /* ---------- فرم‌ها + کد پیگیری ---------- */
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
    /* پاک‌سازی خطا هنگام تایپ */
    form.addEventListener('input', (e) => {
      const field = e.target.closest('.field');
      if (field) field.classList.remove('invalid');
    });
  });

  /* ---------- اسکرول نرم لنگرها ---------- */
  document.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href');
      if (id.length > 1) {
        const el = document.querySelector(id);
        if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      }
    });
  });

  /* ---------- سال فوتر ---------- */
  document.querySelectorAll('[data-year]').forEach((el) => { el.textContent = faDigits(new Date().getFullYear()); });
})();
