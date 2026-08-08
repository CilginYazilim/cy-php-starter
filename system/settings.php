<?php
/**
 * =====================================================================
 *  AYAR YÖNETİMİ
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  "ayarlar" tablosunu okur/yazar.
 *
 *  KULLANIMI (en sık ihtiyaç duyacağınız fonksiyonlar):
 *      echo setting('site_adi');                    // oku
 *      if (setting_bool('sistem_bakim_modu')) {}    // aç/kapa oku
 *      settings_save($db, ['site_adi' => 'Yeni']);  // yaz
 *
 *  PERFORMANS: Ayarlar sayfa başına SADECE BİR KEZ, tek sorguyla
 *  okunur ve bellekte tutulur. setting() fonksiyonunu yüz kere
 *  çağırsanız da veritabanına bir kez gidilir.
 *
 *  Önbellek config.php tarafından açılışta doldurulur:
 *      settings_load($db);
 * =====================================================================
 */

declare(strict_types=1);

/**
 * Önbellek tutucusu.
 *
 * PHP'de bir fonksiyonun "static" değişkenine dışarıdan erişilemez.
 * Bu yüzden hem okuma hem yazma hem temizleme işini TEK bir
 * fonksiyonda topluyoruz; diğer tüm fonksiyonlar bunu kullanır.
 * Böylece önbellek gerçekten temizlenebilir hale gelir.
 *
 * @param array<string,string>|null $set   Doldurmak için dizi
 * @param bool                      $clear true ise önbelleği sıfırlar
 * @return array<string,string>|null       Doldurulmamışsa null
 */
function settings_cache(?array $set = null, bool $clear = false): ?array
{
    static $cache = null;

    if ($clear) {
        $cache = null;
        return null;
    }

    if ($set !== null) {
        $cache = $set;
    }

    return $cache;
}

/**
 * Ayarları veritabanından okuyup önbelleğe alır.
 * config.php açılışta bunu bir kez çağırır.
 *
 * @return array<string,string>
 */
function settings_load(PDO $db): array
{
    try {
        $rows = $db->query('SELECT anahtar, deger FROM ayarlar')->fetchAll();
    } catch (PDOException $e) {
        // Tablo henüz yoksa (kurulum yapılmamış) uygulamayı çökertme.
        return settings_cache([]) ?? [];
    }

    $values = [];
    foreach ($rows as $row) {
        $values[$row['anahtar']] = (string) $row['deger'];
    }

    return settings_cache($values) ?? [];
}

/**
 * Önbelleği temizler. Ayar kaydettikten sonra çağrılır ki
 * aynı istek içinde eski değer okunmasın.
 */
function settings_flush(): void
{
    settings_cache(null, true);
}

/**
 * Tüm ayarları anahtar => değer dizisi olarak döndürür.
 *
 * @return array<string,string>
 */
function settings_all(): array
{
    return settings_cache() ?? [];
}

/**
 * Tek bir ayarı okur.
 *
 * @param string $key     Ayar anahtarı (örn. 'site_adi')
 * @param mixed  $default Ayar yoksa veya boş bırakılmışsa dönecek değer
 */
function setting(string $key, $default = '')
{
    $all = settings_all();

    if (!array_key_exists($key, $all) || $all[$key] === '') {
        return $default;
    }

    return $all[$key];
}

/**
 * "onay" tipindeki ayarları mantıksal (true/false) olarak okur.
 * Veritabanında "1" / "0" metni olarak saklandıkları için,
 * niyeti açık yazmak hataya daha kapalıdır.
 */
function setting_bool(string $key, bool $default = false): bool
{
    $all = settings_all();

    if (!array_key_exists($key, $all) || $all[$key] === '') {
        return $default;
    }

    return $all[$key] === '1';
}

/**
 * Tek bir ayarı yazar (yoksa oluşturur).
 *
 * "INSERT ... ON DUPLICATE KEY UPDATE" MySQL'e özgü pratik bir
 * yapıdır: kayıt varsa günceller, yoksa ekler. Tek sorguda hallolur
 * ve iki istek aynı anda gelse bile yarış durumu (race condition)
 * oluşmaz — çünkü "anahtar" sütunu UNIQUE.
 */
function setting_set(PDO $db, string $key, ?string $value): void
{
    $stmt = $db->prepare(
        'INSERT INTO ayarlar (anahtar, deger, etiket) VALUES (:anahtar, :deger, :etiket)
         ON DUPLICATE KEY UPDATE deger = VALUES(deger)'
    );

    $stmt->execute([
        ':anahtar' => $key,
        ':deger'   => $value,
        // Yeni oluşturulursa etiket boş kalmasın (NOT NULL sütun).
        ':etiket'  => $key,
    ]);

    settings_flush();
    settings_load($db);
}

/**
 * Birden fazla ayarı tek seferde yazar.
 *
 * TRANSACTION kullanıyoruz: 10 ayardan 7'si yazılıp 8.'de hata
 * olursa, yarım kalmış bir durum bırakmak yerine hepsini geri alır.
 * (InnoDB bunu destekler, MyISAM desteklemez — şemamızın InnoDB
 *  olmasının sebeplerinden biri de budur.)
 *
 * duzenlenebilir = 0 olan ayarlar bilerek atlanır.
 *
 * @param array<string,?string> $values anahtar => değer
 */
function settings_save(PDO $db, array $values): void
{
    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar AND duzenlenebilir = 1'
        );

        foreach ($values as $key => $value) {
            $stmt->execute([':deger' => $value, ':anahtar' => $key]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    settings_flush();
    settings_load($db);
}

/**
 * Ayarları yönetim panelinde göstermek için gruplara ayırarak,
 * TÜM sütunlarıyla (tip, etiket, açıklama...) döndürür.
 *
 * Yönetim panelindeki form bu veriden OTOMATİK üretilir; yeni bir
 * ayar eklemek için tabloya satır eklemeniz yeterlidir.
 *
 * @return array<string,array<int,array<string,mixed>>> grup => satırlar
 */
function settings_grouped(PDO $db): array
{
    $rows = $db->query(
        'SELECT * FROM ayarlar ORDER BY grup ASC, sira ASC, id ASC'
    )->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['grup']][] = $row;
    }

    return $grouped;
}

/**
 * Grup anahtarlarının Türkçe başlıkları.
 * Yeni bir grup eklerseniz buraya da bir satır ekleyin;
 * eklemezseniz grup anahtarı olduğu gibi gösterilir.
 */
function settings_group_labels(): array
{
    return [
        'genel'    => 'Genel',
        'iletisim' => 'İletişim',
        'sosyal'   => 'Sosyal Medya',
        'seo'      => 'SEO',
        'sistem'   => 'Sistem',
    ];
}

/**
 * Site logosunun tarayıcıdan erişilebilir adresini döndürür.
 * Ayarlarda logo tanımlı değilse şablonun varsayılan logosunu verir.
 *
 * @param string $basePath Alt klasörden çağrılıyorsa '../' gibi bir önek
 */
function site_logo_url(string $basePath = ''): string
{
    $logo = (string) setting('site_logo', '');

    if ($logo !== '') {
        return $basePath . UPLOAD_URL . rawurlencode($logo);
    }

    return $basePath . 'assets/images/logo.png';
}
