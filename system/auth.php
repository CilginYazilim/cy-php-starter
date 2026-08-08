<?php
/**
 * =====================================================================
 *  OTURUM VE YETKİ YÖNETİMİ (Authentication & Authorization)
 *  cilginyazilim.com – Çılgın Yazılım PHP Başlangıç Şablonu
 * ---------------------------------------------------------------------
 *  İKİ FARKLI KAVRAM, KARIŞTIRMAYIN:
 *    Authentication (kimlik doğrulama) → "Sen kimsin?"  → auth_login()
 *    Authorization  (yetkilendirme)    → "Buna hakkın var mı?" → require_role()
 *
 *  KULLANIMI:
 *    // Korumalı bir sayfanın en üstünde:
 *    require_login();                  // giriş yapmamışsa giriş sayfasına at
 *    require_role('admin');            // sadece yöneticiler
 *
 *    // Şablon içinde:
 *    if (auth_check()) { echo auth_user()['ad']; }
 *
 *  GÜVENLİK NOTLARI:
 *   - Parolalar password_hash() ile saklanır (bcrypt). md5/sha1 ASLA.
 *   - Girişte session_regenerate_id() çağrılır → oturum sabitleme
 *     (session fixation) saldırısını engeller.
 *   - Kullanıcı adı/parola hatasında AYNI mesaj döner → saldırgan
 *     hangi e-postanın kayıtlı olduğunu öğrenemez (user enumeration).
 *   - Ardışık hatalı denemeler geçici kilitlenmeye yol açar.
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 *  Kaba kuvvet (brute force) koruması ayarları
 * ------------------------------------------------------------------ */
const AUTH_MAX_ATTEMPTS   = 5;    // Bu kadar hatalı denemeden sonra
const AUTH_LOCKOUT_SECONDS = 300; // bu kadar saniye kilitlenir (5 dk)

/**
 * Rol sıralaması: büyük sayı = daha yetkili.
 * require_role() bu sıralamaya göre "en az şu rol" kontrolü yapar.
 */
function auth_role_levels(): array
{
    return [
        'uye'    => 1,
        'editor' => 2,
        'admin'  => 3,
    ];
}

/** Rollerin Türkçe gösterim adları. */
function auth_role_labels(): array
{
    return [
        'uye'    => 'Üye',
        'editor' => 'Editör',
        'admin'  => 'Yönetici',
    ];
}

/** Durumların Türkçe gösterim adları. */
function auth_status_labels(): array
{
    return [
        'aktif'  => 'Aktif',
        'pasif'  => 'Pasif',
        'askida' => 'Askıda',
    ];
}


/* =====================================================================
 *  PAROLA
 * ================================================================== */

/**
 * Parolayı güvenli şekilde özetler (hash).
 *
 * PASSWORD_DEFAULT: PHP'nin o anki en iyi algoritmasını kullanır.
 * Bugün bcrypt; ileride PHP daha iyisine geçerse kodunuz değişmeden
 * yeni algoritmayı kullanmaya başlar.
 */
function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Parola karmaşıklığını doğrular.
 *
 * @return string|null Hata mesajı, sorun yoksa null
 */
function validate_password(string $plain, string $label = 'Parola'): ?string
{
    if (mb_strlen($plain, 'UTF-8') < 8) {
        return $label . ' en az 8 karakter olmalıdır.';
    }
    if (!preg_match('/[A-Za-zÇĞİÖŞÜçğıöşü]/u', $plain)) {
        return $label . ' en az bir harf içermelidir.';
    }
    if (!preg_match('/[0-9]/', $plain)) {
        return $label . ' en az bir rakam içermelidir.';
    }

    return null;
}


/* =====================================================================
 *  GİRİŞ / ÇIKIŞ
 * ================================================================== */

/**
 * Kullanıcı girişi yapar.
 *
 * @param string $identifier E-posta VEYA kullanıcı adı
 * @param string $password   Düz metin parola
 * @return array{0:bool,1:string} [başarılı mı, mesaj]
 */
