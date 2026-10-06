<?php
/**
 * =====================================================================
 *  MakeCommand – Dosya üreten komutların ortak atası
 * ---------------------------------------------------------------------
 *  Kalıp (stub) dosyaları app/Core/Console/stubs/ altındadır. İçlerinde
 *  {{AD}} gibi yer tutucular bulunur; burada gerçek değerlerle
 *  değiştirilirler.
 *
 *  Kalıpları kendi zevkinize göre düzenleyebilirsiniz — üretilen her
 *  dosya o kalıptan çıkar, kodun içine gömülü değildir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Console\Commands;

use App\Core\Console\Command;
use RuntimeException;

abstract class MakeCommand extends Command
{
    /**
     * Çekirdeğin tabloları. Bir modül ya da model bu adlardan birini
     * üretirse "php cy migrate:rollback" o modülün down() metoduyla
     * ÇEKİRDEK tabloyu düşürebilirdi ("Kullanicilar" adlı bir modül
     * "kullanicilar" tablosunu silerdi).
     */
    private const CORE_TABLES = [
        'kullanicilar', 'ayarlar', 'mesajlar', 'mail_kayitlari', 'login_attempts',
        'sayfalar', 'migrasyonlar', 'onbellek', 'isler', 'api_anahtarlari',
    ];

    /**
     * PHP'nin sınıf adı olarak kabul etmediği ayrılmış sözcükler.
     * ("die", "include_once" gibi dil yapıları da dahil; eskiden
     * "make:model Die" ayrıştırılamayan bir dosya üretiyordu.)
     */
    private const RESERVED = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case',
        'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo',
        'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn',
        'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list',
        'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print',
        'private', 'protected', 'public', 'readonly', 'require', 'require_once', 'return', 'self',
        'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var',
        'void', 'while', 'xor', 'yield',
    ];

    /**
     * Kullanıcının verdiği adı sınıf adına çevirir.
     *      "urun kategori" → "UrunKategori"
     *      "urun-kategori" → "UrunKategori"
     *      "Ürün"          → "Urun"
     *
     * TÜRKÇE HARFLER ÇEVRİLİR, SİLİNMEZ. Eskiden ASCII dışındaki her
     * karakter boşluk sayılıyordu: "Ürün" sessizce "RN" sınıfına
     * dönüşüyordu.
     */
    protected function studly(string $name): string
    {
        $name  = strtr($name, [
            'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'İ' => 'I',
            'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
        ]);
        $clean = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name) ?? '';

        return str_replace(' ', '', ucwords(trim($clean)));
    }

    /**
     * Argümanı alır, sınıf adına çevirir ve DOĞRULAR. Geçersizse
     * nedenini yazıp komutu sonlandırır.
     *
     * Eskiden doğrulama yoktu: "9Stok" rakamla başlayan, PHP'nin
     * ayrıştıramadığı bir sınıf üretiyordu.
     */
    protected function className(string $usage): string
    {
        $raw   = $this->requireName($usage);
        $class = $this->studly($raw);

        $error = match (true) {
            $class === ''                                        => 'Ad en az bir harf içermelidir.',
            preg_match('/^[A-Za-z][A-Za-z0-9]*\z/', $class) !== 1 => 'Ad bir harfle başlamalıdır ("' . $class . '" geçersiz bir PHP sınıf adı olur).',
            strlen($class) > 60                                  => 'Ad en fazla 60 karakter olabilir.',
            in_array(strtolower($class), self::RESERVED, true)   => '"' . $class . '" PHP\'de ayrılmış bir sözcük; sınıf adı olamaz.',
            default                                              => null,
        };

        if ($error !== null) {
            $this->out->error($error);
            $this->out->muted('  Kullanım: ' . $usage);

            exit(self::HATA);
        }

        if ($class !== $raw) {
            $this->out->muted('  Ad sınıf adına çevrildi: "' . $raw . '" → ' . $class);
        }

        return $class;
    }

    /** Üretilecek tablo adı çekirdekle çakışıyorsa komutu durdurur. */
    protected function assertTableFree(string $table): void
    {
        if (in_array(strtolower($table), self::CORE_TABLES, true)) {
            $this->out->error('"' . $table . '" çekirdek bir tablonun adı. Başka bir ad seçin.');
            $this->out->muted('  Aynı adı kullanmak, geri alma (rollback) sırasında çekirdek tabloyu silebilir.');

            exit(self::HATA);
        }
    }

    /** "UrunKategori" → "urun_kategori" (tablo/dosya adları için) */
    protected function snake(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $this->studly($name)) ?? '';

        return strtolower($snake);
    }

    protected function stub(string $name): string
    {
        $file = CY_BASE . '/app/Core/Console/stubs/' . $name . '.stub';

        if (!is_file($file)) {
            throw new RuntimeException('Kalıp dosyası bulunamadı: ' . $name . '.stub');
        }

        return (string) file_get_contents($file);
    }

    /**
     * Kalıbı doldurup diske yazar.
     *
     * @param array<string,string> $replacements
     * @return bool false → dosya zaten var (üzerine YAZILMAZ)
     */
    protected function writeFile(string $path, string $stub, array $replacements): bool
    {
        if (is_file($path)) {
            $this->out->error('Dosya zaten var: ' . \App\Core\ErrorHandler::relative($path));

            return false;
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Klasör oluşturulamadı: ' . $dir);
        }

        $content = str_replace(
            array_map(static fn (string $key): string => '{{' . $key . '}}', array_keys($replacements)),
            array_values($replacements),
            $this->stub($stub)
        );

        $cakisma = $this->importCollision($content);

        if ($cakisma !== null) {
            $this->out->error($cakisma);
            $this->out->muted('  Başka bir ad seçin; dosya oluşturulmadı.');

            return false;
        }

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('Dosya yazılamadı: ' . $path);
        }

        $this->out->success('Oluşturuldu: ' . \App\Core\ErrorHandler::relative($path));

        return true;
    }

    /**
     * Üretilen sınıfın adı dosyanın İÇE AKTARDIĞI bir adla çakışıyor mu?
     *
     * Örnek: "make:controller Controller" → "final class Controller
     * extends Controller" ve "use App\Http\Controller;" — PHP dosyayı
     * yüklerken "name is already in use" hatası verir. Eskiden dosya
     * yine de yazılıyordu. PHP'de sınıf adları büyük/küçük harf
     * duyarsızdır; karşılaştırma da öyle yapılır.
     */
    protected function importCollision(string $content): ?string
    {
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $sinif) !== 1) {
            return null;
        }

        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*;/m', $content, $m, PREG_SET_ORDER);

        foreach ($m as $use) {
            $kisa = $use[2] ?? '';
            $kisa = $kisa !== '' ? $kisa : substr((string) strrchr('\\' . $use[1], '\\'), 1);

            if (strcasecmp($kisa, $sinif[1]) === 0) {
                return '"' . $sinif[1] . '" adı, dosyanın kullandığı ' . $use[1] . ' sınıfıyla çakışıyor.';
            }
        }

        return null;
    }

    /** Argüman verilmemişse anlaşılır bir hata bas. */
    protected function requireName(string $usage): string
    {
        $name = trim($this->input->argument(0));

        if ($name === '') {
            $this->out->error('Bir ad vermelisiniz.');
            $this->out->muted('  Kullanım: ' . $usage);

            exit(self::HATA);
        }

        return $name;
    }
}
