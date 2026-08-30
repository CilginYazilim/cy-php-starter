/* ==================================================================
 *  CY ADMIN – PANEL KABUĞU
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Bu dosya her sayfada yüklenir ve şunları yönetir:
 *
 *    1. Genel yardımcılar  (CY.url, CY.notify, CY.escape)
 *    2. Sol menü           (mobil çekmece + masaüstü daraltma)
 *    3. Tema               (açık / koyu, çerezde saklanır)
 *    4. Bildirimler        (sunucudan gelen flash mesajları)
 *    5. Ortak form davranışları (parola göster, görsel önizleme)
 *
 *  Sayfaya özel kod ayrı dosyalardadır (users.js, login.js).
 *
 *  NEDEN SATIR İÇİ <script> KULLANMIYORUZ?
 *  Content-Security-Policy başlığımız satır içi script'e izin vermez.
 *  Bu, olası bir XSS açığında saldırganın kod çalıştırmasını
 *  engelleyen güçlü bir ikinci savunma hattıdır.
 * ================================================================== */

/* global bootstrap, jQuery */
window.CY = (function ($) {
    'use strict';

    var CY = {};

    /* =============================================================
     *  1) GENEL YARDIMCILAR
     * ============================================================= */

    /** Sayfadaki CSRF anahtarı. Her AJAX isteğine eklenir. */
    CY.token = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    /**
     * Uygulama içi adres üretir.
     *   CY.url('api/users/list')  →  "index.php?r=api/users/list"
     *
     * Kalıbı sunucu <meta name="cy-base"> ile bildirir; böylece
     * "temiz adres" ayarı açıldığında JavaScript'i değiştirmek
     * gerekmez.
     */
    CY.url = function (path) {
        var meta = document.querySelector('meta[name="cy-base"]');
        var tpl  = meta ? meta.getAttribute('content') : 'index.php?r=__PATH__';

        return tpl.replace('__PATH__', path);
    };

    /**
     * Metni HTML'e güvenle koymak için kaçışlar.
     * Sunucudan gelen veriyi innerHTML ile basmak zorunda kaldığımız
     * nadir yerlerde kullanılır. Mümkün olan her yerde .text() tercih
     * edilmelidir; o zaten kaçışlama gerektirmez.
     */
    CY.escape = function (value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    };

    /** Çerez yazar (tema ve menü tercihi için). */
    CY.setCookie = function (name, value, days) {
        var expires = new Date(Date.now() + (days || 365) * 864e5).toUTCString();
        var secure  = location.protocol === 'https:' ? '; Secure' : '';

        document.cookie = name + '=' + encodeURIComponent(value) +
                          '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
    };

    CY.getCookie = function (name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    };

    /* =============================================================
     *  PANEL TABLOLARI (DataTables)
     * -------------------------------------------------------------
     *  Kullanıcılar, Mesajlar ve E-posta ekranları aynı tabloyu
     *  kurar: sunucu taraflı sayfalama, POST + CSRF, Türkçe metinler,
     *  aynı sayfa düzeni. Bu kurulum üç dosyada birebir tekrar
     *  ediyordu; bir metni düzeltmek üç yerde düzeltmek demekti.
     *
     *  Artık ortak kısım burada. Sayfalar yalnızca KENDİNE ÖZGÜ
     *  olanı verir: sütun tanımları, filtreler, boş liste metni.
     * ============================================================= */

    /**
     * Sayfa başına kayıt sayısı. Yönetici bunu panelden değiştirebilir
     * (Ayarlar → Sistem); sunucu değeri <meta> ile bildirir.
     */
    CY.pageLength = function () {
        var meta  = document.querySelector('meta[name="cy-page-length"]');
        var value = meta ? parseInt(meta.getAttribute('content'), 10) : 0;

        return value > 0 ? value : 10;
    };

    /** DataTables'ın Türkçe metinleri. "isim" listelenen şeyin adıdır. */
    CY.tableLanguage = function (isim) {
        return {
            emptyTable:     'Henüz ' + isim + ' bulunmuyor.',
            info:           '_TOTAL_ kayıttan _START_ – _END_ arası',
            infoEmpty:      'Gösterilecek kayıt yok',
            infoFiltered:   '(toplam _MAX_ kayıt içinden filtrelendi)',
            lengthMenu:     'Sayfada _MENU_ kayıt',
            loadingRecords: 'Yükleniyor…',
            processing:     'İşleniyor…',
            zeroRecords:    'Aramanızla eşleşen ' + isim + ' bulunamadı.',
            paginate: { first: 'İlk', last: 'Son', next: 'Sonraki', previous: 'Önceki' },
            aria: {
                sortAscending:  ': artan sırada sıralamak için etkinleştir',
                sortDescending: ': azalan sırada sıralamak için etkinleştir'
            }
        };
    };

    /**
     * Sunucu taraflı bir panel tablosu kurar.
     *
     *   CY.table('#user_table', {
     *       isim: 'kullanıcı',
     *       order: [[0, 'desc']],
     *       ajax: { url: API.list, data: function (d) { d.filter_role = …; } },
     *       columnDefs: [ … ]
     *   });
     *
     * ajax.data ve drawCallback verirseniz ORTAK davranışın YERİNE
     * değil ARDINDAN çalışırlar: CSRF anahtarı ve toplam kayıt sayacı
     * her tabloda kendiliğinden işler.
     */
    CY.table = function (selector, options) {
        options = options || {};

        var isim = options.isim || 'kayıt';
        var ajax = options.ajax || {};

        var sayfaBoyu = CY.pageLength();
        var secenekler = [10, 25, 50, 100];

        // Yöneticinin seçtiği boyut menüde yoksa ekle; aksi halde
        // DataTables "Sayfada — kayıt" gösterip boş bir seçim yapar.
        if (secenekler.indexOf(sayfaBoyu) === -1) {
            secenekler.push(sayfaBoyu);
            secenekler.sort(function (a, b) { return a - b; });
        }

        var ayarlar = $.extend({
            processing: true,
            serverSide: true,
            pageLength: sayfaBoyu,
            lengthMenu: [secenekler, secenekler],
            dom: 'rt<"cy-dt-bottom"<"cy-dt-bottom__left"li>p>'
        }, options);

        delete ayarlar.isim;

        ayarlar.language = $.extend(CY.tableLanguage(isim), options.language || {});

        var ekVeri = ajax.data;

        ayarlar.ajax = $.extend({ type: 'POST' }, ajax, {
            data: function (d) {
                d.csrf_token = CY.token();
                if (ekVeri) { ekVeri(d); }
            },
            error: ajax.error || function (xhr) {
                CY.ajaxError(xhr, 'Kayıtlar yüklenirken bir hata oluştu.');
            }
        });

        var ekCizim = options.drawCallback;

        ayarlar.drawCallback = function (settings) {
            $('#total_records').text(settings.json ? settings.json.recordsTotal : 0);

            if (ekCizim) { ekCizim.call(this, settings); }
        };

        return $(selector).DataTable(ayarlar);
    };

    /* =============================================================
     *  BİLDİRİMLER (Toast)
     * ============================================================= */

    var TOAST_ICONS = {
        success: '<path d="M20 6 9 17l-5-5"/>',
        danger:  '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
        warning: '<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/>',
        info:    '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>'
    };

    /**
     * Sağ üstte geçici bildirim gösterir.
     * @param {string} message Gösterilecek metin
     * @param {string} [type]  success | danger | warning | info
     */
    CY.notify = function (message, type) {
        type = TOAST_ICONS[type] ? type : 'success';

        var $container = $('#cy_toasts');
        if (!$container.length) { return; }

        var $toast = $(
            '<div class="toast cy-toast cy-toast--' + type + '" role="alert" aria-live="assertive" aria-atomic="true">' +
                '<span class="cy-toast__icon">' +
                    '<svg class="cy-icon cy-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                    'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + TOAST_ICONS[type] + '</svg>' +
                '</span>' +
                '<div class="cy-toast__body"></div>' +
                '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Kapat"></button>' +
            '</div>'
        );

        // ÖNEMLİ: .html() değil .text(). Aksi halde mesajdaki HTML
        // çalışır ve XSS açığı oluşurdu.
        $toast.find('.cy-toast__body').text(message);
        $container.append($toast);

        var toast = new bootstrap.Toast($toast[0], { delay: 4500 });

        // Kapanınca DOM'dan tamamen kaldır (bellek sızıntısını önler).
        $toast.on('hidden.bs.toast', function () { $toast.remove(); });
        toast.show();
    };

    /**
     * AJAX hatasını kullanıcıya anlaşılır biçimde bildirir.
     * Oturum düştüyse (401/419) sayfayı yeniler; kullanıcı giriş
     * ekranına düşsün, "hiçbir şey çalışmıyor" hissi oluşmasın.
     */
    CY.ajaxError = function (xhr, fallback) {
        var res = (xhr && xhr.responseJSON) || {};

        /* "expired" bayrağı sunucudan gelir (bkz. ErrorHandler).
         * Yalnızca durum koduna bakmak yetmiyor: 419 kayıtlı bir HTTP
         * kodu olmadığı için Apache onu 403'e/500'e çevirebiliyor ve
         * oturum düşmesi sıradan bir hata gibi görünüyordu. */
        if (res.expired === true || (xhr && (xhr.status === 401 || xhr.status === 419))) {
            CY.notify(res.description || 'Oturumunuz sonlandı. Sayfa yenileniyor…', 'warning');
            window.setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }

        CY.notify(res.description || fallback || 'Bir hata oluştu.', 'danger');
    };

    /* =============================================================
     *  SAYFA HAZIR
     * ============================================================= */
    $(function () {

        /* ---------------------------------------------------------
         *  Tüm AJAX isteklerine CSRF anahtarını başlık olarak ekle.
         *  Böylece her istekte tek tek eklemeyi unutma riski kalkar.
         * ------------------------------------------------------- */
        $.ajaxSetup({
            headers: { 'X-CSRF-Token': CY.token() }
        });

        /* =========================================================
         *  2) SOL MENÜ
         * ---------------------------------------------------------
         *  Aynı düğme iki iş yapar:
         *    - Dar ekran (<992px) : menüyü çekmece olarak aç/kapat
         *    - Geniş ekran        : menüyü ikon moduna daralt/genişlet
         * ======================================================= */
        var $body     = $('body');
        var $backdrop = $('#cy_backdrop');
        var $toggle   = $('#cy_sidebar_toggle');
        var $sidebar  = $('#cy_sidebar');

        function isMobile() {
            return window.matchMedia('(max-width: 991.98px)').matches;
        }

        function openDrawer() {
            $body.addClass('is-sidebar-open');
            $backdrop.prop('hidden', false);
            $toggle.attr('aria-expanded', 'true');
        }

        function closeDrawer() {
            $body.removeClass('is-sidebar-open');
            $backdrop.prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        }

        $toggle.on('click', function () {
            if (isMobile()) {
                $body.hasClass('is-sidebar-open') ? closeDrawer() : openDrawer();
                return;
            }

            // Masaüstü: daraltma tercihini çereze yaz ki sunucu bir
            // sonraki sayfayı doğrudan doğru genişlikte üretsin
            // (sayfa açılırken menünün "zıplamasını" önler).
            var collapsed = !$body.hasClass('is-collapsed');

            $body.toggleClass('is-collapsed', collapsed);
            CY.setCookie('cy_sidebar', collapsed ? 'collapsed' : 'expanded');
        });

        $backdrop.on('click', closeDrawer);

        /* --- Açılır alt menüler (ör. Site Ayarları) ---
         * Menü daraltılmışken (yalnızca ikonlar) alt menüyü göstermenin
         * yeri yok; önce menüyü genişletiyoruz, sonra grubu açıyoruz. */
        $sidebar.on('click', '.cy-nav-parent', function () {
            if (!isMobile() && $body.hasClass('is-collapsed')) {
                $body.removeClass('is-collapsed');
                CY.setCookie('cy_sidebar', 'expanded');
            }

            var $item = $(this).closest('.cy-nav-item');
            var open  = !$item.hasClass('is-open');

            $item.toggleClass('is-open', open);
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });

        // Menüden bir bağlantıya tıklanınca çekmece kapansın.
        $sidebar.on('click', 'a', function () {
            if (isMobile()) { closeDrawer(); }
        });

        // ESC tuşu çekmeceyi kapatır (klavye erişilebilirliği).
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && $body.hasClass('is-sidebar-open')) {
                closeDrawer();
                $toggle.trigger('focus');
            }
        });

        // Ekran büyütülürse açık kalan çekmeceyi temizle.
        $(window).on('resize', function () {
            if (!isMobile() && $body.hasClass('is-sidebar-open')) {
                closeDrawer();
            }
        });

        /* =========================================================
         *  3) TEMA (açık / koyu)
         * ---------------------------------------------------------
         *  Tercih çereze yazılır; sunucu bir sonraki sayfayı doğrudan
         *  doğru temayla üretir. Böylece sayfa açılırken bir an yanlış
         *  temada görünmez ("flash of wrong theme").
         *
         *  Giriş yapmış kullanıcı için tercih AYRICA hesaba kaydedilir
         *  (bkz. data-cy-auth); böylece başka bir cihazdan/tarayıcıdan
         *  giriş yapıldığında da aynı tema uygulanır — yalnızca bu
         *  tarayıcıya özgü bir çerez olarak kalmaz.
         * ======================================================= */
        var $themeToggle  = $('#cy_theme_toggle');
        var isAuthed      = document.body.getAttribute('data-cy-auth') === '1';

        function currentTheme() {
            var attr = document.documentElement.getAttribute('data-cy-theme');
            if (attr) { return attr; }

            // Seçim yapılmamış: işletim sistemi ayarına bak.
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function paintThemeIcon(theme) {
            $('.cy-theme-icon--light').toggleClass('d-none', theme === 'dark');
            $('.cy-theme-icon--dark').toggleClass('d-none', theme !== 'dark');
        }

        paintThemeIcon(currentTheme());

        $themeToggle.on('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-cy-theme', next);
            CY.setCookie('cy_theme', next);
            paintThemeIcon(next);

            if (isAuthed) {
                $.ajax({
                    url: CY.url('api/tema'), method: 'POST', dataType: 'json',
                    data: { tema: next === 'dark' ? 'koyu' : 'acik', csrf_token: CY.token() }
                }).fail(function () {
                    // Sessizce yut: görsel değişim zaten uygulandı, tarayıcı
                    // çerezi yedek olarak kalır. Bir sonraki başarılı istekte
                    // tekrar denenir.
                });
            }
        });

        /* =========================================================
         *  4) SUNUCUDAN GELEN BİLDİRİMLER
         * ---------------------------------------------------------
         *  Flash mesajları <script type="application/json"> içinde
         *  taşınır. Tarayıcı bu etiketi ÇALIŞTIRMAZ, sadece veri
         *  olarak saklar; satır içi script yasağıyla uyumludur.
         * ======================================================= */
        var flashNode = document.getElementById('cy_flash');

        if (flashNode) {
            try {
                var messages = JSON.parse(flashNode.textContent || '[]');

                messages.forEach(function (item, index) {
                    // Mesajları hafif gecikmeli sırala; üst üste binmesinler.
                    window.setTimeout(function () {
                        CY.notify(item.message, item.type);
                    }, index * 220);
                });
            } catch (error) {
                // Bozuk JSON uygulamayı çökertmesin.
            }
        }

        /* =========================================================
         *  5) ORTAK FORM DAVRANIŞLARI
         * ======================================================= */

        /* --- Onay isteyen formlar ---
         * Satır içi onsubmit="return confirm(...)" KULLANILMAZ:
         * İçerik Güvenliği Politikası (CSP) satır içi JavaScript'i
         * yasaklar ve düğme sessizce çalışmaz hale gelirdi. Bunun
         * yerine form'a data-confirm="..." yazılır. */
        $(document).on('submit', 'form[data-confirm]', function (event) {
            if (!window.confirm($(this).data('confirm'))) {
                event.preventDefault();
            }
        });

        /* --- Parolayı göster / gizle ---
         * Olay, sabit bir üst elemana bağlanır ("event delegation");
         * böylece modal içinde SONRADAN oluşan alanlarda da çalışır. */
        $(document).on('click', '.js-toggle-password', function () {
            var $button = $(this);
            var $input  = $button.siblings('input');

            if (!$input.length) { return; }

            var show = $input.attr('type') === 'password';

            $input.attr('type', show ? 'text' : 'password');
            $button.attr('aria-label', show ? 'Parolayı gizle' : 'Parolayı göster');
        });

        /* --- Görsel önizleme ---
         * FileReader dosyayı SUNUCUYA YÜKLEMEDEN tarayıcıda okur.
         * Sonuç "data:image/png;base64,..." biçiminde bir metindir. */
        $(document).on('change', '.js-image-input', function () {
            var input   = this;
            var file    = input.files && input.files[0];
            var $preview     = $($(input).data('preview'));
            var $placeholder = $($(input).data('placeholder'));

            if (!file || !$preview.length) { return; }

            var reader = new FileReader();

            reader.onload = function (event) {
                $preview.attr('src', event.target.result).removeClass('d-none');
                $placeholder.addClass('d-none');
            };

            reader.readAsDataURL(file);
        });

        /* =========================================================
         *  6) ÖN YÜZ ÜST MENÜSÜ
         * ---------------------------------------------------------
         *  Menü yapışkandır (CSS: position: sticky). Sayfanın en
         *  üstündeyken düz durması, kaydırıldığında ise içerikten
         *  ayrıldığının belli olması gerekir; yoksa altındaki kart
         *  menüye yapışık görünür. Gölgeyi CSS tek başına veremez —
         *  "yapıştı mı" bilgisi yalnızca kaydırma konumundan gelir.
         *
         *  Panelde bu menü yoktur; eleman bulunamazsa blok hiç
         *  çalışmaz.
         * ======================================================= */
        var siteNav = document.getElementById('cy_site_nav');

        if (siteNav) {
            var stuck = false;

            var paintNav = function () {
                var now = window.scrollY > 4;

                if (now !== stuck) {
                    stuck = now;
                    siteNav.classList.toggle('is-stuck', stuck);
                }
            };

            paintNav();

            // passive: tarayıcı kaydırmayı beklemeden sürdürebilsin —
            // mobilde akıcılığın farkı buradan gelir.
            window.addEventListener('scroll', paintNav, { passive: true });
        }
    });

    return CY;
})(jQuery);
