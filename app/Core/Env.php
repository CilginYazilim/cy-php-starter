<?php
/**
 * =====================================================================
 *  Env – ".env" dosyası okuyucu
 * ---------------------------------------------------------------------
 *  Şifre gibi hassas değerleri koda yazmak yerine, depoya gönderilmeyen
 *  ".env" dosyasında tutarız. Kurulum sihirbazı bu dosyayı otomatik
 *  üretir; siz de elle oluşturabilirsiniz (bkz. .env.example).
 *
 *  ARAMA SIRASI
 *      .env dosyası → $_ENV → $_SERVER → getenv() → varsayılan
 *
 *  Sunucu ortam değişkenlerine de bakmamızın nedeni: paylaşımlı
 *  hostinglerde .env kullanılır, ama Docker/Heroku gibi ortamlarda
 *  değişkenler doğrudan sürece verilir. İkisi de çalışmalıdır.
 *
 *  DESTEKLENEN SÖZDİZİMİ
 *      KEY=deger
 *      export KEY=deger              (kabuk betiklerinden kopyala-yapıştır)
 *      KEY="tırnaklı  deger"         (\\ \" \$ \n \r \t kaçışları çözülür)
 *      KEY='ham deger'               (hiçbir kaçış çözülmez)
 *      KEY="deger"   # satır sonu notu (tırnaklı ya da tırnaksız)
 *      KEY=${BASKA_KEY}/alt-yol      (daha önce tanımlanmış değişkene atıf;
 *                                     düz "$" için tırnak içinde \$ yazın)
 *
 *  NEDEN putenv() KULLANMIYORUZ? putenv ile yazılan değerler alt
 *  süreçlere (exec, proc_open) miras kalır; veritabanı parolanızın
 *  çağırdığınız her komut satırı aracına sızmasını istemeyiz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string,string>|null null = henüz yüklenmedi */
    private static ?array $values = null;

    /**
     * Log ve hata ekranlarında GİZLENECEK anahtar kalıpları.
     * all() bu kalıplara uyan değerleri "***" olarak döndürür.
     */
    private const SECRET_PATTERN = '/(PASS|PASSWORD|SECRET|TOKEN|KEY|SIFRE|PAROLA)/i';

    public static function load(string $path): void
    {
        self::$values = [];

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $parsed = self::parseLine($line);

            if ($parsed !== null) {
                self::$values[$parsed[0]] = $parsed[1];
            }
        }
    }

    /**
     * Tek bir satırı ayrıştırır.
     *
     * @return array{0:string,1:string}|null Yorum/boş satırda null
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            return null;
        }

        [$key, $value] = explode('=', $line, 2);

        // "export DB_PASS=..." biçimini de kabul ediyoruz.
        $key = trim(preg_replace('/^export\s+/i', '', trim($key)) ?? '');

        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*\z/', $key) !== 1) {
            return null;
        }

        return [$key, self::parseValue(trim($value))];
    }

    /**
     * Tek bir değeri çözer. Testlerde doğrudan sınanabilsin diye
     * "public"tir; uygulama kodu Env::get() kullanmalıdır.
     */
    public static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        /* TIRNAKLI DEĞER: kapanış tırnağı SONDAKİ karakter olmak
         * zorunda değildir — KEY="deger"   # not  biçimi de geçerlidir.
         * Kapanışı karakter karakter arıyoruz; kaçışlanmış \" kapanış
         * sayılmaz. */
        if ($first === '"' || $first === "'") {
            $end = self::closingQuote($value, $first);

            if ($end !== null) {
                $inner = substr($value, 1, $end - 1);

                // Tek tırnak: içerik olduğu gibi alınır (ham).
                return $first === "'" ? $inner : self::unescape($inner);
            }
        }

        /* TIRNAKSIZ değer: satır sonundaki notu ayıklarız.
         * "#" yalnızca ÖNÜNDE BOŞLUK VARSA yorum sayılır — aksi halde
         * "Parola#123" gibi bir şifrenin yarısını atardık. */
        $value = (string) preg_replace('/\s+#.*$/', '', $value);

        return self::interpolate(trim($value));
    }

    /** Açılış tırnağının eşinin konumu; kapanmamışsa null. */
    private static function closingQuote(string $value, string $quote): ?int
    {
        $length = strlen($value);

        for ($i = 1; $i < $length; $i++) {
            if ($quote === '"' && $value[$i] === '\\') {
                $i++; // kaçışlanmış karakteri atla

                continue;
            }

            if ($value[$i] === $quote) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Çift tırnak içini TEK GEÇİŞTE çözer: kaçışlar ve ${ATIF}lar.
     *
     * NEDEN TEK GEÇİŞ? Eskiden önce kaçışlar çözülüyor, SONRA ${...}
     * aranıyordu; "\$\{" gibi kaçışlanmış bir dolar işaretini korumanın
     * yolu yoktu. Bir veritabanı parolası "${" içerdiğinde sessizce
     * değişiyor, site veritabanına bağlanamıyordu. Artık "\$" düz bir
     * dolar işaretidir ve atıf başlatmaz.
     *
     *      \\ → \     \" → "     \$ → $     \n \r \t → kontrol karakteri
     *      tanınmayan kaçış (ör. \d) olduğu gibi kalır
     */
    private static function unescape(string $inner): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)|\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/s',
            static function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    return match ($m[1]) {
                        'n'     => "\n",
                        'r'     => "\r",
                        't'     => "\t",
                        '"', '\\', '$' => $m[1],
                        default => '\\' . $m[1],
                    };
                }

                return self::$values[$m[2]] ?? '';
            },
            $inner
        );
    }

    /**
     * "${DB_HOST}" gibi atıfları daha önce okunmuş değerlerle
     * değiştirir. Yalnızca ÖNCEKİ satırlara bakar; böylece döngüsel
     * atıf (A=${B}, B=${A}) imkânsızdır.
     */
    private static function interpolate(string $value): string
    {
        if (!str_contains($value, '${')) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/',
            static fn (array $m): string => self::$values[$m[1]] ?? '',
            $value
        );
    }

    /* =================================================================
     *  OKUMA
     * ============================================================== */

    public static function get(string $key, string $default = ''): string
    {
        if (self::$values !== null && array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (is_scalar($candidate)) {
                return (string) $candidate;
            }
        }

        $fromServer = getenv($key);

        return $fromServer !== false ? (string) $fromServer : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $raw = strtolower(trim(self::get($key, $default ? 'true' : 'false')));

        if ($raw === '') {
            return $default;
        }

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $raw = trim(self::get($key, (string) $default));

        return is_numeric($raw) ? (int) $raw : $default;
    }

    /**
     * Bir değeri .env satırına GÜVENLE yazılabilir hale getirir —
     * parseValue()'nun tam tersi: Env::parseValue(Env::quote($x)) === $x.
     *
     * Yalnızca güvenli karakterlerden oluşan değerler tırnaksız yazılır;
     * geri kalan HER ŞEY çift tırnağa alınır ve \ " $ ile satır sonları
     * kaçışlanır. Eskiden kurulum sihirbazı yalnızca " karakterini
     * kaçışlıyordu: "\t" ya da "${" içeren bir veritabanı parolası
     * okunurken değişiyor, site veritabanına bağlanamıyordu.
     *
     * Kurulum sihirbazı da bunu kullanır (bu dosya bağımlılıksızdır).
     */
    public static function quote(string $value): string
    {
        if ($value !== '' && preg_match('#^[A-Za-z0-9_.:/@+,-]+\z#', $value) === 1) {
            return $value;
        }

        return '"' . strtr($value, [
            '\\' => '\\\\',
            '"'  => '\\"',
            '$'  => '\\$',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]) . '"';
    }

    /**
     * Virgülle ayrılmış listeyi diziye çevirir.
     *
     *      CSP_SCRIPT_SRC="https://js.stripe.com, https://maps.googleapis.com"
     *      → ['https://js.stripe.com', 'https://maps.googleapis.com']
     *
     * Boş değer boş dizi döner; tek bir değer de listedir.
     *
     * @param array<int,string> $default
     * @return array<int,string>
     */
    public static function list(string $key, array $default = []): array
    {
        $raw = trim(self::get($key));

        if ($raw === '') {
            return $default;
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $p): bool => $p !== '');

        return array_values($parts);
    }

    public static function has(string $key): bool
    {
        return self::get($key, "\0yok") !== "\0yok";
    }

    /**
     * Tanımlı tüm değişkenler — hassas olanlar MASKELİ.
     * Yalnızca tanılama ekranlarında (panel/sistem) kullanın.
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        $safe = [];

        foreach (self::$values ?? [] as $key => $value) {
            $safe[$key] = preg_match(self::SECRET_PATTERN, $key) === 1 && $value !== ''
                ? '***'
                : $value;
        }

        return $safe;
    }

    /** .env dosyası hiç var mı? (kurulum yapılmış mı sorusunun ilk işareti) */
    public static function exists(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }
}
