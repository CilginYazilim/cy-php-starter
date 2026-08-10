<?php
/**
 * =====================================================================
 *  MailUi – E-posta şablonlarının yapı taşları
 * ---------------------------------------------------------------------
 *  E-postada satır içi stil zorunludur; aynı uzun style="..." metnini
 *  her şablona kopyalamak hataya davetiye çıkarır. Bu sınıf o parçaları
 *  tek yerde toplar:
 *
 *      <?= MailUi::baslik('Merhaba Ali') ?>
 *      <?= MailUi::paragraf('Hesabınız oluşturuldu.') ?>
 *      <?= MailUi::dugme(url('giris'), 'Giriş Yap') ?>
 *
 *  Verilen metinler HTML'e KAÇIŞLANARAK basılır — kullanıcıdan gelen
 *  bir ad ya da konu mektubun düzenini bozamaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Setting;

final class MailUi
{
    private const FONT = 'Segoe UI,Roboto,Helvetica,Arial,sans-serif';

    public static function renk(): string
    {
        return Setting::get('sistem_tema_rengi', '#0b5cb5');
    }

    public static function baslik(string $text): string
    {
        return '<h1 style="margin:0 0 16px; font-family:' . self::FONT . '; font-size:21px;'
             . ' line-height:1.35; font-weight:700; color:#0f172a;">' . e($text) . '</h1>';
    }

    public static function altBaslik(string $text): string
    {
        return '<h2 style="margin:24px 0 10px; font-family:' . self::FONT . '; font-size:15px;'
             . ' font-weight:700; color:#0f172a;">' . e($text) . '</h2>';
    }

    /** @param bool $raw true ise metin HTML olarak basılır (dikkatli kullanın). */
    public static function paragraf(string $text, bool $raw = false): string
    {
        return '<p style="margin:0 0 14px; font-family:' . self::FONT . '; font-size:15px;'
             . ' line-height:1.65; color:#334155;">' . ($raw ? $text : nl2br(e($text))) . '</p>';
    }

    public static function kucukNot(string $text): string
    {
        return '<p style="margin:16px 0 0; font-family:' . self::FONT . '; font-size:13px;'
             . ' line-height:1.6; color:#64748b;">' . nl2br(e($text)) . '</p>';
    }

    /** Ortalanmış, dokunmatik ekranda rahat tıklanan eylem düğmesi. */
    public static function dugme(string $url, string $label): string
    {
        $renk = self::renk();

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">'
             . '<tr><td style="border-radius:10px; background:' . e($renk) . ';">'
             . '<a href="' . e($url) . '" style="display:inline-block; padding:12px 26px; font-family:' . self::FONT . ';'
             . ' font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">'
             . e($label) . '</a>'
             . '</td></tr></table>';
    }

    /**
     * Etiket/değer çiftlerinden okunaklı bir bilgi tablosu üretir.
     * İletişim bildiriminde "Ad: ... / E-posta: ..." gibi.
     *
     * @param array<string,string> $rows
     */
    public static function bilgiTablosu(array $rows): string
    {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
              . ' style="margin:8px 0 18px; border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; overflow:hidden;">';

        $index = 0;

        foreach ($rows as $label => $value) {
            $background = $index % 2 === 0 ? '#f8fafc' : '#ffffff';

            $html .= '<tr>'
                   . '<td style="padding:10px 14px; background:' . $background . '; font-family:' . self::FONT . ';'
                   . ' font-size:13px; color:#64748b; width:34%; vertical-align:top; white-space:nowrap;">' . e($label) . '</td>'
                   . '<td style="padding:10px 14px; background:' . $background . '; font-family:' . self::FONT . ';'
                   . ' font-size:14px; color:#0f172a; word-break:break-word;">' . nl2br(e($value)) . '</td>'
                   . '</tr>';

            $index++;
        }

        return $html . '</table>';
    }

    /** Alıntı kutusu: ziyaretçinin yazdığı mesajın gövdesi için. */
    public static function alinti(string $text): string
    {
        $renk = self::renk();

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px;">'
             . '<tr><td style="padding:16px 18px; background:#f8fafc; border-left:4px solid ' . e($renk) . ';'
             . ' border-radius:0 10px 10px 0; font-family:' . self::FONT . '; font-size:15px; line-height:1.7;'
             . ' color:#1f2937; white-space:pre-wrap; word-break:break-word;">' . e($text) . '</td></tr></table>';
    }

    public static function ayrac(): string
    {
        return '<div style="height:1px; background:#e2e8f0; margin:24px 0;"></div>';
    }

    /**
     * Yöneticinin panelden yazdığı serbest metni güvenli HTML'e çevirir.
     *
     * Metin OLDUĞU GİBİ basılmaz: satır sonları paragrafa döner,
     * "https://..." ile başlayan adresler tıklanabilir olur, geri
     * kalan her şey kaçışlanır. Böylece panele HTML yapıştıran bir
     * kullanıcı mektubun düzenini (ya da güvenliğini) bozamaz.
     */
    public static function metinBloklari(string $text): string
    {
        $renk   = self::renk();
        $bloklar = preg_split("/\n{2,}/", trim(str_replace(["\r\n", "\r"], "\n", $text))) ?: [];
        $html   = '';

        foreach ($bloklar as $blok) {
            if (trim($blok) === '') {
                continue;
            }

            $safe = nl2br(e($blok));

            // Adresleri tıklanabilir yap (kaçışlamadan SONRA, çünkü
            // artık metnin içinde HTML etiketi kalmadığından eminiz).
            $safe = (string) preg_replace(
                '#(https?://[^\s<]+)#i',
                '<a href="$1" style="color:' . $renk . '; text-decoration:underline;">$1</a>',
                $safe
            );

            $html .= self::paragraf($safe, true);
        }

        return $html;
    }
}
