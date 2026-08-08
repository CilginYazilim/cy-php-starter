<?php
/**
 * =====================================================================
 *  YARDIMCI FONKSİYONLAR – Çılgın Yazılım PHP Başlangıç Şablonu
 *  cilginyazilim.com
 * ---------------------------------------------------------------------
 *  Projeden projeye DEĞİŞMEYEN altyapı fonksiyonları burada durur:
 *  JSON yanıt, CSRF, doğrulama, dosya yükleme, tarih biçimleme.
 *
 *  ► Projeye ÖZEL veritabanı fonksiyonlarınızı en alttaki
 *    "PROJEYE ÖZEL" bölümüne yazın.
 *
 *  TASARIM KARARI: Bu dosya config.php'yi DAHİL ETMEZ.
 *  Veritabanına ihtiyaç duyan fonksiyonlar PDO nesnesini PARAMETRE
 *  olarak alır. Böylece her çağrıda yeni bağlantı açılmaz.
 *  (Bu yaklaşımın adı: Dependency Injection / Bağımlılık Enjeksiyonu)
 * =====================================================================
 */

declare(strict_types=1);


/* =====================================================================
 *  BÖLÜM 1 – ÇIKTI VE YANIT
 * ================================================================== */

/**
 * Metni HTML'e güvenle basmak için kaçışlar (XSS koruması).
 *
 * XSS NEDİR? Kullanıcı bir alana <script>alert(1)</script> yazarsa ve
 * biz bunu ekrana olduğu gibi basarsak tarayıcı bunu KOD olarak
 * çalıştırır. Saldırgan böylece oturum çerezlerini çalabilir.
 *
 * Veritabanından gelen HER değeri bu fonksiyondan geçirin.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSON yanıtı gönderir ve script'i sonlandırır.
 *
 * exit kullanmamızın sebebi: yanıt gönderildikten sonra kodun devam
 * edip ikinci bir JSON basmasını önlemek. İki JSON arka arkaya
 * gelirse JavaScript "Unexpected token" hatası verir.
 *
 * @param array<string,mixed> $payload
 * @param int                 $status  HTTP durum kodu
 */
function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    // JSON_UNESCAPED_UNICODE: Türkçe karakterler ç gibi kodlanmasın.
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Standart BAŞARI yanıtı. */
function json_success(string $description, array $extra = []): void
{
    json_response(array_merge([
        'success'     => true,
        'type'        => 'success',
        'description' => $description,
    ], $extra));
}

/**
 * Standart HATA yanıtı.
 *
 * Kullanılan kodlar: 400 geçersiz istek · 404 bulunamadı ·
 * 419 CSRF · 422 doğrulama hatası · 500 sunucu hatası
 */
function json_error(string $description, int $status = 400, array $extra = []): void
{
    json_response(array_merge([
        'success'     => false,
        'type'        => 'danger',
        'description' => $description,
    ], $extra), $status);
}


/* =====================================================================
 *  BÖLÜM 2 – CSRF KORUMASI
 * =====================================================================
 *  CSRF NEDİR? Siz sitemize giriş yapmışken kötü niyetli başka bir
 *  siteyi ziyaret edersiniz. O site gizlice bizim uç noktamıza istek
 *  gönderir; tarayıcı çerezlerinizi otomatik eklediği için sunucu
 *  bunu SİZİN yaptığınızı sanır.
 *
 *  ÇÖZÜM: Oturuma özel, tahmin edilemez bir anahtar üretip sayfaya
 *  gömeriz. Başka bir site bu anahtarı okuyamaz (same-origin policy),
 *  dolayısıyla geçerli istek üretemez.
 * ================================================================== */

