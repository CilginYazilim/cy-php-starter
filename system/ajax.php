<?php
/**
 * =====================================================================
 *  AJAX UÇ NOKTASI (Endpoint) – Çılgın Yazılım PHP Başlangıç Şablonu
 *  cilginyazilim.com
 * ---------------------------------------------------------------------
 *  Bu dosya HTML üretmez; SADECE JSON döndürür.
 *  index.php'deki JavaScript buraya POST atar, gelen JSON'a bakarak
 *  ekranı günceller.
 *
 *  NEDEN TEK DOSYA?
 *  Her işlem için ayrı dosya (add.php, delete.php...) açmak yerine tek
 *  giriş noktası kullanmak, güvenlik kontrollerini (CSRF, POST
 *  zorunluluğu, hata yakalama) TEK YERDE toplamayı sağlar. Böylece bir
 *  kontrolü yanlışlıkla bir dosyada unutma riski kalmaz.
 *
 *  ► YENİ İŞLEM EKLEMEK İÇİN:
 *    1. Aşağıdaki switch bloğuna bir "case" ekleyin
 *    2. Karşılık gelen handle_xxx() fonksiyonunu yazın
 *    3. Veri DEĞİŞTİREN işlemlerde ilk satır require_csrf() olsun
 *
 *  ► TAM CRUD LAZIMSA (ekle/düzenle/getir/sil + DataTables listesi):
 *    Dosyanın en altında, yorum içinde HAZIR ve TEST EDİLMİŞ bir örnek
 *    var (handle_list/handle_save/handle_fetch/handle_delete). Yorumu
 *    kaldırıp "items" tablo adını kendinize göre değiştirmeniz yeterli.
 *    Ayrıntılı adımlar: README.md → "Hazır CRUD örneğini açmak"
 * =====================================================================
 */

declare(strict_types=1);

// config.php; function.php, settings.php ve auth.php'yi de yükler.
require __DIR__ . '/config.php';

/* ---------------------------------------------------------------------
 *  GÜVENLİK KONTROLÜ 1: Sadece POST kabul edilir.
 * ---------------------------------------------------------------------
 *  Veri değiştiren işlemler asla GET ile yapılmamalıdır. Aksi halde
 *  <img src="ajax.php?action=delete&id=5"> gibi basit bir etiket bile
 *  kayıt silebilirdi.
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Yalnızca POST istekleri kabul edilir.', 405);
}

// İstenen işlemi oku. ?? operatörü: "solundaki yoksa sağdakini kullan".
$action = isset($_POST['action']) ? strtolower(trim((string) $_POST['action'])) : '';

/* ---------------------------------------------------------------------
 *  YÖNLENDİRİCİ (Router)
 * ---------------------------------------------------------------------
 *  try/catch: İçeride NEREDE hata olursa olsun buraya düşer. Böylece
 *  kullanıcı çirkin bir PHP hata sayfası yerine düzgün bir JSON hata
 *  mesajı görür ve JavaScript bunu işleyebilir.
 * ------------------------------------------------------------------ */
