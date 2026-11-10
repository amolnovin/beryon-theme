/* ==========================================================
   قالب شروع - جاوااسکریپت سبک
   - parallax سبک فقط با transform
   - fallback برای reveal در مرورگرهای بدون scroll-driven
   - احترام به prefers-reduced-motion
   ========================================================== */
(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ---------- Parallax ---------- */
  const parallaxLayers = Array.from(document.querySelectorAll('[data-parallax]'));
  let ticking = false;

  function updateParallax() {
    ticking = false;
    if (reduceMotion.matches) return;
    const y = window.scrollY;
    for (const el of parallaxLayers) {
      const speed = parseFloat(el.dataset.parallax) || 0;
      el.style.transform = `translate3d(0, ${(y * speed).toFixed(1)}px, 0)`;
    }
  }

  function onScroll() {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  }

  if (parallaxLayers.length) {
    window.addEventListener('scroll', onScroll, { passive: true });
    updateParallax();
  }

  /* ---------- Reveal fallback ----------
     اگر مرورگر animation-timeline را پشتیبانی نکند، از IntersectionObserver استفاده می‌کنیم.
     در غیر این صورت، CSS کار را انجام می‌دهد.
  */
  const supportsScrollTimeline = CSS.supports('animation-timeline', 'view()');
  const revealEls = document.querySelectorAll('.reveal');

  if (!supportsScrollTimeline && !reduceMotion.matches && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('js-reveal');
    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        }
      },
      { threshold: 0.15 }
    );
    revealEls.forEach((el) => io.observe(el));
  }

  /* ---------- فرم: اعتبارسنجی ساده و پیام واضح ---------- */
  const form = document.querySelector('.contact-form');
  if (form) {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) firstInvalid.focus();
      }
    });
  }
})();
