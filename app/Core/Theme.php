<?php
/**
 * =====================================================================
 *  Theme – "Tema Rengi" ayarını gerçek bir renk paletine çevirir
 * ---------------------------------------------------------------------
 *  SORUN: Ayarlar → Sistem ekranında bir "Tema Rengi" seçicisi vardı,
 *  ama seçilen renk hiçbir yerde görünmüyordu; yalnızca telefonun
 *  tarayıcı çubuğuna (PWA theme-color) gidiyordu. Yönetici rengi
 *  değiştiriyor, panelde hiçbir şey değişmiyordu.
 *
 *  ÇÖZÜM: Tasarım kalıbı (cilginyazilim.css) zaten TEK bir marka
 *  renginden türetilmiş token'lar üzerine kuruludur:
 *
 *      --cy-brand-700  koyu ton    (gradyanın başı, vurgular)
 *      --cy-brand-600  ANA RENK    (butonlar, bağlantılar, aktif menü)
 *      --cy-brand-100  çok açık    (seçili satır, yumuşak zeminler)
 *
 *  Burada seçilen tek renkten bu tonların TAMAMINI üretiyor ve
 *  sayfaya küçük bir <style> bloğu olarak basıyoruz. Böylece tek bir
 *  renk seçimi panelin ve sitenin tamamını yeniden renklendirir —
 *  CSS dosyasına dokunmadan.
 *
 *  NEDEN CSS DOSYASI ÜRETMİYORUZ? Renk bir veritabanı ayarıdır ve
 *  anında değişmelidir. Disk üzerinde bir dosya üretmek yazma izni,
 *  önbellek temizleme ve "eski dosya takıldı" sorunları getirirdi.
 *  Üretilen blok yaklaşık 400 bayttır; bir HTTP isteğinden ucuzdur.
 *
 *  Varsayılan renk seçiliyse HİÇBİR ŞEY basılmaz: kural yoksa
 *  cilginyazilim.css içindeki değerler zaten geçerlidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Theme
{
    /** cilginyazilim.css içindeki --cy-brand-600 ile AYNI olmalıdır. */
    public const DEFAULT_BRAND = '#0b5cb5';

    /**
     * Yöneticinin seçtiği marka rengi (#rrggbb).
     * Geçersiz bir değer varsayılana düşer — panelden gelen metne
     * güvenip doğrudan CSS'e yazmıyoruz.
     */
    public static function brand(): string
    {
        return self::normalize(Setting::get('sistem_tema_rengi', self::DEFAULT_BRAND));
    }

    /** Renk varsayılandan farklı mı? (farklı değilse CSS üretmeye gerek yok) */
    public static function isCustom(): bool
    {
        return strcasecmp(self::brand(), self::DEFAULT_BRAND) !== 0;
    }

    /**
     * Sayfaya basılacak <style> bloğu. Varsayılan renkte boş döner.
     *
     * Üretilen değerlerin tamamı "#rrggbb" biçimindedir; kullanıcı
     * girdisi CSS'e olduğu gibi geçmez (bkz. normalize).
     */
    public static function styleTag(): string
    {
        if (!self::isCustom()) {
            return '';
        }

        $t = self::palette();

        return '<style id="cy-theme-color">:root{'
            . '--cy-brand-800:' . $t[800] . ';'
            . '--cy-brand-700:' . $t[700] . ';'
            . '--cy-brand-600:' . $t[600] . ';'
            . '--cy-brand-500:' . $t[500] . ';'
            . '--cy-brand-400:' . $t[400] . ';'
            . '--cy-brand-200:' . $t[200] . ';'
            . '--cy-brand-100:' . $t[100] . ';'
            . '--cy-brand-50:'  . $t[50]  . ';'
            . '--cy-accent:'     . $t[400] . ';'
            . '--cy-accent-600:' . $t[600] . ';'
            . '--cy-focus:0 0 0 3px ' . self::rgba($t[600], .25) . ';'
            . '--cy-gradient:linear-gradient(135deg,' . $t[700] . ' 0%,' . $t[600] . ' 55%,' . $t[500] . ' 100%);'
            . '}</style>';
    }

    /**
     * Seçilen renkten tüm tonları türetir.
     *
     * Koyu tonlar siyaha, açık tonlar beyaza doğru karıştırılarak
     * elde edilir. Oranlar varsayılan paletin (#0b5cb5 ailesi)
     * aralıklarına bakılarak seçilmiştir; hangi renk verilirse
     * verilsin kontrast dengesi benzer kalır.
     *
     * @return array<int,string> ton => #rrggbb
     */
    public static function palette(): array
    {
        $brand = self::brand();

        return [
            800 => self::mix($brand, '#000000', .55),
            700 => self::mix($brand, '#000000', .28),
            600 => $brand,
            500 => self::mix($brand, '#ffffff', .10),
            400 => self::mix($brand, '#ffffff', .32),
            200 => self::mix($brand, '#ffffff', .72),
            100 => self::mix($brand, '#ffffff', .90),
            50  => self::mix($brand, '#ffffff', .96),
        ];
    }

    /* =================================================================
     *  RENK ARİTMETİĞİ
     * ============================================================== */

    /**
     * "#abc", "abc", "#aabbcc" → "#aabbcc". Geçersizse varsayılan.
     *
     * Bu metot aynı zamanda GÜVENLİK sınırıdır: dönen değer her
     * zaman tam olarak yedi karakterlik bir onaltılık renktir, bu
     * yüzden CSS'e gömmek güvenlidir.
     */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = ltrim($value, '#');

        if (preg_match('/^[0-9a-f]{3}$/', $value) === 1) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $value) === 1
            ? '#' . $value
            : self::DEFAULT_BRAND;
    }

    /** İki rengi karıştırır. $ratio = 0 ilk renk, 1 ikinci renk. */
    private static function mix(string $a, string $b, float $ratio): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        $ratio = max(0.0, min(1.0, $ratio));

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 + ($r2 - $r1) * $ratio),
            (int) round($g1 + ($g2 - $g1) * $ratio),
            (int) round($b1 + ($b2 - $b1) * $ratio)
        );
    }

    private static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.'));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
