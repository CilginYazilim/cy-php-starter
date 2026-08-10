<?php
/**
 * =====================================================================
 *  E-POSTA DÜZENİ – Bütün mektupların ortak çerçevesi
 * ---------------------------------------------------------------------
 *  E-POSTA HTML'İ, WEB HTML'İ DEĞİLDİR. Outlook 2016 hâlâ Word'ün
 *  düzen motorunu kullanır; Gmail <style> bloğunu kırpar. Bu yüzden:
 *
 *    • Düzen <table> ile kurulur (flex/grid çalışmaz).
 *    • Stiller SATIR İÇİ yazılır (style="..."), sınıf kullanılmaz.
 *    • Genişlik 600 pikseli geçmez (eski istemcilerin güvenli sınırı).
 *    • Görseller MUTLAK adres ister; "assets/logo.png" işe yaramaz.
 *
 *  @var string $content  İç şablonun ürettiği HTML
 *  @var string $mailKonu Mektubun konusu
 *  @var string $onizleme Gelen kutusunda görünen ön izleme metni
 * =====================================================================
 */

use App\Core\Mail\Mailer;
use App\Core\Setting;

$siteAdi   = Setting::get('site_adi', 'Site');
$renk      = Setting::get('sistem_tema_rengi', '#0b5cb5');
$logo      = Mailer::absolute(Setting::logoUrl());
$siteAdres = Mailer::absolute('');
$altBilgi  = Setting::get('mail_alt_bilgi');
$mailKonu  = $mailKonu ?? $siteAdi;
$onizleme  = $onizleme ?? $mailKonu;
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($mailKonu) ?></title>
</head>
<body style="margin:0; padding:0; background:#f1f4f8; -webkit-font-smoothing:antialiased;">

<!-- Önizleme metni: gelen kutusunda konunun yanında görünen gri yazı.
     Ekranda GÖRÜNMEZ ama listede okunur. data-preheader işareti,
     düz metin sürümü üretilirken bu bloğun atlanmasını sağlar
     (bkz. Mailable::htmlToText) — yoksa konu iki kez yazılırdı. -->
<div data-preheader style="display:none; max-height:0; overflow:hidden; opacity:0;"><?= e($onizleme) ?></div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f4f8; padding:24px 12px;">
<tr>
<td align="center">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
           style="width:600px; max-width:100%; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0;">

        <!-- ÜST ŞERİT -->
        <tr>
            <td style="background:<?= e($renk) ?>; padding:22px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="vertical-align:middle;">
                        <a href="<?= e($siteAdres) ?>" style="text-decoration:none; color:#ffffff;">
                            <img src="<?= e($logo) ?>" alt="" width="36" height="36"
                                 style="display:inline-block; vertical-align:middle; border:0; border-radius:8px; background:#ffffff;">
                            <span style="display:inline-block; vertical-align:middle; margin-left:10px;
                                         font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                                         font-size:18px; font-weight:700; color:#ffffff;"><?= e($siteAdi) ?></span>
                        </a>
                    </td>
                </tr>
                </table>
            </td>
        </tr>

        <!-- İÇERİK -->
        <tr>
            <td style="padding:32px 28px; font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                       font-size:15px; line-height:1.65; color:#1f2937;">
                <?= $content ?>
            </td>
        </tr>

        <!-- ALT BİLGİ -->
        <tr>
            <td style="padding:20px 28px; background:#f8fafc; border-top:1px solid #e2e8f0;
                       font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                       font-size:12px; line-height:1.6; color:#64748b;">
                <?php if ($altBilgi !== ''): ?>
                    <p style="margin:0 0 6px;"><?= nl2br(e($altBilgi)) ?></p>
                <?php endif; ?>

                <p style="margin:0;">
                    Bu e-posta <a href="<?= e($siteAdres) ?>" style="color:<?= e($renk) ?>; text-decoration:none;"><?= e($siteAdi) ?></a>
                    tarafından gönderildi.
                </p>
                <p style="margin:6px 0 0; color:#94a3b8;">
                    &copy; <?= date('Y') ?> <?= e($siteAdi) ?> · Bu iletiye yanıt vermeniz gerekmiyorsa görmezden gelebilirsiniz.
                </p>
            </td>
        </tr>
    </table>

</td>
</tr>
</table>

</body>
</html>