/**
 * Oturuma bağlı CSRF anahtarını döndürür (yoksa üretir).
 *
 * random_bytes(): Kriptografik olarak güvenli rastgele veri.
 * rand() veya mt_rand() KULLANMAYIN — tahmin edilebilirler.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Gelen isteğin CSRF anahtarını doğrular; geçersizse 419 ile durur.
 *
 * hash_equals() NEDEN? Normal "===" ilk farklı karakterde durur.
 * Saldırgan yanıt SÜRESİNİ ölçerek anahtarı karakter karakter tahmin
 * edebilir (timing attack). hash_equals() her zaman aynı sürede çalışır.
 *
 * Veri DEĞİŞTİREN her işlemin başında çağırın.
 */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($token) || $token === ''
        || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {

        json_error('Oturum doğrulaması başarısız. Lütfen sayfayı yenileyin.', 419);
    }
}


/* =====================================================================
 *  BÖLÜM 3 – DOĞRULAMA
 * =====================================================================
 *  ALTIN KURAL: İstemci (JavaScript) tarafındaki doğrulama sadece
 *  KULLANICI DENEYİMİ içindir. Kötü niyetli biri tarayıcıyı hiç
 *  kullanmadan doğrudan sunucuya istek atabilir (curl, Postman...).
 *  Bu yüzden her kontrol SUNUCUDA TEKRAR yapılmalıdır.
 * ================================================================== */

/**
 * Serbest metin alanını temizler ve uzunluğunu doğrular.
 *
 * Dönüş: [temizlenmiş değer, hata mesajı|null]
 * Kullanımı:  [$baslik, $hata] = validate_text($_POST['baslik'] ?? '', 'Başlık');
 *
 * @return array{0:string,1:?string}
 */
function validate_text(?string $value, string $label, ?int $min = null, ?int $max = null): array
{
    /* Varsayılan sınırlar config.php'deki sabitlerden gelir. Ancak bu
     * dosya config.php olmadan da yüklenebilmeli (install.php böyle
     * kullanır), bu yüzden sabit tanımlı değilse makul bir değere
     * düşüyoruz. Parametre olarak açıkça verilirse o kullanılır. */
    $min = $min ?? (defined('TEXT_MIN_LENGTH') ? TEXT_MIN_LENGTH : 2);
    $max = $max ?? (defined('TEXT_MAX_LENGTH') ? TEXT_MAX_LENGTH : 150);

    $value = (string) $value;

    /* GEÇERSİZ UTF-8 KORUMASI — bu kontrolü atlamayın.
     * Aşağıdaki preg_replace deseni /u (Unicode) bayrağını kullanır.
     * Girdi geçerli UTF-8 DEĞİLSE preg_replace null döndürür; null'ı
     * trim()'e verince PHP 8 ölümcül TypeError fırlatır ve uygulama
     * 500 ile çöker (hata ayıklama açıkken yığın izini de sızdırır).
     * Kötü niyetli biri bunu bilerek tetikleyebilir, bu yüzden
     * bozuk baytları burada nazikçe reddediyoruz. */
    if (!mb_check_encoding($value, 'UTF-8')) {
        return ['', $label . ' geçersiz karakterler içeriyor.'];
    }

    // trim(): baştaki/sondaki boşlukları siler.
    // preg_replace('/\s+/u', ' '): aradaki çoklu boşlukları teke indirir.
    // Sondaki /u: desenin UTF-8 (Türkçe karakterli) metinle çalışmasını sağlar.
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));

    if ($value === '') {
        return ['', $label . ' alanı boş bırakılamaz.'];
    }

    // mb_strlen(): çok baytlı karakterleri doğru sayar.
    // strlen("Çılgın") = 8 (yanlış) · mb_strlen("Çılgın") = 6 (doğru)
    $length = mb_strlen($value, 'UTF-8');

    if ($length < $min) {
        return [$value, $label . ' en az ' . $min . ' karakter olmalıdır.'];
    }
    if ($length > $max) {
        return [$value, $label . ' en fazla ' . $max . ' karakter olabilir.'];
    }

    return [$value, null];
}