try {
    switch ($action) {

        // Şablonun çalıştığını doğrulamak için hazır gelen örnek işlem.
        // Kendi işlemlerinizi eklemeye başlayınca bunu silebilirsiniz.
        case 'ping':
            handle_ping($db);
            break;

        /* --- Kullanıcı yönetimi (yonetim/kullanicilar.php kullanır) ---
         * Hepsi require_role_json('admin') ile korunur. */
        case 'kullanici_list':
            handle_kullanici_list($db);
            break;

        case 'kullanici_getir':
            handle_kullanici_getir($db);
            break;

        case 'kullanici_ekle':
        case 'kullanici_guncelle':
            handle_kullanici_kaydet($db, $action);
            break;

        case 'kullanici_sil':
            handle_kullanici_sil($db);
            break;

        /* --- İletişim formu (iletisim.php kullanır) ---
         * Bu uç BİLEREK herkese açıktır; giriş gerektirmez. */
        case 'mesaj_gonder':
            handle_mesaj_gonder($db);
            break;

        /* ---------------------------------------------------------
         *  ► KENDİ İŞLEMLERİNİZİ BURAYA EKLEYİN
         *
         *  case 'list':   handle_list($db);   break;
         *  case 'add':
         *  case 'edit':   handle_save($db, $action); break;
         *  case 'fetch':  handle_fetch($db);  break;
         *  case 'delete': handle_delete($db); break;
         * --------------------------------------------------------- */

        default:
            json_error('Bilinmeyen işlem: ' . ($action === '' ? '(boş)' : $action), 400);
    }
} catch (PDOException $e) {
    // Veritabanı kaynaklı hatalar (bağlantı koptu, sorgu hatalı...)
    error_log('[' . APP_NAME . '] Veritabani hatasi: ' . $e->getMessage());

    // GÜVENLİK: Canlıda hata detayı kullanıcıya GÖSTERİLMEZ;
    // tablo/sütun isimleri saldırgana bilgi verir.
    json_error(
        APP_DEBUG ? 'Veritabanı hatası: ' . $e->getMessage()
                  : 'Beklenmeyen bir veritabanı hatası oluştu.',
        500
    );
} catch (Throwable $e) {
    // Throwable: PHP 7+ ile tüm hata ve istisnaların ortak atası.
    // Yani buraya akla gelen HER hata düşer.
    error_log('[' . APP_NAME . '] Hata: ' . $e->getMessage());

    json_error(
        APP_DEBUG ? 'Hata: ' . $e->getMessage() : 'Beklenmeyen bir hata oluştu.',
        500
    );
}


/* =====================================================================
 *  ÖRNEK İŞLEM – bağlantı testi
 * =====================================================================
 *  Kurulumun doğru olduğunu tek tıkla doğrulamanızı sağlar:
 *  CSRF çalışıyor mu, veritabanına bağlanılıyor mu?
 * ------------------------------------------------------------------ */
function handle_ping(PDO $db): void
{
    require_csrf();

    // Veritabanı gerçekten cevap veriyor mu?
    $now = $db->query('SELECT NOW()')->fetchColumn();

    json_success('Bağlantı başarılı. Şablon çalışıyor.', [
        'php'       => PHP_VERSION,
        'database'  => DB_NAME,
        'server_at' => format_date((string) $now, 'd.m.Y H:i:s'),
    ]);
}


/* =====================================================================
 *  İLETİŞİM FORMU – MESAJ GÖNDER
 * =====================================================================
 *  iletisim.php sayfasındaki formu karşılar ve mesajı "mesajlar"
 *  tablosuna yazar.
 *
 *  BU UÇ HERKESE AÇIKTIR — giriş istemez. Açık uçlarda üç şeye
 *  ayrıca dikkat etmek gerekir:
 *
 *    1. SPAM        → bal küpü (honeypot) alanı + hız sınırı
 *    2. AŞIRI VERİ  → alan uzunlukları sunucuda da sınırlanır
 *                     (maxlength yalnızca tarayıcıda geçerlidir,
 *                      istek elle gönderilirse hiçbir işe yaramaz)
 *    3. XSS         → mesaj HAM saklanır, EKRANA BASILIRKEN e() ile
 *                     kaçışlanır. Kaydederken temizlemek yanlıştır;
 *                     veriyi bozar ve tek bir unutulan yer açık bırakır.
 * ------------------------------------------------------------------ */
