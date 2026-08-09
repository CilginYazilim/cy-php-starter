<?php
/**
 * =====================================================================
 *  YÖNETİM PANELİ YARDIMCILARI
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  Bu dosya SADECE yonetim/ klasöründeki sayfalar tarafından yüklenir
 *  (yonetim/_ust.php içinden). Ön yüz sayfaları bu fonksiyonları
 *  yüklemez — gereksiz kod her isteğe yük olmasın diye.
 *
 *  İÇİNDEKİLER:
 *    1) İkonlar        → panel_icon()
 *    2) Menü tanımı    → panel_menu()
 *    3) Özet sorguları → panel_stats(), panel_daily_counts() ...
 *    4) Biçimleme      → time_ago(), format_bytes()
 * =====================================================================
 */

declare(strict_types=1);


/* =====================================================================
 *  1) İKONLAR
 * =====================================================================
 *  NEDEN İKON KÜTÜPHANESİ (FontAwesome vb.) YOK?
 *  Şablonun hiçbir dış bağımlılığı olmasın istiyoruz: internet
 *  olmadan da, CDN engelli bir sunucuda da panel eksiksiz açılmalı.
 *  Bu yüzden ihtiyaç duyulan ~25 ikon SVG olarak burada duruyor.
 *
 *  Hepsi "currentColor" kullanır: ikonun rengi, bulunduğu yerin
 *  yazı rengine göre kendiliğinden değişir — koyu temada da doğru
 *  görünmesinin sebebi budur.
 *
 *  YENİ İKON EKLEMEK: Aşağıdaki diziye 24x24 viewBox'lı bir SVG
 *  gövdesi ekleyin (dış <svg> etiketini YAZMAYIN, o otomatik eklenir).
 * ------------------------------------------------------------------ */

/**
 * Adı verilen ikonu <svg> olarak döndürür.
 *
 * @param string $name  İkon anahtarı (bilinmeyen ad boş dize döndürür)
 * @param string $class Ek CSS sınıfı
 */
function panel_icon(string $name, string $class = ''): string
{
    static $icons = [
        // --- Gezinme ---
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'globe'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'menu'      => '<path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/>',

        // --- Eylemler ---
        'plus'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit'    => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
        'trash'   => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'x'       => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'search'  => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'save'    => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'arrow'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'filter'  => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',

        // --- Durum / bilgi ---
        'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.6-4.6a2 2 0 0 0-2.8 0L3 21"/>',
        'moon'     => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.3 17.7-1.4 1.4"/><path d="m19.1 4.9-1.4 1.4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'server'   => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01"/><path d="M6 17h.01"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';

    /* aria-hidden + focusable="false": ikonlar SÜSTÜR. Ekran okuyucu
     * bunları okumamalı, klavye ile üzerlerine gelinmemelidir; anlam
     * her zaman yanlarındaki metinde bulunur. */
    return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
}


/* =====================================================================
 *  2) MENÜ TANIMI
 * =====================================================================
 *  Panelin sol menüsü TEK yerden yönetilir. Yeni bir sayfa eklemek
 *  için buraya bir satır eklemeniz yeterlidir.
 *
 *  Bir öğenin alanları:
 *    anahtar → sayfanın $aktifMenu değeriyle eşleşir (vurgulama)
 *    baslik  → menüde görünen ad
 *    url     → yonetim/ klasörüne göre göreli adres
 *    ikon    → panel_icon() anahtarı
 *    rol     → bu öğeyi GÖREBİLMEK için gereken en az rol
 *    rozet   → 'mesaj' verilirse okunmamış mesaj sayısı gösterilir
 *
 *  'baslik' => null olan satır bir BÖLÜM BAŞLIĞIDIR (tıklanamaz).
 * ------------------------------------------------------------------ */
function panel_menu(): array
{
    return [
        ['tip' => 'baslik', 'metin' => 'Genel', 'rol' => 'editor'],
        ['tip' => 'link', 'anahtar' => 'ozet', 'baslik' => 'Kontrol Paneli', 'url' => 'index.php', 'ikon' => 'dashboard', 'rol' => 'editor'],

        ['tip' => 'baslik', 'metin' => 'Yönetim', 'rol' => 'admin'],
        ['tip' => 'link', 'anahtar' => 'kullanicilar', 'baslik' => 'Kullanıcılar', 'url' => 'kullanicilar.php', 'ikon' => 'users', 'rol' => 'admin'],
        ['tip' => 'link', 'anahtar' => 'mesajlar', 'baslik' => 'Mesajlar', 'url' => 'mesajlar.php', 'ikon' => 'mail', 'rol' => 'admin', 'rozet' => 'mesaj'],
        ['tip' => 'link', 'anahtar' => 'ayarlar', 'baslik' => 'Site Ayarları', 'url' => 'ayarlar.php', 'ikon' => 'settings', 'rol' => 'admin'],
        ['tip' => 'link', 'anahtar' => 'sistem', 'baslik' => 'Sistem Bilgisi', 'url' => 'sistem.php', 'ikon' => 'server', 'rol' => 'admin'],

        ['tip' => 'baslik', 'metin' => 'Hesabım', 'rol' => 'editor'],
        ['tip' => 'link', 'anahtar' => 'profil', 'baslik' => 'Profilim', 'url' => 'profil.php', 'ikon' => 'user', 'rol' => 'editor'],
        ['tip' => 'link', 'anahtar' => 'site', 'baslik' => 'Siteyi Görüntüle', 'url' => '../index.php', 'ikon' => 'globe', 'rol' => 'editor'],
    ];
}


/* =====================================================================
 *  3) ÖZET SORGULARI
 * ================================================================== */

/**
 * Bir tablonun var olup olmadığını söyler.
 *
 * NEDEN GEREKLİ? Şablonu kendi projesine uyarlayan biri "mesajlar"
 * tablosunu silmiş olabilir. Panel bu yüzden çökmemeli; ilgili
 * kutuyu göstermeyip devam etmelidir.
 */
function table_exists(PDO $db, string $table): bool
{
    // Sonucu istek başına önbellekle: aynı sayfada defalarca sorulur.
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $stmt = $db->prepare('SHOW TABLES LIKE :t');
        $stmt->execute([':t' => $table]);
        return $cache[$table] = ($stmt->fetchColumn() !== false);
    } catch (PDOException $e) {
        return $cache[$table] = false;
    }
}