/**
 * Ad / soyad gibi SADECE HARF içermesi gereken alanlar için.
 *
 * Desen açıklaması:
 *   \p{L}  → herhangi bir dildeki harf (ç, ğ, ş, ü, é, 漢 ...)
 *   \p{M}  → harflere eklenen işaretler (aksan vb.)
 *   . ' -  → nokta, kesme işareti, tire ("Ayşe-Nur", "D'Angelo")
 *
 * @return array{0:string,1:?string}
 */
function validate_name(?string $value, string $label): array
{
    [$value, $error] = validate_text($value, $label);

    if ($error !== null) {
        return [$value, $error];
    }

    if (!preg_match("/^[\p{L}\p{M}\s.'-]+$/u", $value)) {
        return [$value, $label . ' yalnızca harf, boşluk, nokta, kesme işareti ve tire içerebilir.'];
    }

    return [$value, null];
}

/**
 * E-posta adresini doğrular.
 *
 * @return array{0:string,1:?string}
 */
function validate_email(?string $value, string $label = 'E-posta'): array
{
    $value = trim((string) $value);

    if ($value === '') {
        return ['', $label . ' alanı boş bırakılamaz.'];
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return [$value, 'Geçerli bir ' . mb_strtolower($label, 'UTF-8') . ' adresi girin.'];
    }

    return [$value, null];
}

/**
 * POST ile gelen ID'yi güvenle tam sayıya çevirir.
 *
 * filter_input(...FILTER_VALIDATE_INT): "5abc" veya "1 OR 1=1" gibi
 * değerler false döner. Geçersizse null döndürür.
 */
function post_id(string $field = 'id'): ?int
{
    $id = filter_input(INPUT_POST, $field, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return ($id === false || $id === null) ? null : $id;
}


/* =====================================================================
 *  BÖLÜM 4 – DOSYA YÜKLEME
 * =====================================================================
 *  DOSYA YÜKLEME, WEB'İN EN TEHLİKELİ KISMIDIR.
 *  Saldırgan "shell.php" yükleyip sunucunuzu ele geçirebilir.
 *  Üç katmanlı savunma:
 *    1. Dosyanın gerçekten görsel olduğu İÇERİĞİNDEN doğrulanır
 *    2. Yeni dosya adı ve uzantısı BİZ belirleriz (kullanıcı değil)
 *    3. upload/.htaccess ile o klasörde PHP çalıştırma kapatılır
 * ================================================================== */

/**
 * Yüklenen dosyayı doğrular ve upload/ klasörüne taşır.
 *
 * @param array<string,mixed> $file $_FILES['alan_adi'] dizisi
 * @throws RuntimeException Doğrulama veya taşıma başarısız olursa
 * @return string Diskte oluşan yeni dosya adı
 */
function upload_image(array $file): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Geçersiz dosya yükleme isteği.');
    }

    // PHP yükleme sonucunu bir hata koduyla bildirir.
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:   // php.ini limitini aştı
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Dosya boyutu sunucu limitini aşıyor.');
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('Dosya seçilmedi.');
        default:
            throw new RuntimeException('Dosya yüklenirken bir hata oluştu.');
    }

    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException(
            'Görsel boyutu en fazla ' . (int) (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB olabilir.'
        );
    }

    // is_uploaded_file(): Dosyanın gerçekten HTTP yüklemesiyle geldiğini
    // doğrular. Bu kontrol olmazsa saldırgan tmp_name alanına sistem
    // dosyası yazıp onu kopyalatabilir.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Geçersiz dosya kaynağı.');
    }

    // EN KRİTİK KONTROL: getimagesize() dosyanın İÇERİĞİNİ okur.
    // Uzantı ".png" olsa bile içinde PHP kodu varsa buradan geçemez.
    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new RuntimeException('Yüklenen dosya geçerli bir görsel değil.');
    }

    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));

    // image/svg+xml içinde JavaScript barındırabildiği için listede yoktur.
    if (!array_key_exists($mime, ALLOWED_IMAGE_TYPES)) {
        throw new RuntimeException('Yalnızca JPG, PNG, GIF ve WEBP formatları desteklenir.');
    }

    // Uzantıyı kullanıcının dosya adından değil, kendi listemizden alıyoruz.
    $extension = ALLOWED_IMAGE_TYPES[$mime];

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }

    // Rastgele, tahmin edilemez dosya adı: üzerine yazmayı ve
    // dosya adından bilgi sızmasını önler.
    do {
        $newName = bin2hex(random_bytes(16)) . '.' . $extension;
    } while (file_exists(UPLOAD_DIR . $newName));

    // move_uploaded_file(): copy() yerine bunu kullanın, ek güvenlik kontrolü yapar.
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
        throw new RuntimeException('Görsel kaydedilemedi.');
    }

    return $newName;
}