function auth_login(PDO $db, string $identifier, string $password): array
{
    // --- Kilit kontrolü ---
    $lockedUntil = $_SESSION['auth_locked_until'] ?? 0;
    if ($lockedUntil > time()) {
        $remaining = (int) ceil(($lockedUntil - time()) / 60);
        return [false, 'Çok fazla hatalı deneme. Lütfen ' . $remaining . ' dakika sonra tekrar deneyin.'];
    }

    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return [false, 'E-posta/kullanıcı adı ve parola zorunludur.'];
    }

    // Tek sorguyla hem e-postaya hem kullanıcı adına bak.
    // NOT: EMULATE_PREPARES kapalıyken aynı yer tutucu iki kez
    // kullanılamaz; bu yüzden iki ayrı isim veriyoruz.
    $stmt = $db->prepare(
        'SELECT * FROM kullanicilar WHERE eposta = :eposta OR kullanici_adi = :kadi LIMIT 1'
    );
    $stmt->execute([':eposta' => $identifier, ':kadi' => $identifier]);
    $user = $stmt->fetch();

    /* GÜVENLİK: Kullanıcı bulunamasa bile password_verify() çalıştırıyoruz.
     * Neden? Kullanıcı yoksa hemen dönseydik, yanıt gözle görülür şekilde
     * daha hızlı olurdu. Saldırgan bu süre farkına bakarak hangi
     * e-postaların kayıtlı olduğunu tespit edebilirdi (timing attack).
     * Sahte bir özetle doğrulama yaparak süreyi eşitliyoruz. */
    $hash = $user['sifre'] ?? '$2y$12$usuallyinvalidhashusuallyinvalidhashusuallyinvalidhashuh';
    $passwordOk = password_verify($password, $hash);

    if ($user === false || !$passwordOk) {
        auth_register_failure();
        // Aynı mesaj: hangi alanın yanlış olduğunu belli etmiyoruz.
        return [false, 'E-posta/kullanıcı adı veya parola hatalı.'];
    }

    if ($user['durum'] !== 'aktif') {
        auth_register_failure();
        return [false, 'Hesabınız şu anda ' . (auth_status_labels()[$user['durum']] ?? $user['durum']) . '. Yönetici ile görüşün.'];
    }

    /* Parola doğru ama daha eski/zayıf bir algoritmayla özetlenmişse
     * (örn. PHP sürümü yükseldi) sessizce yeniden özetleyip kaydet. */
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $rehash = $db->prepare('UPDATE kullanicilar SET sifre = :sifre WHERE id = :id');
        $rehash->execute([':sifre' => hash_password($password), ':id' => $user['id']]);
    }

    /* OTURUM SABİTLEME KORUMASI:
     * Saldırgan size önceden bilinen bir oturum kimliği kabul ettirip,
     * siz giriş yaptıktan sonra aynı kimlikle hesabınıza girebilir.
     * Giriş anında kimliği yenileyerek bunu imkânsız kılıyoruz. */
    session_regenerate_id(true);

    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_rol']     = $user['rol'];
    $_SESSION['auth_giris']   = time();

    // Başarılı giriş: hata sayacını sıfırla.
    unset($_SESSION['auth_attempts'], $_SESSION['auth_locked_until']);

    // Son giriş bilgilerini kaydet.
    $update = $db->prepare(
        'UPDATE kullanicilar
            SET son_giris = NOW(), son_giris_ip = :ip, giris_sayisi = giris_sayisi + 1
          WHERE id = :id'
    );
    $update->execute([
        ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ':id' => $user['id'],
    ]);

    return [true, 'Hoş geldiniz, ' . $user['ad'] . '.'];
}

/** Hatalı deneme sayacını artırır, sınır aşılırsa kilitler. */
function auth_register_failure(): void
{
    $attempts = (int) ($_SESSION['auth_attempts'] ?? 0) + 1;
    $_SESSION['auth_attempts'] = $attempts;

    if ($attempts >= AUTH_MAX_ATTEMPTS) {
        $_SESSION['auth_locked_until'] = time() + AUTH_LOCKOUT_SECONDS;
        $_SESSION['auth_attempts']     = 0;
    }
}

/**
 * Oturumu kapatır.
 *
 * Sadece $_SESSION dizisini boşaltmak yetmez; çerezi de silip
 * oturumu sunucu tarafında yok ediyoruz.
 */
function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}


/* =====================================================================
 *  DURUM SORGULARI
 * ================================================================== */

/** Giriş yapılmış mı? */
function auth_check(): bool
{
    return !empty($_SESSION['auth_user_id']);
}

/** Giriş yapan kullanıcının ID'si (yoksa null). */
function auth_id(): ?int
{
    return auth_check() ? (int) $_SESSION['auth_user_id'] : null;
}

/**
 * Giriş yapan kullanıcının tüm bilgilerini döndürür.
 *
 * Sonuç istek başına önbelleklenir; aynı sayfada on kere çağırsanız
 * da veritabanına bir kez gidilir.
 *
 * ÖNEMLİ: Rol bilgisini oturumdan değil VERİTABANINDAN okuyoruz.
 * Yönetici birinin rolünü düşürdüğünde, o kişinin açık oturumu
 * eski yetkisini kullanmaya devam edemesin diye.
 *
 * @return array<string,mixed>|null
 */
function auth_user(?PDO $db = null): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }

    if (!auth_check() || $db === null) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM kullanicilar WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => auth_id()]);
    $user = $stmt->fetch();

    // Kullanıcı silinmiş veya pasife alınmışsa oturumu düşür.
    if ($user === false || $user['durum'] !== 'aktif') {
        auth_logout();
        $loaded = true;
        return null;
    }

    // Oturumdaki rolü veritabanıyla senkron tut.
    $_SESSION['auth_rol'] = $user['rol'];

    $cached = $user;
    $loaded = true;

    return $cached;
}

/** Giriş yapan kullanıcının rolü (giriş yoksa boş string). */
function auth_role(): string
{
    return (string) ($_SESSION['auth_rol'] ?? '');
}

