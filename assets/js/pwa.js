/* ==================================================================
 *  PWA – Servis çalışanını kaydeder, kapatıldığında SİLER
 * ------------------------------------------------------------------
 *  Davranış tek bir etikete bakar: <meta name="cy-sw">
 *
 *    · Etiket VARSA  → servis çalışanını kaydet, güncellemeyi bildir
 *    · Etiket YOKSA  → BU UYGULAMANIN servis çalışanını sil,
 *                      önbelleğini boşalt
 *
 *  İKİNCİSİ NEDEN ŞART? Servis çalışanı bir kez kaydedildiğinde
 *  tarayıcıda KALICIDIR. "Çevrimdışı Çalışma" ayarını kapatmak
 *  sunucuda etiketi kaldırmakla bitseydi, o siteyi daha önce açmış
 *  herkesin tarayıcısı sayfaları eski önbellekten sunmaya devam
 *  ederdi. Bu yüzden betik PWA kapalıyken de yüklenir: tek işi
 *  geride kalanı temizlemektir.
 *
 *  YALNIZCA KENDİ KAYDIMIZ. Eskiden aynı alan adındaki BÜTÜN servis
 *  çalışanları siliniyordu: localhost'ta /ci4/ gibi başka bir projenin
 *  servis çalışanı da gidiyordu. Artık yalnızca kapsamı bu uygulamanın
 *  klasörü olan kayıt ve o kapsamın önbellekleri silinir.
 * ================================================================== */

/* global CY */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) { return; }

    /* Uygulamanın kökü: bu betik assets/js/ altında durur, iki üst klasör
     * uygulamanın kendisidir. document.currentScript yalnızca betik
     * ilk çalışırken dolu olduğu için hemen okunur. */
    var betik  = document.currentScript;
    var kapsam = betik && betik.src ? new URL('../../', betik.src).href : '';

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

    /* Kapsamı BU uygulama olan servis çalışanını ve onun önbelleklerini
     * siler (önbellek adı "cy-vN|<kapsam yolu>|tür" biçimindedir, bkz. sw.js). */
    function temizle() {
        if (!kapsam) { return; }

        var kapsamYolu = new URL(kapsam).pathname;

        navigator.serviceWorker.getRegistrations().then(function (kayitlar) {
            kayitlar.forEach(function (kayit) {
                if (kayit.scope === kapsam) { kayit.unregister(); }
            });
        }).catch(function () { /* yoksay */ });

        if (!('caches' in window)) { return; }

        caches.keys().then(function (adlar) {
            adlar.forEach(function (ad) {
                if (/^cy-v\d+\|/.test(ad) && ad.split('|')[1] === kapsamYolu) {
                    caches.delete(ad);
                }
            });
        }).catch(function () { /* yoksay */ });
    }
}());
