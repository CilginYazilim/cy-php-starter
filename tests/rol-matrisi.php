<?php
/**
 * =====================================================================
 *  ROL MATRİSİ – Her rota için "kim erişebilir?"
 * ---------------------------------------------------------------------
 *  tests/unit.php bu tabloyu routes/web.php ve açık modüllerin rotalarıyla
 *  karşılaştırır. Test KIRILIR eğer:
 *    · yeni bir rota eklenip buraya yazılmadıysa,
 *    · bir rota silinip burada kaldıysa,
 *    · bir rotanın ara katmanı (auth/can:…) değişip erişim bu tablodan
 *      farklılaştıysa — örneğin bir "can:" kuralı yanlışlıkla silinip
 *      üyeye açıldıysa.
 *
 *  Kısaltmalar (bkz. tests/rol-matrisi-hesap.php):
 *      M misafir · U üye · E editör · Y yönetici
 *
 *  Bu tablo yetkiyi TANIMLAMAZ (tanım routes/web.php ve App\Models\Role
 *  içindedir); yalnızca bilinçli bir değişiklik olduğunu kanıtlar.
 *  Yeni rota eklediğinizde satırını buraya siz yazarsınız.
 *
 *  Satır düzeyi kurallar (Örnek Modül'de başkasının kaydı) burada değil,
 *  OrnekPolicy testlerinde ve tests/smoke.php'dedir.
 * =====================================================================
 */

declare(strict_types=1);

return [
    'DELETE api/v1/oturum'                 => 'UEY',
    'DELETE api/v1/oturumlar/{id}'         => 'UEY',
    'GET '                                 => 'MUEY',
    'GET api/v1/ben'                       => 'UEY',
    'GET api/v1/dosyalar'                  => 'UEY',
    'GET api/v1/dosyalar/{ad}'             => 'UEY',
    'GET api/v1/kullanicilar'              => 'Y',
    'GET api/v1/oturumlar'                 => 'UEY',
    'GET api/v1/sayfalar'                  => 'MUEY',
    'GET api/v1/sayfalar/{slug}'           => 'MUEY',
    'GET cevrimdisi'                       => 'MUEY',
    'GET duyurular/iptal'                  => 'MUEY',
    'GET giris'                            => 'M',
    'GET kayit'                            => 'M',
    'GET kayit/dogrula'                    => 'MUEY',
    'GET manifest.webmanifest'             => 'MUEY',
    'GET onizleme/{id}'                    => 'MUEY',
    'GET panel'                            => 'UEY',
    'GET panel/ayarlar'                    => 'Y',
    'GET panel/ayarlar/{grup}'             => 'Y',
    'GET panel/eposta'                     => 'EY',
    'GET panel/hesabim'                    => 'UEY',
    'GET panel/kullanicilar'               => 'Y',
    'GET panel/mesajlar'                   => 'EY',
    'GET panel/ornek'                      => 'UEY',
    'GET panel/ornek/duzenle/{id}'         => 'UEY',
    'GET panel/sayfalar'                   => 'EY',
    'GET panel/sayfalar/yeni'              => 'EY',
    'GET panel/sayfalar/{id}'              => 'EY',
    'GET panel/sayfalar/{id}/onizle'       => 'EY',
    'GET panel/sistem'                     => 'Y',
    'GET parola-sifirla'                   => 'M',
    'GET parolami-unuttum'                 => 'M',
    'GET robots.txt'                       => 'MUEY',
    'GET sitemap.xml'                      => 'MUEY',
    'GET {slug}'                           => 'MUEY',
    'POST api/bildirim/kapat'              => 'UEY',
    'POST api/eposta/alicilar'             => 'Y',
    'POST api/eposta/baglanti'             => 'Y',
    'POST api/eposta/fetch'                => 'EY',
    'POST api/eposta/gonder'               => 'Y',
    'POST api/eposta/isle'                 => 'Y',
    'POST api/eposta/list'                 => 'EY',
    'POST api/eposta/onizle'               => 'Y',
    'POST api/eposta/sil'                  => 'Y',
    'POST api/eposta/sinama'               => 'Y',
    'POST api/eposta/tekrar'               => 'Y',
    'POST api/eposta/temizle'              => 'Y',
    'POST api/iletisim/gonder'             => 'MUEY',
    'POST api/kullanicilar/delete'         => 'Y',
    'POST api/kullanicilar/fetch'          => 'Y',
    'POST api/kullanicilar/list'           => 'Y',
    'POST api/kullanicilar/save'           => 'Y',
    'POST api/kullanicilar/status'         => 'Y',
    'POST api/mesajlar/delete'             => 'EY',
    'POST api/mesajlar/fetch'              => 'EY',
    'POST api/mesajlar/list'               => 'EY',
    'POST api/mesajlar/okundu'             => 'EY',
    'POST api/mesajlar/toplu'              => 'EY',
    'POST api/tema'                        => 'UEY',
    'POST api/v1/oturum'                   => 'MUEY',
    'POST cikis'                           => 'UEY',
    'POST duyurular/iptal'                 => 'MUEY',
    'POST giris'                           => 'M',
    'POST kayit'                           => 'M',
    'POST panel/ayarlar/favicon'           => 'Y',
    'POST panel/ayarlar/favicon-sil'       => 'Y',
    'POST panel/ayarlar/logo'              => 'Y',
    'POST panel/ayarlar/logo-sil'          => 'Y',
    'POST panel/ayarlar/pwa-simge'         => 'Y',
    'POST panel/ayarlar/pwa-simge-sil'     => 'Y',
    'POST panel/ayarlar/{grup}'            => 'Y',
    'POST panel/hesabim/api-anahtari'      => 'EY',
    'POST panel/hesabim/api-anahtari/sil'  => 'EY',
    'POST panel/hesabim/avatar'            => 'UEY',
    'POST panel/hesabim/bildirimler'       => 'UEY',
    'POST panel/hesabim/avatar-sil'        => 'UEY',
    'POST panel/hesabim/cihaz/sil'         => 'UEY',
    'POST panel/hesabim/guncelle'          => 'UEY',
    'POST panel/hesabim/oturumlari-kapat'  => 'UEY',
    'POST panel/hesabim/parola'            => 'UEY',
    'POST panel/hesabim/sil'               => 'UEY',
    'POST panel/ornek/durum/{id}'          => 'UEY',
    'POST panel/ornek/duzenle/{id}'        => 'UEY',
    'POST panel/ornek/kaydet'              => 'UEY',
    'POST panel/ornek/ornek-uret'          => 'Y',
    'POST panel/ornek/sil/{id}'            => 'UEY',
    'POST panel/sayfalar/gorsel'           => 'EY',
    'POST panel/sayfalar/yeni'             => 'EY',
    'POST panel/sayfalar/{id}'             => 'EY',
    'POST panel/sayfalar/{id}/sil'         => 'EY',
    'POST panel/sistem/demo-kaldir'        => 'Y',
    'POST panel/sistem/kurulum-sil'        => 'Y',
    'POST panel/sistem/kuyruk/tekrar'      => 'Y',
    'POST panel/sistem/kuyruk/temizle'     => 'Y',
    'POST panel/sistem/migrate'            => 'Y',
    'POST panel/sistem/modul/{ad}'         => 'Y',
    'POST parola-sifirla'                  => 'M',
    'POST parolami-unuttum'                => 'M',
];
