/* ==================================================================
 *  PWA – Servis çalışanını kaydeder ve güncellemeyi bildirir
 * ------------------------------------------------------------------
 *  Yalnızca PWA ayarı açıkken yüklenir (bkz. layouts/admin, site).
 *
 *  GÜNCELLEME SORUNU: Servis çalışanı eski sürümü önbellekten
 *  sunmaya devam edebilir; kullanıcı "site güncellenmiyor" der.
 *  Bu yüzden yeni sürüm hazır olduğunda bir bildirim gösterip
 *  sayfayı yenilemeyi öneriyoruz.
 * ================================================================== */

/* global CY, jQuery */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) { return; }

    var meta = document.querySelector('meta[name="cy-sw"]');
    if (!meta) { return; }

    var swUrl = meta.getAttribute('content');
    if (!swUrl) { return; }

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
}());