/**
 * upload/ klasöründeki bir dosyayı GÜVENLE siler.
 *
 * PATH TRAVERSAL: Saldırgan dosya adı olarak "../system/config.php"
 * gönderirse kontrolsüz bir unlink() başka dosyaları silebilir.
 * basename() yoldaki klasör bilgisini atar, sadece dosya adını bırakır.
 */
function delete_upload(?string $filename): void
{
    $filename = basename(trim((string) $filename));

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $path = UPLOAD_DIR . $filename;

    if (is_file($path)) {
        @unlink($path);
    }
}


/* =====================================================================
 *  BÖLÜM 5 – GENEL VERİ YARDIMCILARI
 * ================================================================== */

/**
 * LIKE kalıbındaki joker karakterleri etkisizleştirir.
 *
 * SQL'de LIKE için: %  → "sıfır veya daha fazla karakter"
 *                   _  → "tam olarak bir karakter"
 * Kullanıcı arama kutusuna "%" yazarsa TÜM kayıtlar dönerdi.
 *
 * (Prepared statement SQL Injection'ı engeller ama joker karakterlerin
 *  ANLAMINI değiştirmez; bu ayrı bir konudur.)
 */
function escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
}

/**
 * Veritabanı tarihini okunabilir hale getirir (06.01.2025 19:34).
 *
 * DateTimeImmutable, DateTime'a göre daha güvenlidir: üzerinde işlem
 * yapınca orijinal nesneyi değiştirmez, yenisini döndürür.
 */
function format_date(?string $value, string $format = 'd.m.Y H:i'): string
{
    if (empty($value)) {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Exception $e) {
        return (string) $value;
    }
}

/**
 * Bir tablodaki kayıt sayısını döndürür.
 *
 * DİKKAT: $table değeri ASLA kullanıcıdan gelmemelidir; sütun ve tablo
 * adları prepared statement ile bind EDİLEMEZ. Kodun içinde sabit yazın.
 */
function count_rows(PDO $db, string $table): int
{
    return (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}


/* =====================================================================
 *  BÖLÜM 6 – PROJEYE ÖZEL FONKSİYONLAR
 * =====================================================================
 *  ► Kendi sorgularınızı buraya yazın.
 *
 *  Aşağıdaki find_item() örneği, system/ajax.php içindeki hazır CRUD
 *  örneğiyle (handle_save / handle_fetch / handle_delete) birlikte
 *  çalışacak şekilde yazıldı. CRUD örneğini açtığınızda bunu da
 *  yorumdan çıkarın.
 *
 *  function find_item(PDO $db, int $id): ?array
 *  {
 *      // SELECT * yerine sütunları tek tek yazmak iyi bir alışkanlıktır:
 *      // ileride "sifre" gibi bir sütun eklenirse yanlışlıkla sızmaz.
 *      $stmt = $db->prepare(
 *          'SELECT id, title, description, image, created_at FROM items WHERE id = :id LIMIT 1'
 *      );
 *      $stmt->execute([':id' => $id]);
 *
 *      // fetch() kayıt yoksa false döner; biz null'a çeviriyoruz.
 *      return $stmt->fetch() ?: null;
 *  }
 * ================================================================== */