/** Okunmamış mesaj sayısı (tablo yoksa 0). */
function unread_message_count(PDO $db): int
{
    if (!table_exists($db, 'mesajlar')) {
        return 0;
    }

    return (int) $db->query('SELECT COUNT(*) FROM mesajlar WHERE okundu = 0')->fetchColumn();
}

/**
 * Kontrol panelindeki kutuların beslendiği tek sorgu paketi.
 *
 * @return array<string,int>
 */
function panel_stats(PDO $db): array
{
    /* Tek sorguda birden çok sayaç: COUNT(*) yerine SUM(kosul)
     * kullanıyoruz. MySQL'de karşılaştırma 1/0 döndürdüğü için
     * SUM() doğrudan "kaç tanesi bu koşulu sağlıyor" verir. */
    $kullanici = $db->query(
        "SELECT
            COUNT(*) AS toplam,
            SUM(rol = 'admin')   AS yonetici,
            SUM(rol = 'editor')  AS editor,
            SUM(durum = 'aktif') AS aktif,
            SUM(durum <> 'aktif') AS pasif,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS yeni_30,
            SUM(son_giris >= DATE_SUB(NOW(), INTERVAL 7 DAY))   AS aktif_7
         FROM kullanicilar"
    )->fetch() ?: [];

    $mesaj = ['toplam' => 0, 'okunmamis' => 0, 'bugun' => 0];

    if (table_exists($db, 'mesajlar')) {
        $mesaj = $db->query(
            'SELECT
                COUNT(*) AS toplam,
                SUM(okundu = 0) AS okunmamis,
                SUM(DATE(created_at) = CURDATE()) AS bugun
             FROM mesajlar'
        )->fetch() ?: $mesaj;
    }

    return [
        'kullanici_toplam'   => (int) ($kullanici['toplam'] ?? 0),
        'kullanici_yonetici' => (int) ($kullanici['yonetici'] ?? 0),
        'kullanici_editor'   => (int) ($kullanici['editor'] ?? 0),
        'kullanici_aktif'    => (int) ($kullanici['aktif'] ?? 0),
        'kullanici_pasif'    => (int) ($kullanici['pasif'] ?? 0),
        'kullanici_yeni_30'  => (int) ($kullanici['yeni_30'] ?? 0),
        'kullanici_aktif_7'  => (int) ($kullanici['aktif_7'] ?? 0),
        'mesaj_toplam'       => (int) ($mesaj['toplam'] ?? 0),
        'mesaj_okunmamis'    => (int) ($mesaj['okunmamis'] ?? 0),
        'mesaj_bugun'        => (int) ($mesaj['bugun'] ?? 0),
        'ayar_toplam'        => count_rows($db, 'ayarlar'),
    ];
}

/**
 * Son N günün günlük kayıt sayısını döndürür (grafik için).
 *
 * BOŞ GÜNLER: Veritabanı sadece kayıt OLAN günleri döndürür. Grafiğin
 * doğru olması için önce tüm günleri 0 ile dolduruyoruz, sonra gelen
 * sonuçları üzerine yazıyoruz. Aksi halde "kayıt olmayan gün" hiç
 * çizilmez ve grafik yalan söyler.
 *
 * @param string $table  SABİT yazılmalı — kullanıcıdan gelmemeli!
 * @return array<string,int> 'Y-m-d' => adet
 */