function handle_mesaj_gonder(PDO $db): void
{
    require_csrf();

    // Yönetici formu kapattıysa uç da kapalı olmalıdır. Sadece sayfada
    // formu gizlemek yetmez; ajax.php doğrudan çağrılabilir.
    if (!setting_bool('sistem_iletisim_formu', true)) {
        json_error('İletişim formu şu anda kapalı.', 403);
    }

    /* --- BAL KÜPÜ (honeypot) ---------------------------------------
     * Ekranda görünmeyen "website" alanı doluysa gönderen bir bottur.
     * Hata DÖNDÜRMÜYORUZ: bot, denemesinin başarısız olduğunu anlarsa
     * formu inceleyip yöntemini değiştirir. Başarılı gibi davranıp
     * mesajı sessizce çöpe atmak daha etkilidir. */
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        json_success('Mesajınız alındı. Teşekkür ederiz.');
    }

    /* --- HIZ SINIRI (rate limit) -----------------------------------
     * Aynı oturumdan 60 saniye içinde ikinci mesaja izin vermiyoruz.
     * Basit ama formu makineli tüfek gibi kullanmayı engeller.
     * (Oturum çerezi olmayan bir istemci bunu aşabilir; ciddi trafikte
     *  IP tabanlı bir sayaç veya CAPTCHA eklemek gerekir.) */
    $sonGonderim = (int) ($_SESSION['son_mesaj_zamani'] ?? 0);
    $bekleme     = 60;

    if ($sonGonderim > 0 && (time() - $sonGonderim) < $bekleme) {
        $kalan = $bekleme - (time() - $sonGonderim);
        json_error('Çok hızlı gönderiyorsunuz. Lütfen ' . $kalan . ' saniye bekleyin.', 429);
    }

    /* --- DOĞRULAMA -------------------------------------------------- */
    $errors = [];

    [$ad, $adHata]         = validate_text($_POST['ad'] ?? '', 'Ad', 2, 150);
    [$eposta, $epostaHata] = validate_email($_POST['eposta'] ?? '', 'E-posta');

    if ($adHata !== null)     { $errors['ad'] = $adHata; }
    if ($epostaHata !== null) { $errors['eposta'] = $epostaHata; }

    // Konu isteğe bağlı; girilmişse uzunluğu sınırlanır.
    $konu = trim((string) ($_POST['konu'] ?? ''));
    if (mb_strlen($konu, 'UTF-8') > 190) {
        $errors['konu'] = 'Konu en fazla 190 karakter olabilir.';
    }

    // Mesajda satır sonlarını KORUMAK istiyoruz, bu yüzden
    // validate_text() kullanmıyoruz (o tüm boşlukları teke indirir).
    $mesaj = trim((string) ($_POST['mesaj'] ?? ''));

    if (!mb_check_encoding($mesaj, 'UTF-8')) {
        $errors['mesaj'] = 'Mesaj geçersiz karakterler içeriyor.';
    } elseif (mb_strlen($mesaj, 'UTF-8') < 10) {
        $errors['mesaj'] = 'Mesajınız en az 10 karakter olmalı.';
    } elseif (mb_strlen($mesaj, 'UTF-8') > 4000) {
        $errors['mesaj'] = 'Mesajınız en fazla 4000 karakter olabilir.';
    }

    if ($errors !== []) {
        // 422 = "Unprocessable Entity": istek anlaşıldı ama içeriği geçersiz.
        json_error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $errors]);
    }

    /* --- KAYDET ----------------------------------------------------- */
    $db->prepare(
        'INSERT INTO mesajlar (ad, eposta, konu, mesaj, kullanici_id, ip, tarayici)
         VALUES (:ad, :eposta, :konu, :mesaj, :kullanici_id, :ip, :tarayici)'
    )->execute([
        ':ad'           => $ad,
        ':eposta'       => $eposta,
        ':konu'         => $konu,
        ':mesaj'        => $mesaj,
        // Giriş yapmış biri gönderdiyse hesabıyla ilişkilendir.
        ':kullanici_id' => auth_id(),
        ':ip'           => client_ip(),
        // User-Agent uzun olabilir; sütun sınırını aşmasın diye kırpıyoruz.
        ':tarayici'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8'),
    ]);

    $_SESSION['son_mesaj_zamani'] = time();

    json_success('Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.');
}


/* =====================================================================
 *  KULLANICI YÖNETİMİ
 * =====================================================================
 *  Bu dört fonksiyon yonetim/kullanicilar.php sayfasını besler.
 *
 *  HEPSİNİN İLK İKİ SATIRI AYNI:
 *      require_csrf();               → sahte istek koruması
 *      require_role_json('admin');   → yetki koruması
 *
 *  Bu ikisini atlamak, panelinizi internete açmakla eşdeğerdir:
 *  ajax.php herkese açık bir adrestir, sadece panel sayfası
 *  korumalı olduğu için buranın da korunduğunu SANMAYIN.
 * ------------------------------------------------------------------ */

/**
 * DataTables için kullanıcı listesi.
 */
