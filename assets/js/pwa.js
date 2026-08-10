/* ==================================================================
 *  PWA – Servis çalışanını kaydeder, kapatıldığında SİLER
 * ------------------------------------------------------------------
 *  Davranış tek bir etikete bakar: <meta name="cy-sw">
 *
 *    · Etiket VARSA  → servis çalışanını kaydet, güncellemeyi bildir
 *    · Etiket YOKSA  → kayıtlı servis çalışanını sil, önbelleğini boşalt
 *
 *  İKİNCİSİ NEDEN ŞART? Servis çalışanı bir kez kaydedildiğinde
 *  tarayıcıda KALICIDIR. "Çevrimdışı Çalışma" ayarını kapatmak
 *  sunucuda etiketi kaldırmakla bitseydi, o siteyi daha önce açmış
 *  herkesin tarayıcısı sayfaları eski önbellekten sunmaya devam
 *  ederdi ve yönetici "ayarı kapattım ama hâlâ eski sayfa geliyor"
 *  derdi. Bu yüzden betik PWA kapalıyken de yüklenir: tek işi
 *  geride kalanı temizlemektir.
 * ================================================================== */

/* global CY */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) { return; }

    var meta  = document.querySelector('meta[name="cy-sw"]');
    var swUrl = meta ? meta.getAttribute('content') : '';

    if (!swUrl) {
        temizle();
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register(swUrl).then(function (registration) {
            registration.addEventListener('updatefound', function () {
                var yeni = registration.installing;
                if (!yeni) { return; }

                yeni.addEventListener('statechange', function () {
                    // "installed" + mevcut bir kontrolcü varsa: bu bir GÜNCELLEME.
                    if (yeni.state === 'installed' && navigator.serviceWorker.controller) {
                        if (window.CY && CY.notify) {
                            CY.notify('Yeni sürüm hazır. Sayfayı yenileyin.', 'info');
                        }
                    }
                });
            });
        }).catch(function () {
            // Kayıt başarısız olursa site normal çalışmaya devam eder.
        });
    });

    /* Kayıtlı servis çalışanını ve BU UYGULAMANIN önbelleklerini siler.
     * Yalnızca "cy-" ile başlayan önbellekler silinir; aynı alan adında
     * duran başka bir uygulamanın verisine dokunmayız. */
    function temizle() {
        navigator.serviceWorker.getRegistrations().then(function (kayitlar) {
            kayitlar.forEach(function (kayit) { kayit.unregister(); });
        }).catch(function () { /* yoksay */ });

        if (!('caches' in window)) { return; }

        caches.keys().then(function (adlar) {
            adlar.forEach(function (ad) {
                if (ad.indexOf('cy-') === 0) { caches.delete(ad); }
            });
        }).catch(function () { /* yoksay */ });
    }
}());