function panel_daily_counts(PDO $db, string $table, int $days = 14, string $column = 'created_at'): array
{
    // Beyaz liste: tablo ve sütun adları prepared statement ile bind edilemez.
    $allowed = [
        'kullanicilar' => ['created_at', 'son_giris'],
        'mesajlar'     => ['created_at'],
    ];

    if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
        return [];
    }
    if (!table_exists($db, $table)) {
        return [];
    }

    $days = max(1, min($days, 90));

    $seri = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $seri[date('Y-m-d', strtotime('-' . $i . ' day'))] = 0;
    }

    $stmt = $db->prepare(
        'SELECT DATE(`' . $column . '`) AS gun, COUNT(*) AS adet
           FROM `' . $table . '`
          WHERE `' . $column . '` >= DATE_SUB(CURDATE(), INTERVAL :gun DAY)
          GROUP BY gun'
    );
    $stmt->execute([':gun' => $days - 1]);

    foreach ($stmt->fetchAll() as $satir) {
        $gun = (string) $satir['gun'];
        if (array_key_exists($gun, $seri)) {
            $seri[$gun] = (int) $satir['adet'];
        }
    }

    return $seri;
}


/* =====================================================================
 *  4) TEK SEFERLİK BİLDİRİM (Flash Message)
 * =====================================================================
 *  NEDEN GEREKLİ? Form kaydedildikten sonra sayfayı OLDUĞU GİBİ
 *  yeniden çizmek iki soruna yol açar:
 *    1. Kullanıcı F5'e basarsa form TEKRAR gönderilir (çift kayıt).
 *    2. Sayfanın üst kısmı (ad, avatar) eski veriyle çizilmiş kalır.
 *
 *  ÇÖZÜM "POST → Redirect → GET" kalıbıdır: kaydettikten sonra
 *  yönlendiririz. Ama o sırada "kaydedildi" mesajı kaybolur — işte
 *  bu iki fonksiyon mesajı oturumda tek seferliğine taşır.
 * ------------------------------------------------------------------ */

/** Bir sonraki sayfada gösterilecek bildirimi saklar. */
function flash_set(string $tur, string $metin): void
{
    $_SESSION['panel_flash'] = ['tur' => $tur, 'metin' => $metin];
}

/**
 * Bekleyen bildirimi döndürür ve SİLER (bir daha gösterilmesin).
 *
 * @return array{tur:string,metin:string}|null
 */
function flash_get(): ?array
{
    if (empty($_SESSION['panel_flash'])) {
        return null;
    }

    $flash = $_SESSION['panel_flash'];
    unset($_SESSION['panel_flash']);

    return is_array($flash) ? $flash : null;
}


/* =====================================================================
 *  5) BİÇİMLEME
 * ================================================================== */

/**
 * Tarihi "3 saat önce" gibi okunabilir hale getirir.
 *
 * Kullanıcı "06.01.2025 19:34"ten çok "2 saat önce" ifadesini hızlı
 * kavrar. Kesin tarihi title özniteliğinde vermek iyi bir dengedir.
 */
function time_ago(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    try {
        $an   = new DateTimeImmutable($date);
        $simdi = new DateTimeImmutable('now');
    } catch (Exception $e) {
        return (string) $date;
    }

    $fark = $simdi->getTimestamp() - $an->getTimestamp();

    // Gelecek tarih (sunucu saati kaymış olabilir): tarihi olduğu gibi ver.
    if ($fark < 0) {
        return format_date($date);
    }

    if ($fark < 60)     { return 'az önce'; }
    if ($fark < 3600)   { return intdiv($fark, 60) . ' dakika önce'; }
    if ($fark < 86400)  { return intdiv($fark, 3600) . ' saat önce'; }
    if ($fark < 604800) { return intdiv($fark, 86400) . ' gün önce'; }

    return format_date($date, 'd.m.Y');
}

/** Bayt değerini "2.4 MB" gibi okunabilir hale getirir. */
function format_bytes(float $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * php.ini'deki "8M", "512K" gibi kısaltmaları bayta çevirir.
 * Sistem bilgisi sayfasında yükleme limitlerini göstermek için.
 */
function ini_bytes(string $value): float
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $unit   = strtolower($value[strlen($value) - 1]);
    $number = (float) $value;

    return match ($unit) {
        'g'     => $number * 1024 * 1024 * 1024,
        'm'     => $number * 1024 * 1024,
        'k'     => $number * 1024,
        default => $number,
    };
}
