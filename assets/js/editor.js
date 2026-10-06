/* ==================================================================
 *  ZENGİN METİN EDİTÖRÜ
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  NEDEN HAZIR BİR EDİTÖR KULLANMIYORUZ?
 *  TinyMCE/CKEditor/Quill'in en küçüğü bile birkaç yüz kilobayt ve
 *  çoğu örnekte CDN'den gelir. Bu proje "sıfır bağımlılık, internet
 *  olmadan da eksiksiz çalışır" diyor; bir içerik sayfası için
 *  gereken biçimlendirme ise başlık, kalın/italik, liste, bağlantı
 *  ve alıntıdan ibaret.
 *
 *  NASIL ÇALIŞIR?
 *  Görünen alan contenteditable bir div'dir. Form gönderilirken
 *  içeriğin HTML'i gizli bir <textarea>'ya kopyalanır — sunucuya
 *  giden budur. JavaScript hiç çalışmazsa textarea'daki MEVCUT
 *  içerik olduğu gibi gönderilir; bozuk bir editör yüzünden sayfa
 *  boşalmaz.
 *
 *  GÜVENLİK: Buradaki hiçbir şey güvenlik önlemi DEĞİLDİR. Tarayıcı
 *  tarafında üretilen HTML'e asla güvenilmez; süzme işi sunucuda,
 *  App\Core\Html::sanitize() içinde yapılır.
 * ================================================================== */

