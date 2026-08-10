<?php
/**
 * =====================================================================
 *  PARÇA: PWA künyesi (<head> içine)
 * ---------------------------------------------------------------------
 *  "Uygulama Modu" ayarı (Ayarlar → Uygulama) açıkken site telefona
 *  "ana ekrana ekle" ile kurulabilir ve çevrimdışı açılır.
 *
 *  Ayar KAPALIYKEN tek bir etiket bile basılmaz: manifest istenmez,
 *  servis çalışanı kaydedilmez. Böylece özelliği kullanmayan bir
 *  proje onun varlığını hiç hissetmez.
 *
 *  "cy-sw" ETİKETİ İKİ ANLAM TAŞIR ve pwa.js buna bakar:
 *    · dolu → servis çalışanını KAYDET
 *    · yok  → varsa kayıtlı olanı SİL ve önbelleğini temizle
 *  Bu yüzden "Çevrimdışı Çalışma" kapatıldığında etiket basılmaz;
 *  kapatma işlemi ziyaretçinin tarayıcısında da gerçekten uygulanır.
 *
 *  Bu blok panel ve ön yüz düzenlerinde birebir aynıydı; iki yerde
 *  düzeltmemek için buraya alındı. Servis çalışanını kaydeden
 *  pwa.js ise düzenlerin script bölümünde yüklenir.
 * =====================================================================
 */

use App\Core\Setting;
use App\Core\Theme;
use App\Core\Url;

if (!Setting::bool('pwa_aktif', false)) {
    return;
}
?>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e(Theme::brand()) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<?php /* apple-touch-icon BURADA DEĞİL, düzenlerde basılır: favicon ile
         aynı kaynaktan (Setting::faviconUrl) gelmeli ve PWA kapalıyken
         de bulunmalı. Burada da basıldığında iki farklı simge
         tanımlanıyor ve hangisinin kazandığı tarayıcıya kalıyordu. */ ?>
<?php if (Setting::bool('pwa_cevrimdisi', true)): ?>
<meta name="cy-sw" content="<?= e(Url::base() . '/sw.js') ?>">
<?php endif; ?>