/** Kullanıcı yönetici mi? */
function is_admin(): bool
{
    return auth_role() === 'admin';
}

/**
 * Kullanıcının rolü, verilen rolden EN AZ o kadar yetkili mi?
 * Örn: auth_at_least('editor') → editor ve admin için true.
 */
function auth_at_least(string $minRole): bool
{
    $levels = auth_role_levels();
    $current = $levels[auth_role()] ?? 0;
    $needed  = $levels[$minRole] ?? 99;

    return $current >= $needed;
}


/* =====================================================================
 *  KORUMA (sayfa başlarında çağrılır)
 * ================================================================== */

/**
 * Giriş yapılmamışsa giriş sayfasına yönlendirir.
 *
 * @param string $loginUrl Giriş sayfasının yolu (alt klasörden '../giris.php')
 */
function require_login(string $loginUrl = 'giris.php'): void
{
    if (auth_check()) {
        return;
    }

    // Giriş sonrası kullanıcıyı geldiği sayfaya geri götürebilmek için
    // istenen adresi oturumda saklıyoruz.
    $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'] ?? '';

    header('Location: ' . $loginUrl);
    exit;
}

/**
 * Belirli bir rolün altındaysa erişimi engeller.
 *
 * @param string $minRole  En az gereken rol ('editor', 'admin'...)
 * @param string $loginUrl Giriş yapılmamışsa yönlendirilecek adres
 */
function require_role(string $minRole, string $loginUrl = 'giris.php'): void
{
    require_login($loginUrl);

    if (!auth_at_least($minRole)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<title>Yetkisiz erişim</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#f2f7fd;color:#0f172a;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.box{background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(6,19,33,.12);'
            . 'padding:2rem 2.5rem;max-width:420px;text-align:center}'
            . 'a{color:#0b5cb5}</style></head><body><div class="box">'
            . '<h1 style="font-size:1.25rem">Bu sayfaya erişim yetkiniz yok</h1>'
            . '<p>Bu bölüm için gereken rol: <strong>'
            . htmlspecialchars(auth_role_labels()[$minRole] ?? $minRole, ENT_QUOTES, 'UTF-8')
            . '</strong><br>Sizin rolünüz: <strong>'
            . htmlspecialchars(auth_role_labels()[auth_role()] ?? auth_role(), ENT_QUOTES, 'UTF-8')
            . '</strong></p><p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Geri dön</a></p>'
            . '</div></body></html>';
        exit;
    }
}

/**
 * AJAX istekleri için yetki kontrolü.
 * Yönlendirme yapmaz, JSON hata döndürür.
 */
function require_login_json(): void
{
    if (!auth_check()) {
        json_error('Bu işlem için giriş yapmalısınız.', 401);
    }
}

/** AJAX istekleri için rol kontrolü. */
function require_role_json(string $minRole): void
{
    require_login_json();

    if (!auth_at_least($minRole)) {
        json_error('Bu işlem için yetkiniz yok.', 403);
    }
}


/* =====================================================================
 *  KULLANICI SORGULARI
 * ================================================================== */

/** Tek bir kullanıcıyı getirir. */
function find_user(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM kullanicilar WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

/**
 * E-posta veya kullanıcı adı başkası tarafından kullanılıyor mu?
 *
 * @param int|null $exceptId Düzenlemede kendi kaydını hariç tutmak için
 */
function user_field_taken(PDO $db, string $field, string $value, ?int $exceptId = null): bool
{
    // GÜVENLİK: $field kullanıcıdan gelmez, kodun içinden gelir.
    // Yine de beyaz listeyle sınırlıyoruz — sütun adları prepared
    // statement ile bind edilemez.
    if (!in_array($field, ['eposta', 'kullanici_adi'], true)) {
        throw new InvalidArgumentException('Geçersiz alan: ' . $field);
    }

    $sql    = 'SELECT COUNT(*) FROM kullanicilar WHERE ' . $field . ' = :deger';
    $params = [':deger' => $value];

    if ($exceptId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $exceptId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

/** Sistemdeki yönetici sayısı (son yöneticiyi silmeyi engellemek için). */
function admin_count(PDO $db): int
{
    return (int) $db->query(
        "SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin' AND durum = 'aktif'"
    )->fetchColumn();
}

/** Kullanıcı adı doğrular (harf, rakam, alt çizgi, nokta). */
function validate_username(?string $value, string $label = 'Kullanıcı adı'): array
{
    $value = trim((string) $value);

    if ($value === '') {
        return ['', $label . ' boş bırakılamaz.'];
    }
    if (mb_strlen($value, 'UTF-8') < 3) {
        return [$value, $label . ' en az 3 karakter olmalıdır.'];
    }
    if (mb_strlen($value, 'UTF-8') > 50) {
        return [$value, $label . ' en fazla 50 karakter olabilir.'];
    }
    if (!preg_match('/^[a-zA-Z0-9._]+$/', $value)) {
        return [$value, $label . ' yalnızca İngilizce harf, rakam, nokta ve alt çizgi içerebilir.'];
    }

    return [$value, null];
}
