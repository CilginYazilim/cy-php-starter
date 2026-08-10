<?php
/**
 * =====================================================================
 *  Disk – Tek bir depolama alanı (kök klasör) üzerinde dosya işlemleri
 * ---------------------------------------------------------------------
 *  Bir "disk", kök klasörü ve web adresi yapılandırmayla belirlenen
 *  bir alandır (bkz. config/storage.php):
 *
 *      public  → upload/          tarayıcıdan erişilebilir, url() verir
 *      private → storage/files/   web'e KAPALI, yalnızca PHP okur
 *
 *  BÜTÜN YOLLAR KÖKE GÖRELİDİR: "fatura/2026/03.pdf". Mutlak yol ya da
 *  ".." içeren bir yol her zaman reddedilir — bu sınıfın en önemli
 *  görevi budur.
 *
 *  NEDEN "DRIVER" ARAYÜZÜ YOK?
 *  Şu an tek bir sürücü var: yerel dosya sistemi. Tek uygulaması olan
 *  bir arayüz, gelecekte belki gerekecek diye bugün eklenen ölü
 *  soyutlamadır. S3 gibi ikinci bir sürücü gerçekten geldiğinde bu
 *  sınıfın genel metotları hazır bir sözleşme oluşturur; arayüzü o
 *  gün çıkarmak beş dakikalık iştir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Storage;

final class Disk
{
    public function __construct(
        private readonly string $name,
        private readonly string $root,
        private readonly string $url = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Tarayıcıdan erişilebilen bir disk mi? */
    public function isPublic(): bool
    {
        return $this->url !== '';
    }

    public function root(): string
    {
        return rtrim($this->root, '/\\');
    }

    /* =================================================================
     *  YOL GÜVENLİĞİ
     * ============================================================== */

    /**
     * Göreli yolu doğrular ve normalize eder.
     *
     * Reddedilenler: boş yol, mutlak yol (/etc/passwd, C:\...),
     * ".." ve "." parçaları, null bayt, sürücü harfi, UNC yolu.
     *
     * NEDEN ÇOK KATMANLI? Tek bir str_replace('..','') yetmez:
     * "....//" gibi girdiler onu atlatır. Biz yolu PARÇALARINA ayırıp
     * her parçayı tek tek denetliyoruz; birleştirme sonrası da
     * realpath ile kökün içinde kaldığını doğruluyoruz.
     */
    public function relative(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, "\0")) {
            throw new StorageException('Geçersiz dosya yolu.');
        }

        // Mutlak yol ya da sürücü harfi ("C:/...") kabul edilmez.
        if (str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:#', $path) === 1) {
            throw new StorageException('Dosya yolu göreli olmalıdır.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new StorageException('Dosya yolu geçersiz bir parça içeriyor.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Göreli yolu MUTLAK yola çevirir.
     *
     * $mustExist true ise dosyanın gerçekten kökün içinde olduğu
     * realpath ile doğrulanır (sembolik bağlantı hilelerine karşı).
     */
    public function path(string $path, bool $mustExist = false): string
    {
        $relative = $this->relative($path);
        $absolute = $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!$mustExist) {
            return $absolute;
        }

        $realRoot = realpath($this->root());
        $realPath = realpath($absolute);

        if ($realRoot === false || $realPath === false || !str_starts_with($realPath, $realRoot)) {
            throw new StorageException('Dosya bulunamadı.');
        }

        return $realPath;
    }

    /* =================================================================
     *  OKUMA
     * ============================================================== */

    public function exists(string $path): bool
    {
        try {
            return is_file($this->path($path, true));
        } catch (StorageException) {
            return false;
        }
    }

    public function get(string $path): string
    {
        $file = $this->path($path, true);

        if (!is_readable($file)) {
            throw new StorageException('Dosya okunamadı.');
        }

        return (string) file_get_contents($file);
    }

    public function size(string $path): int
    {
        return (int) filesize($this->path($path, true));
    }

    public function lastModified(string $path): int
    {
        return (int) filemtime($this->path($path, true));
    }

    /**
     * Dosyanın GERÇEK içerik türü.
     *
     * Uzantıya ASLA güvenmeyiz: "fatura.pdf" adında bir PHP betiği
     * yüklenebilir. finfo dosyanın ilk baytlarına bakar.
     */
    public function mime(string $path): string
    {
        $file = $this->path($path, true);

        if (!function_exists('finfo_open')) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * Klasördeki dosyalar (köke göreli yollar).
     *
     * @return array<int,string>
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $base = $directory === '' ? $this->root() : $this->path($directory);

        if (!is_dir($base)) {
            return [];
        }

        $pattern = $recursive
            ? $base . DIRECTORY_SEPARATOR . '*'
            : $base . DIRECTORY_SEPARATOR . '*';

        $found = [];

        foreach (glob($pattern) ?: [] as $item) {
            if (is_dir($item)) {
                if ($recursive) {
                    $sub  = ltrim(str_replace($this->root(), '', $item), '/\\');
                    $found = array_merge($found, $this->files(str_replace('\\', '/', $sub), true));
                }

                continue;
            }

            $found[] = str_replace('\\', '/', ltrim(str_replace($this->root(), '', $item), '/\\'));
        }

        sort($found, SORT_STRING);

        return $found;
    }

    /* =================================================================
     *  YAZMA
     * ============================================================== */

    /** İçeriği yazar; üst klasörleri gerekiyorsa oluşturur. */
    public function put(string $path, string $contents): string
    {
        $relative = $this->relative($path);
        $absolute = $this->path($relative);

        $this->ensureDirectory(dirname($absolute));

        if (@file_put_contents($absolute, $contents, LOCK_EX) === false) {
            throw new StorageException('Dosya kaydedilemedi.');
        }

        @chmod($absolute, 0644);

        return $relative;
    }

    /**
     * Yüklenmiş geçici dosyayı diske taşır.
     *
     * move_uploaded_file kullanılır (rename değil): PHP böylece
     * dosyanın gerçekten bu isteğe ait bir yükleme olduğunu bir kez
     * daha doğrular.
     */
    public function putUploadedFile(string $tmpPath, string $path): string
    {
        $relative = $this->relative($path);
        $absolute = $this->path($relative);

        $this->ensureDirectory(dirname($absolute));

        if (!move_uploaded_file($tmpPath, $absolute)) {
            throw new StorageException('Yüklenen dosya kaydedilemedi.');
        }

        @chmod($absolute, 0644);

        return $relative;
    }

    public function delete(string $path): bool
    {
        try {
            $file = $this->path($path, true);
        } catch (StorageException) {
            // Yoksa zaten silinmiş sayılır; hata fırlatmak çağıranı
            // gereksiz try/catch yazmaya zorlardı.
            return false;
        }

        return is_file($file) && @unlink($file);
    }

    /** Aynı disk içinde taşır/yeniden adlandırır. */
    public function move(string $from, string $to): string
    {
        $source = $this->path($from, true);
        $target = $this->path($to);

        if ($source === $target) {
            return $this->relative($to);
        }

        if (is_file($target)) {
            throw new StorageException('Hedefte aynı adlı bir dosya var.');
        }

        $this->ensureDirectory(dirname($target));

        if (!@rename($source, $target)) {
            throw new StorageException('Dosya taşınamadı.');
        }

        return $this->relative($to);
    }

    public function copy(string $from, string $to): string
    {
        $source = $this->path($from, true);
        $target = $this->path($to);

        $this->ensureDirectory(dirname($target));

        if (!@copy($source, $target)) {
            throw new StorageException('Dosya kopyalanamadı.');
        }

        @chmod($target, 0644);

        return $this->relative($to);
    }

    public function makeDirectory(string $directory): void
    {
        $this->ensureDirectory($this->path($directory));
    }

    private function ensureDirectory(string $absolute): void
    {
        if (is_dir($absolute)) {
            return;
        }

        if (!@mkdir($absolute, 0755, true) && !is_dir($absolute)) {
            throw new StorageException('Klasör oluşturulamadı.');
        }
    }

    /* =================================================================
     *  ADRES
     * ============================================================== */

    /**
     * Dosyanın tarayıcı adresi.
     *
     * Özel (private) diskte boş string döner — o dosyalar doğrudan
     * servis edilmez, bir denetleyici üzerinden indirilirler
     * (bkz. Response::download).
     */
    public function url(string $path): string
    {
        if ($this->url === '') {
            return '';
        }

        try {
            $relative = $this->relative($path);
        } catch (StorageException) {
            return '';
        }

        if (!$this->exists($relative)) {
            return '';
        }

        /* Her SEGMENT ayrı kodlanır; rawurlencode'u tüm yola
         * uygulamak "/" karakterini de kodlayıp adresi bozar. */
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));
        $prefix  = rtrim($this->url, '/');

        /* Yapılandırmadaki adres göreliyse ("upload/") uygulamanın
         * taban yolunu ekleriz. Aksi halde /panel/ayarlar gibi derin
         * bir sayfada tarayıcı onu /panel/upload/... diye çözer ve
         * görsel kırık görünür. */
        if (!preg_match('#^(https?:)?//#i', $prefix) && !str_starts_with($prefix, '/')) {
            $prefix = \App\Core\Url::base() . '/' . $prefix;
        }

        return $prefix . '/' . $encoded;
    }
}