/* global jQuery, CY */
jQuery(function ($) {
    'use strict';

    var $editors = $('[data-cy-editor]');

    if (!$editors.length) { return; }

    /* Araç çubuğu tanımı. "cmd" değerleri document.execCommand
     * komutlarıdır: eski bir API'dir ama contenteditable için tüm
     * tarayıcılarda hâlâ tek pratik yoldur ve yerine geçen bir
     * standart henüz yoktur. */
    var TOOLS = [
        { cmd: 'formatBlock', value: 'h2', text: 'H2', title: 'Başlık 2' },
        { cmd: 'formatBlock', value: 'h3', text: 'H3', title: 'Başlık 3' },
        { cmd: 'formatBlock', value: 'p',  text: '¶',  title: 'Normal paragraf' },
        { sep: true },
        { cmd: 'bold',      text: 'B', title: 'Kalın (Ctrl+B)', style: 'font-weight:800' },
        { cmd: 'italic',    text: 'I', title: 'İtalik (Ctrl+I)', style: 'font-style:italic' },
        { cmd: 'underline', text: 'U', title: 'Altı çizili (Ctrl+U)', style: 'text-decoration:underline' },
        { cmd: 'strikeThrough', text: 'S', title: 'Üstü çizili', style: 'text-decoration:line-through' },
        { sep: true },
        { cmd: 'insertUnorderedList', text: '•—', title: 'Madde listesi' },
        { cmd: 'insertOrderedList',   text: '1.', title: 'Numaralı liste' },
        { cmd: 'formatBlock', value: 'blockquote', text: '❝', title: 'Alıntı' },
        { sep: true },
        { action: 'link',   text: '🔗', title: 'Bağlantı ekle' },
        { action: 'unlink', text: '⛓', title: 'Bağlantıyı kaldır' },
        { action: 'upload', text: '🖼', title: 'Görsel yükle', upload: true },
        { action: 'image',  text: '🌐', title: 'Görsel ekle (bu sitedeki adres ile)' },
        { sep: true },
        { cmd: 'removeFormat', text: '✕', title: 'Biçimi temizle' }
    ];

    $editors.each(function () {
        var $editor  = $(this);
        var $area    = $editor.find('.cy-editor__area');
        var $toolbar = $editor.find('.cy-editor__toolbar');
        var $count   = $editor.find('[data-cy-editor-count]');
        var $target  = $('#' + $editor.data('target'));
        var yukleme  = $editor.data('upload-url') || '';

        if (!$area.length || !$target.length) { return; }

        /* ---------------- Araç çubuğunu kur ---------------- */
        TOOLS.forEach(function (tool) {
            if (tool.sep) {
                $toolbar.append($('<span class="cy-editor__sep" aria-hidden="true"></span>'));
                return;
            }

            // Yükleme adresi verilmemiş editörde "Görsel yükle" düğmesi yok.
            if (tool.upload && !yukleme) { return; }

            var $btn = $('<button type="button" class="cy-editor__btn"></button>')
                .attr('title', tool.title)
                .attr('aria-label', tool.title)
                .text(tool.text);

            if (tool.style) { $btn.attr('style', tool.style); }

            $btn.on('mousedown', function (event) {
                /* mousedown'da engelliyoruz: aksi halde düğmeye
                 * basıldığı an metin seçimi kaybolur ve komut hiçbir
                 * şeye uygulanmaz. */
                event.preventDefault();
            });

            $btn.on('click', function () {
                $area.trigger('focus');
                run(tool);
                sync();
                refreshState();
            });

            $toolbar.append($btn);
        });

        /* ---------------- Komutlar ---------------- */
        function run(tool) {
            if (tool.action === 'link')   { return insertLink(); }
            if (tool.action === 'unlink') { return document.execCommand('unlink', false, null); }
            if (tool.action === 'image')  { return insertImage(); }
            if (tool.action === 'upload') { return uploadImage(); }

            /* formatBlock, Chrome dışında <h2> biçiminde bekleyebilir. */
            var deger = tool.value ? '<' + tool.value + '>' : null;

            document.execCommand(tool.cmd, false, deger);
        }

        function insertLink() {
            var adres = window.prompt('Bağlantı adresi:', 'https://');

            if (!adres) { return; }

            /* "javascript:" adresleri burada da eleniyor. Sunucu
             * zaten süzüyor ama kullanıcı kendi yazdığı bağlantının
             * kaydedilmediğini ancak kaydettikten sonra fark ederdi. */
            if (/^\s*javascript:/i.test(adres)) {
                window.alert('Bu adres türü kullanılamaz.');
                return;
            }

            document.execCommand('createLink', false, adres.trim());
        }

        /* Yalnızca BU sitedeki görseller: başka sitenin görseli CSP'ye
         * takılıp kırık görünür, sunucu da kaydederken siler
         * (bkz. Html::localImage). Kontrol burada erken uyarı içindir. */
        function insertImage() {
            var adres = window.prompt('Bu sitedeki görselin adresi (ör. ' + window.location.origin + '/…/upload/img/sayfa/…):', '');

            if (!adres) { return; }

            var baglanti = document.createElement('a');
            baglanti.href = adres.trim();

            if (baglanti.host !== window.location.host || !/^https?:$/.test(baglanti.protocol)) {
                window.alert('Yalnızca bu sitedeki görseller eklenebilir. Başka bir yerdeki görseli önce indirip "Görsel yükle" ile ekleyin.');
                return;
            }

            document.execCommand('insertImage', false, baglanti.href);
        }

        /* Seçimi sakla / geri yükle: dosya penceresi açılınca editör
         * odağı kaybeder; görsel imlecin olduğu yere eklenmeli. */
        function saklaSecim() {
            var secim = window.getSelection();

            if (!secim || !secim.rangeCount) { return null; }

            var aralik = secim.getRangeAt(0);

            return $area[0].contains(aralik.commonAncestorContainer) ? aralik.cloneRange() : null;
        }

        function geriYukleSecim(aralik) {
            var secim = window.getSelection();

            $area.trigger('focus');

            if (!secim) { return; }

            if (!aralik) {
                aralik = document.createRange();
                aralik.selectNodeContents($area[0]);
                aralik.collapse(false);
            }

            secim.removeAllRanges();
            secim.addRange(aralik);
        }

        function uploadImage() {
            var aralik = saklaSecim();
            var $girdi = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>');

            $girdi.on('change', function () {
                var dosya = this.files && this.files[0];

                $girdi.remove();

                if (!dosya) { return; }

                var veri = new FormData();
                veri.append('gorsel', dosya);

                $editor.addClass('is-busy');

                $.ajax({ url: yukleme, method: 'POST', data: veri, processData: false, contentType: false, dataType: 'json' })
                    .done(function (yanit) {
                        if (!yanit || !yanit.success || !yanit.url) {
                            CY.notify((yanit && yanit.description) || 'Görsel yüklenemedi.', 'danger');
                            return;
                        }

                        geriYukleSecim(aralik);
                        document.execCommand('insertImage', false, yanit.url);
                        sync();
                        CY.notify(yanit.description || 'Görsel eklendi.', 'success');
                    })
                    .fail(function (xhr) { CY.ajaxError(xhr, 'Görsel yüklenemedi.'); })
                    .always(function () { $editor.removeClass('is-busy'); });
            });

            $('body').append($girdi);
            $girdi.trigger('click');
        }

        /* ---------------- Durum ve eşitleme ---------------- */
        function refreshState() {
            $toolbar.find('.cy-editor__btn').each(function (index) {
                var tool = TOOLS.filter(function (t) { return !t.sep; })[index];

                if (!tool || !tool.cmd || tool.cmd === 'formatBlock' || tool.cmd === 'removeFormat') { return; }

                var aktif = false;

                try { aktif = document.queryCommandState(tool.cmd); } catch (e) { aktif = false; }

                $(this).toggleClass('is-active', !!aktif);
            });
        }

        function sync() {
            $target.val($area.html());

            var metin = $.trim($area.text());
            var adet  = metin === '' ? 0 : metin.split(/\s+/).length;

            $count.text(adet + ' kelime');
        }

        /* ---------------- Olaylar ---------------- */
        $area.on('input keyup', function () { sync(); refreshState(); });
        $area.on('mouseup', refreshState);
        $area.on('focus', function () { $editor.addClass('is-focused'); });
        $area.on('blur',  function () { $editor.removeClass('is-focused'); sync(); });

        /* YAPIŞTIRMA: Word'den ya da başka bir siteden kopyalanan
         * metin devasa bir stil çöplüğü taşır (<span style="mso-…">).
         * Düz metin olarak yapıştırıp biçimlendirmeyi kullanıcıya
         * bırakmak hem daha temiz hem de sunucudaki süzgecin işini
         * kolaylaştırır. */
        $area.on('paste', function (event) {
            var pano = (event.originalEvent || event).clipboardData;

            if (!pano) { return; }

            event.preventDefault();

            var metin = pano.getData('text/plain');

            document.execCommand('insertText', false, metin);
        });

        /* Form gönderilirken son hâli mutlaka aktar. */
        $area.closest('form').on('submit', sync);

        sync();
    });
});
