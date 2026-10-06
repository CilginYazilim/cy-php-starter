/* ==================================================================
 *  ANA SAYFA — kod sekmeleri ve görünürken belirme
 * ------------------------------------------------------------------
 *  Bağımlılıksız, küçük. JavaScript kapalıyken sayfa eksiksizdir:
 *  bütün kod örnekleri alt alta görünür, bölümler zaten görünürdür.
 * ================================================================== */
(function () {
    'use strict';

    /* --- Kod sekmeleri (ARIA tabs deseni; ok tuşlarıyla gezilir) --- */
    document.querySelectorAll('[data-tabs]').forEach(function (kutu) {
        var sekmeler = Array.prototype.slice.call(kutu.querySelectorAll('[role="tab"]'));

        function sec(sekme) {
            sekmeler.forEach(function (s) {
                var secili = s === sekme;
                s.setAttribute('aria-selected', secili ? 'true' : 'false');
                s.tabIndex = secili ? 0 : -1;
                document.getElementById(s.getAttribute('aria-controls')).hidden = !secili;
            });
        }

        sekmeler.forEach(function (sekme, i) {
            sekme.addEventListener('click', function () { sec(sekme); });
            sekme.addEventListener('keydown', function (olay) {
                var hedef = null;
                if (olay.key === 'ArrowRight') { hedef = sekmeler[(i + 1) % sekmeler.length]; }
                if (olay.key === 'ArrowLeft')  { hedef = sekmeler[(i - 1 + sekmeler.length) % sekmeler.length]; }
                if (hedef) { olay.preventDefault(); sec(hedef); hedef.focus(); }
            });
        });

        if (sekmeler.length) { sec(sekmeler[0]); }
    });

    /* --- Görünürken belir --- */
    var azalt = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ogeler = document.querySelectorAll('[data-reveal]');

    if (azalt || !('IntersectionObserver' in window) || !ogeler.length) {
        return;
    }

    document.documentElement.classList.add('cy-reveal-ready');

    var gozcu = new IntersectionObserver(function (girdiler) {
        girdiler.forEach(function (girdi) {
            if (girdi.isIntersecting) {
                girdi.target.classList.add('is-visible');
                gozcu.unobserve(girdi.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });

    ogeler.forEach(function (oge) { gozcu.observe(oge); });
}());
