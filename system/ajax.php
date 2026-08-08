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

require __DIR__ . '/config.php';
require __DIR__ . '/function.php';

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