function handle_kullanici_list(PDO $db): void
{
    require_csrf();
    require_role_json('admin');

    // Sıralanabilir sütunlar beyaz listesi (SQL Injection koruması):
    // kullanıcı bize sadece bir SAYI gönderir, sütun adını BİZ seçeriz.
    $sortableColumns = [
        0 => 'id',
        1 => 'ad',
        2 => 'eposta',
        3 => 'rol',
        4 => 'durum',
    ];

    $draw   = (int) ($_POST['draw'] ?? 1);
    $start  = max(0, (int) ($_POST['start'] ?? 0));
    $length = (int) ($_POST['length'] ?? 10);
    $search = trim((string) ($_POST['search']['value'] ?? ''));

    $orderColumn = (int) ($_POST['order'][0]['column'] ?? 0);
    $orderDir    = strtolower((string) ($_POST['order'][0]['dir'] ?? 'desc'));

    $orderBy  = $sortableColumns[$orderColumn] ?? 'id';
    $orderDir = ($orderDir === 'asc') ? 'ASC' : 'DESC';

    $where  = '';
    $params = [];

    if ($search !== '') {
        // NOT: EMULATE_PREPARES kapalıyken aynı yer tutucu birden fazla
        // kez kullanılamaz; her biri için ayrı isim veriyoruz.
        $where = ' WHERE ad LIKE :s1 OR soyad LIKE :s2 OR eposta LIKE :s3 OR kullanici_adi LIKE :s4';
        $desen = '%' . escape_like($search) . '%';
        $params = [':s1' => $desen, ':s2' => $desen, ':s3' => $desen, ':s4' => $desen];
    }

    // Filtrelenmiş toplam (sayfalama bunu kullanır).
    $countStmt = $db->prepare('SELECT COUNT(*) FROM kullanicilar' . $where);
    $countStmt->execute($params);
    $filtered = (int) $countStmt->fetchColumn();

    $sql = 'SELECT id, ad, soyad, kullanici_adi, eposta, rol, durum, son_giris
              FROM kullanicilar' . $where
        . sprintf(' ORDER BY `%s` %s', $orderBy, $orderDir);

    if ($length > 0) {
        // LIMIT/OFFSET bind edilemez; %d ile tam sayıya zorluyoruz.
        // min(...,500): length=999999 gönderilip sunucu yorulmasın.
        $sql .= sprintf(' LIMIT %d OFFSET %d', min($length, 500), $start);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rolEtiketleri   = auth_role_labels();
    $durumEtiketleri = auth_status_labels();
    $aktifId         = auth_id();

    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $id      = (int) $row['id'];
        $tamAd   = $row['ad'] . ' ' . $row['soyad'];
        $kendisi = ($id === $aktifId);

        // Durum rozetinin rengi.
        $durumRengi = match ($row['durum']) {
            'aktif'  => 'background:#dcfce7;color:#166534',
            'pasif'  => 'background:#fee2e2;color:#991b1b',
            default  => 'background:#fef3c7;color:#92400e',
        };

        /* Kendi hesabınız için silme butonu ÜRETİLMEZ. Yine de bu
         * sadece arayüz kolaylığıdır — asıl kontrol sunucuda,
         * handle_kullanici_sil() içinde yapılır. */
        $silButonu = $kendisi
            ? '<button type="button" class="cy-btn-icon" disabled title="Kendinizi silemezsiniz">&#128465;</button>'
            : '<button type="button" class="cy-btn-icon cy-btn-icon--delete js-sil" data-id="' . $id . '"'
                . ' data-label="' . e($tamAd) . '" title="Sil">&#128465;</button>';

        $data[] = [
            $id,
            '<span class="cy-name">' . e($tamAd) . '</span>'
                . ' <small class="cy-muted">@' . e((string) $row['kullanici_adi']) . '</small>'
                . ($kendisi ? ' <span class="cy-badge cy-badge--soft" style="font-size:.7rem">siz</span>' : ''),
            e((string) $row['eposta']),
            '<span class="cy-badge cy-badge--soft">'
                . e($rolEtiketleri[$row['rol']] ?? (string) $row['rol']) . '</span>',
            '<span class="cy-badge" style="' . $durumRengi . '">'
                . e($durumEtiketleri[$row['durum']] ?? (string) $row['durum']) . '</span>',
            '<div class="cy-actions">'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--edit js-duzenle" data-id="' . $id . '" title="Düzenle">&#9998;</button>'
                . $silButonu
            . '</div>',
        ];
    }

    json_response([
        'draw'            => $draw,
        'recordsTotal'    => count_rows($db, 'kullanicilar'),
        'recordsFiltered' => $filtered,
        'data'            => $data,
    ]);
}

