/* ==================================================================
 *  KURULUM SİHİRBAZI — küçük yardımcılar (bağımlılıksız)
 * ------------------------------------------------------------------
 *  · "Bağlantıyı dene": formu sunucuya gönderir, sonucu anında yazar
 *  · Parola gücü göstergesi ve göster/gizle
 *  · "Demo modu" anahtarı yalnızca "Örnek veriyle kur" açıkken görünür
 *  · "Kur" düğmesine basınca kurulum adımlarını canlandıran katman
 *  JavaScript kapalıyken sihirbaz yine eksiksiz çalışır.
 * ================================================================== */
(function () {
    'use strict';

    /* --- Bağlantıyı dene --- */
    var dene  = document.getElementById('db_test');
    var form  = document.getElementById('db_form');
    var sonuc = document.getElementById('db_test_sonuc');

    if (dene && form && sonuc && window.fetch) {
        dene.hidden = false;

        dene.addEventListener('click', function () {
            var veri = new FormData(form);
            veri.set('islem', 'dene');

            dene.disabled = true;
            sonuc.className = 'kur-test';
            sonuc.textContent = 'Bağlanılıyor…';

            fetch('index.php', { method: 'POST', body: veri, credentials: 'same-origin' })
                .then(function (yanit) { return yanit.json(); })
                .then(function (j) {
                    sonuc.className = 'kur-test ' + (j.ok ? 'is-ok' : 'is-error');
                    sonuc.textContent = (j.ok ? '✓ ' : '✕ ') + j.mesaj;

                    if (j.ayrinti) {
                        var kucuk = document.createElement('small');
                        kucuk.textContent = j.ayrinti;
                        sonuc.appendChild(kucuk);
                    }
                })
                .catch(function () {
                    sonuc.className = 'kur-test is-error';
                    sonuc.textContent = '✕ Sunucudan yanıt alınamadı.';
                })
                .then(function () { dene.disabled = false; });
        });
    }

    /* --- Parola gücü --- */
    var sifre  = document.getElementById('admin_sifre');
    var olcer  = document.getElementById('sifre_guc');

    function guc(deger) {
        if (deger.length < 8 || !/[A-Za-zÇĞİÖŞÜçğıöşü]/.test(deger) || !/[0-9]/.test(deger)) {
            return deger.length === 0 ? 0 : 1;
        }

        var puan = 1;
        if (deger.length >= 12) { puan++; }
        if (/[a-zçğıöşü]/.test(deger) && /[A-ZÇĞİÖŞÜ]/.test(deger) && /[^A-Za-z0-9ÇĞİÖŞÜçğıöşü]/.test(deger)) { puan++; }

        return Math.min(3, puan);
    }

    if (sifre && olcer) {
        var etiket = olcer.querySelector('.kur-meter__label');
        var metin  = ['En az 8 karakter; harf ve rakam', 'Zayıf', 'Orta', 'Güçlü'];

        sifre.addEventListener('input', function () {
            var p = guc(sifre.value);
            olcer.setAttribute('data-guc', String(p));
            etiket.textContent = sifre.value === '' ? metin[0] : 'Parola gücü: ' + metin[p];
        });
    }

    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('[data-sifre-goster]');
        if (!dugme) { return; }

        var alan  = dugme.parentNode.querySelector('input');
        var goster = alan.type === 'password';

        alan.type = goster ? 'text' : 'password';
        dugme.setAttribute('aria-label', goster ? 'Parolayı gizle' : 'Parolayı göster');
    });

    /* --- Demo modu yalnızca örnek veriyle --- */
    var ornek = document.getElementById('ornek_veri');
    var demo  = document.getElementById('demo_modu_satiri');

    function demoGoster() {
        demo.hidden = !ornek.checked;
        if (!ornek.checked) { document.getElementById('demo_modu').checked = false; }
    }

    if (ornek && demo) {
        ornek.addEventListener('change', demoGoster);
        demoGoster();
    }

    /* --- Örneksiz kurulumda varsayılan site adı nötr ---
     * "CY PHP Starter" örnek vitrinin adıdır; örnek veri kapatılıp kurulan
     * bir müşteri sitesinde kalmasın. Kullanıcı adı kendisi yazdıysa
     * dokunulmaz; kutu yeniden açılırsa ilk ad geri gelir. */
    var siteAdi = document.getElementById('site_adi');

    if (ornek && siteAdi) {
        var ilkAd    = siteAdi.value;
        var notrAd   = 'Yeni Proje';
        var elleYazdi = false;

        siteAdi.addEventListener('input', function () { elleYazdi = true; });

        ornek.addEventListener('change', function () {
            if (elleYazdi) { return; }

            if (!ornek.checked && siteAdi.value === ilkAd) {
                siteAdi.value = notrAd;
            } else if (ornek.checked && siteAdi.value === notrAd) {
                siteAdi.value = ilkAd;
            }
        });
    }

    /* --- "Kuruluyor…" katmanı --- */
    var kurForm = document.getElementById('kur_form');
    var katman  = document.getElementById('kur_overlay');

    if (kurForm && katman) {
        kurForm.addEventListener('submit', function () {
            var dugme = document.getElementById('kur_dugmesi');
            if (dugme) { dugme.disabled = true; }

            katman.hidden = false;

            // Kurulum tek istekte çalışır; adımlar yaklaşık sürelerle işaretlenir.
            var satirlar = katman.querySelectorAll('.kur-log li');
            satirlar.forEach(function (satir, i) {
                window.setTimeout(function () {
                    satir.classList.add('is-ok');
                    satir.firstElementChild.textContent = '✓';
                }, 700 * (i + 1));
            });
        });
    }
}());