/**
 * Düzenleme formu için tek kullanıcı getirir.
 *
 * DİKKAT: "sifre" sütunu BİLEREK döndürülmez. Parola özetini
 * tarayıcıya göndermenin hiçbir faydası yoktur, sadece riski vardır.
 */
function handle_kullanici_getir(PDO $db): void
{
    require_csrf();
    require_role_json('admin');

    $id = post_id('id');
    if ($id === null) {
        json_error('Geçersiz kullanıcı numarası.');
    }

    $user = find_user($db, $id);
    if ($user === null) {
        json_error('Kullanıcı bulunamadı.', 404);
    }

    json_response([
        'success'       => true,
        'id'            => (int) $user['id'],
        'ad'            => $user['ad'],
        'soyad'         => $user['soyad'],
        'kullanici_adi' => $user['kullanici_adi'],
        'eposta'        => $user['eposta'],
        'rol'           => $user['rol'],
        'durum'         => $user['durum'],
        'telefon'       => $user['telefon'],
        'hakkinda'      => (string) $user['hakkinda'],
    ]);
}

/**
 * Kullanıcı ekler veya günceller.
 */
function handle_kullanici_kaydet(PDO $db, string $action): void
{
    require_csrf();
    require_role_json('admin');

    $duzenleme = ($action === 'kullanici_guncelle');
    $mevcut    = null;

    if ($duzenleme) {
        $id = post_id('id');
        if ($id === null) {
            json_error('Geçersiz kullanıcı numarası.');
        }

        $mevcut = find_user($db, $id);
        if ($mevcut === null) {
            json_error('Güncellenecek kullanıcı bulunamadı.', 404);
        }
    }

    $errors = [];

    [$ad, $adHata]         = validate_name($_POST['ad'] ?? '', 'Ad');
    [$soyad, $soyadHata]   = validate_name($_POST['soyad'] ?? '', 'Soyad');
    [$kadi, $kadiHata]     = validate_username($_POST['kullanici_adi'] ?? '');
    [$eposta, $epostaHata] = validate_email($_POST['eposta'] ?? '');

    if ($adHata !== null)     { $errors['ad'] = $adHata; }
    if ($soyadHata !== null)  { $errors['soyad'] = $soyadHata; }
    if ($kadiHata !== null)   { $errors['kullanici_adi'] = $kadiHata; }
    if ($epostaHata !== null) { $errors['eposta'] = $epostaHata; }

    // Benzersizlik kontrolü (düzenlemede kendi kaydını hariç tut).
    $haricId = $duzenleme ? (int) $mevcut['id'] : null;

    if (!isset($errors['eposta']) && user_field_taken($db, 'eposta', $eposta, $haricId)) {
        $errors['eposta'] = 'Bu e-posta adresi zaten kayıtlı.';
    }
    if (!isset($errors['kullanici_adi']) && user_field_taken($db, 'kullanici_adi', $kadi, $haricId)) {
        $errors['kullanici_adi'] = 'Bu kullanıcı adı zaten alınmış.';
    }

    // Rol ve durum: beyaz liste dışındaki değerler kabul edilmez.
    $rol   = (string) ($_POST['rol'] ?? 'uye');
    $durum = (string) ($_POST['durum'] ?? 'aktif');

    if (!array_key_exists($rol, auth_role_labels())) {
        $errors['rol'] = 'Geçersiz rol.';
    }
    if (!array_key_exists($durum, auth_status_labels())) {
        $errors['durum'] = 'Geçersiz durum.';
    }

    // Parola: eklemede zorunlu, düzenlemede boş bırakılabilir.
    $sifre = (string) ($_POST['sifre'] ?? '');

    if (!$duzenleme || $sifre !== '') {
        $sifreHata = validate_password($sifre);
        if ($sifreHata !== null) {
            $errors['sifre'] = $sifreHata;
        }
    }

    /* KENDİ KENDİNİ KİLİTLEME KORUMASI
     * Yönetici kendi rolünü düşürür veya hesabını pasife alırsa
     * panele bir daha giremez. Ayrıca sistemdeki SON yöneticiyi
     * yetkisiz bırakmak, kimsenin yönetemediği bir site demektir. */
    if ($duzenleme && (int) $mevcut['id'] === auth_id()) {
        if ($rol !== 'admin') {
            $errors['rol'] = 'Kendi yönetici yetkinizi kaldıramazsınız.';
        }
        if ($durum !== 'aktif') {
            $errors['durum'] = 'Kendi hesabınızı pasife alamazsınız.';
        }
    } elseif ($duzenleme && $mevcut['rol'] === 'admin' && $rol !== 'admin' && admin_count($db) <= 1) {
        $errors['rol'] = 'Sistemdeki son yöneticinin yetkisini kaldıramazsınız.';
    }

    if ($errors !== []) {
        json_error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $errors]);
    }

    $telefon  = trim((string) ($_POST['telefon'] ?? ''));
    $hakkinda = trim((string) ($_POST['hakkinda'] ?? ''));

    if ($duzenleme) {
        $sql = 'UPDATE kullanicilar
                   SET ad = :ad, soyad = :soyad, kullanici_adi = :kadi, eposta = :eposta,
                       rol = :rol, durum = :durum, telefon = :telefon, hakkinda = :hakkinda';
        $params = [
            ':ad'       => $ad,
            ':soyad'    => $soyad,
            ':kadi'     => $kadi,
            ':eposta'   => $eposta,
            ':rol'      => $rol,
            ':durum'    => $durum,
            ':telefon'  => $telefon,
            ':hakkinda' => $hakkinda,
            ':id'       => $mevcut['id'],
        ];

        // Parola sadece doldurulduysa değişir.
        if ($sifre !== '') {
            $sql .= ', sifre = :sifre';
            $params[':sifre'] = hash_password($sifre);
        }

        $sql .= ' WHERE id = :id';

        $db->prepare($sql)->execute($params);

        json_success('Kullanıcı güncellendi.', ['id' => (int) $mevcut['id']]);
    }

    $stmt = $db->prepare(
        'INSERT INTO kullanicilar (ad, soyad, kullanici_adi, eposta, sifre, rol, durum, telefon, hakkinda)
         VALUES (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum, :telefon, :hakkinda)'
    );
    $stmt->execute([
        ':ad'       => $ad,
        ':soyad'    => $soyad,
        ':kadi'     => $kadi,
        ':eposta'   => $eposta,
        // Parolanın kendisi değil, geri döndürülemez özeti saklanır.
        ':sifre'    => hash_password($sifre),
        ':rol'      => $rol,
        ':durum'    => $durum,
        ':telefon'  => $telefon,
        ':hakkinda' => $hakkinda,
    ]);

    json_success('Kullanıcı eklendi.', ['id' => (int) $db->lastInsertId()]);
}

/**
 * Kullanıcı siler.
 */
function handle_kullanici_sil(PDO $db): void
{
    require_csrf();
    require_role_json('admin');

    $id = post_id('id');
    if ($id === null) {
        json_error('Geçersiz kullanıcı numarası.');
    }

    // Kendinizi silemezsiniz (arayüzde buton kapalı ama sunucuda da kontrol şart).
    if ($id === auth_id()) {
        json_error('Kendi hesabınızı silemezsiniz.', 422);
    }

    $user = find_user($db, $id);
    if ($user === null) {
        json_error('Silinecek kullanıcı bulunamadı.', 404);
    }

    // Son yöneticiyi silmek, yönetilemeyen bir site bırakır.
    if ($user['rol'] === 'admin' && admin_count($db) <= 1) {
        json_error('Sistemdeki son yöneticiyi silemezsiniz.', 422);
    }

    $stmt = $db->prepare('DELETE FROM kullanicilar WHERE id = :id');
    $stmt->execute([':id' => $id]);

    // Avatarı varsa diskten de temizle (yetim dosya bırakmamak için).
    if ($user['avatar'] !== '') {
        delete_upload((string) $user['avatar']);
    }

    json_success('Kullanıcı silindi.', ['id' => $id]);
}


/* =====================================================================
 *  ► ŞABLON: LİSTELEME (DataTables server-side)
 * =====================================================================
 *  Aşağıdaki fonksiyonu kopyalayıp tablo/sütun adlarını değiştirerek
 *  kullanabilirsiniz. Yorumu kaldırmayı ve switch'e case eklemeyi
 *  unutmayın.
 *
 *  DataTables sunucudan şu yapıda JSON bekler:
 *    { "draw":1, "recordsTotal":50, "recordsFiltered":12, "data":[[...]] }
 *
 *  recordsTotal ve recordsFiltered'ı doğru vermek ŞARTTIR;
 *  sayfalama butonları bu iki sayıya göre hesaplanır.
 * ---------------------------------------------------------------------

function handle_list(PDO $db): void
{
    // SIRALAMA GÜVENLİĞİ (en kritik kısım!)
    // Sütun adları ve ASC/DESC prepared statement ile bind EDİLEMEZ;
    // SQL'in yapısal parçalarıdır. Kullanıcıdan geleni doğrudan sorguya
    // koymak SQL Injection'a davetiye çıkarır.
    // ÇÖZÜM: Beyaz liste. Kullanıcı bize sadece bir SAYI gönderir;
    // o sayıya karşılık gelen sütun adını BİZ seçeriz.
    $sortableColumns = [
        0 => 'id',
        1 => 'title',
        2 => 'created_at',
    ];

    $draw   = (int) ($_POST['draw'] ?? 1);
    $start  = max(0, (int) ($_POST['start'] ?? 0));
    $length = (int) ($_POST['length'] ?? 10);
    $search = trim((string) ($_POST['search']['value'] ?? ''));

    $orderColumn = (int) ($_POST['order'][0]['column'] ?? 0);
    $orderDir    = strtolower((string) ($_POST['order'][0]['dir'] ?? 'desc'));

    $orderBy  = $sortableColumns[$orderColumn] ?? 'id';
    $orderDir = ($orderDir === 'asc') ? 'ASC' : 'DESC';

    $sql    = 'SELECT id, title, created_at FROM items';
    $params = [];

    if ($search !== '') {
        // NOT: EMULATE_PREPARES kapalıyken aynı isimli yer tutucu iki kez
        // kullanılamaz. Bu, "Invalid parameter number" hatasının sebebidir.
        $sql .= ' WHERE title LIKE :search_title';
        $params[':search_title'] = '%' . escape_like($search) . '%';
    }

    $sql .= sprintf(' ORDER BY `%s` %s', $orderBy, $orderDir);

    // LIMIT/OFFSET de bind edilemez; %d ile biçimlendirdiğimiz için PHP
    // değeri zorla tam sayıya çevirir. min(...,500): length=999999
    // gönderilip sunucunun yorulmasını engeller.
    if ($length > 0) {
        $sql .= sprintf(' LIMIT %d OFFSET %d', min($length, 500), $start);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];

        // e() = htmlspecialchars → XSS koruması.
        // Veritabanındaki bir değerde <script> olsa bile zararsız metne dönüşür.
        $data[] = [
            $id,
            e((string) $row['title']),
            e(format_date($row['created_at'])),
            '<div class="cy-actions">'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--edit js-edit" data-id="' . $id . '">&#9998;</button>'
                . '<button type="button" class="cy-btn-icon cy-btn-icon--delete js-delete" data-id="' . $id . '">&#128465;</button>'
            . '</div>',
        ];
    }

    json_response([
        'draw'            => $draw,
        'recordsTotal'    => count_rows($db, 'items'),
        'recordsFiltered' => count_rows($db, 'items'), // aramada ayrı sorgu yazın!
        'data'            => $data,
    ]);
}


/* =====================================================================
 *  EKLEME / GÜNCELLEME (add + edit ortak)
 * =====================================================================
 *  Aynı fonksiyon hem ekleme hem düzenleme yapar; farkı $action belirler.
 *  Görsel yüklemeyi kullanmıyorsanız $hasNewImage bloğunu silebilirsiniz.
 * ------------------------------------------------------------------

function handle_save(PDO $db, string $action): void
{
    require_csrf();   // ◄── veri değiştiren her işlemin ilk satırı

    $errors = [];

    [$title, $titleError] = validate_text($_POST['title'] ?? '', 'Başlık');
    if ($titleError !== null) {
        $errors['title'] = $titleError;
    }

    // Açıklama zorunlu değil.
    $description = trim((string) ($_POST['description'] ?? ''));

    $isEdit  = ($action === 'edit');
    $current = null;

    if ($isEdit) {
        $id = post_id('id');
        if ($id === null) {
            json_error('Geçersiz kayıt numarası.');
        }

        $current = find_item($db, $id);
        if ($current === null) {
            json_error('Güncellenecek kayıt bulunamadı.', 404);
        }
    }

    // --- Görsel yükleme (opsiyonel alan; kullanmıyorsanız bu bloğu silin) ---
    $hasNewImage = isset($_FILES['image'])
        && is_array($_FILES['image'])
        && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

    $newImage = null;

    if ($hasNewImage && $errors === []) {
        try {
            $newImage = upload_image($_FILES['image']);
        } catch (RuntimeException $e) {
            $errors['image'] = $e->getMessage();
        }
    }

    if ($errors !== []) {
        // Kısmi yükleme olduysa diskte yetim kalmasın.
        if ($newImage !== null) {
            delete_upload($newImage);
        }
        json_error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $errors]);
    }

    if ($isEdit) {
        // Yeni görsel yoksa eskisini koru. ÖNEMLİ: eski dosya adı
        // istemciden değil, veritabanından ($current) okunur —
        // aksi halde saldırgan başka bir dosya adı gönderip sunucudaki
        // rastgele bir dosyayı "değiştirilmiş" gibi gösterebilirdi.
        $image = $newImage ?? (string) $current['image'];

        $stmt = $db->prepare(
            'UPDATE items SET title = :title, description = :description, image = :image WHERE id = :id'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':image'       => $image,
            ':id'          => $current['id'],
        ]);

        if ($newImage !== null && $current['image'] !== '') {
            delete_upload((string) $current['image']);
        }

        json_success('Kayıt güncellendi.', ['id' => (int) $current['id']]);
    }

    $stmt = $db->prepare(
        'INSERT INTO items (title, description, image) VALUES (:title, :description, :image)'
    );
    $stmt->execute([
        ':title'       => $title,
        ':description' => $description,
        ':image'       => $newImage ?? '',
    ]);

    json_success('Kayıt eklendi.', ['id' => (int) $db->lastInsertId()]);
}


/* =====================================================================
 *  TEK KAYIT GETİRME (detay / düzenleme formu için)
 * =====================================================================
 *  Sunucudan hazır HTML değil HAM VERİ döner. Ekranı JavaScript
 *  .text() ile doldurduğu için XSS riski oluşmaz.
 * ------------------------------------------------------------------

function handle_fetch(PDO $db): void
{
    require_csrf();

    $id = post_id('id');
    if ($id === null) {
        json_error('Geçersiz kayıt numarası.');
    }

    $item = find_item($db, $id);
    if ($item === null) {
        json_error('Kayıt bulunamadı.', 404);
    }

    json_response([
        'success'     => true,
        'id'          => (int) $item['id'],
        'title'       => $item['title'],
        'description' => $item['description'],
        'image'       => $item['image'],
        'image_url'   => $item['image'] !== '' ? UPLOAD_URL . rawurlencode((string) $item['image']) : '',
        'created_at'  => format_date($item['created_at']),
    ]);
}


/* =====================================================================
 *  SİLME
 * =====================================================================
 *  Kayıt ve ilişkili görsel dosyası birlikte silinir.
 * ------------------------------------------------------------------

function handle_delete(PDO $db): void
{
    require_csrf();

    $id = post_id('id');
    if ($id === null) {
        json_error('Geçersiz kayıt numarası.');
    }

    // Kaydı silmeden ÖNCE görsel adını öğren; sonra öğrenemeyiz.
    $item = find_item($db, $id);

    $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute([':id' => $id]);

    // rowCount(): kaç satır etkilendi? 0 ise böyle bir kayıt yoktu.
    if ($stmt->rowCount() === 0) {
        json_error('Silinecek kayıt bulunamadı.', 404);
    }

    if ($item !== null && $item['image'] !== '') {
        delete_upload((string) $item['image']);
    }

    json_success('Kayıt silindi.', ['id' => $id]);
}

--------------------------------------------------------------------- */
