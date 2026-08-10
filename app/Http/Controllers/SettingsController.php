<?php
/**
 * =====================================================================
 *  SettingsController – Site Ayarları
 * ---------------------------------------------------------------------
 *  HER GRUP AYRI BİR SAYFADIR:
 *
 *      panel/ayarlar            → gruplara genel bakış
 *      panel/ayarlar/genel      → yalnızca "genel" grubu
 *      panel/ayarlar/eposta     → yalnızca "eposta" grubu
 *
 *  NEDEN SEKME DEĞİL AYRI SAYFA?
 *   · Adres paylaşılabilir ve yer imine eklenebilir
 *   · Sayfa yalnızca ilgili alanları basar (42 alan yerine 8)
 *   · KAYDETME KAPSAMI DARALIR: "E-posta" sayfasını kaydetmek SEO
 *     ayarlarına dokunmaz. Tek dev formda her şey gönderildiğinde,
 *     bir sekmedeki eski değer başka bir sekmedeki ayarı sessizce
 *     geri alabiliyordu.
 *   · Mobilde altı sekmeyi yatay kaydırmak gerekmez
 *
 *  Form yine "ayarlar" tablosundan OTOMATİK üretilir; yeni bir ayar
 *  eklemek için tabloya satır eklemek yeterlidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Exceptions\HttpException;
use App\Core\Flash;
use App\Core\Log\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Core\Uploader;
use App\Http\Controller;
use RuntimeException;

final class SettingsController extends Controller
{
    /**
     * Grup künyeleri: ikon ve bir cümlelik açıklama.
     * Etiketler Setting::groupLabels() içinden gelir.
     *
     * @var array<string,array{ikon:string,aciklama:string}>
     */
    private const GROUP_META = [
        'genel'    => ['ikon' => 'settings', 'aciklama' => 'Site adı, açıklama, slogan, dil, logo ve favicon.'],
        'iletisim' => ['ikon' => 'phone',    'aciklama' => 'E-posta, telefon, WhatsApp, adres ve çalışma saatleri.'],
        'eposta'   => ['ikon' => 'send',     'aciklama' => 'SMTP bilgileri, gönderen adresi ve otomatik bildirimler.'],
        'sosyal'   => ['ikon' => 'globe',    'aciklama' => 'Alt bilgide görünecek sosyal medya bağlantıları.'],
        'seo'      => ['ikon' => 'search',   'aciklama' => 'İndeksleme, site haritası, robots.txt ve paylaşım görseli.'],
        'sistem'   => ['ikon' => 'server',   'aciklama' => 'Bakım modu, kayıt açık/kapalı, tema rengi, PWA.'],
    ];

    /* =================================================================
     *  GENEL BAKIŞ
     * ============================================================== */

    public function index(Request $request): void
    {
        $groups = Setting::grouped($this->db);
        $labels = Setting::groupLabels();

        $kartlar = [];

        foreach ($groups as $key => $rows) {
            $kartlar[] = [
                'anahtar'  => $key,
                'baslik'   => $labels[$key] ?? ucfirst($key),
                'ikon'     => self::GROUP_META[$key]['ikon'] ?? 'settings',
                'aciklama' => self::GROUP_META[$key]['aciklama'] ?? '',
                'adet'     => count($rows),
                'ozet'     => $this->groupSummary($key),
                'uyari'    => $this->groupWarning($key),
            ];
        }

        $this->view('settings/index', [
            'title'    => 'Site Ayarları',
            'subtitle' => 'Ayarlar konularına göre ayrılmıştır; düzenlemek istediğiniz bölümü seçin.',
            'kartlar'  => $kartlar,
            'toplam'   => array_sum(array_map('count', $groups)),
        ]);
    }

    /**
     * Kartta gösterilecek "şu an ne durumda?" özeti.
     *
     * Kullanıcının kartı açmadan önemli bilgiyi görmesini sağlar —
     * örneğin e-postanın hâlâ "kayıt" modunda olduğunu.
     */
    private function groupSummary(string $group): string
    {
        return match ($group) {
            'genel'    => Setting::get('site_adi', '—'),
            'iletisim' => Setting::get('iletisim_eposta') === ''
                ? 'İletişim e-postası tanımlı değil'
                : Setting::get('iletisim_eposta')
                    . (Setting::get('iletisim_whatsapp') !== '' ? ' · WhatsApp açık' : ''),
            'eposta'   => match (Setting::get('mail_surucu', 'kayit')) {
                'smtp'  => 'SMTP · ' . Setting::get('mail_host', '—'),
                'php'   => 'PHP mail()',
                default => 'Kayıt modu — mektuplar gönderilmiyor',
            },
            'sosyal'   => $this->socialCount() . ' bağlantı tanımlı',
            'seo'      => Setting::bool('seo_indeksleme', true)
                ? 'Arama motorlarına açık'
                . (Setting::bool('seo_sitemap_aktif', true) ? ' · site haritası üretiliyor' : ' · site haritası kapalı')
                : 'Arama motorlarına KAPALI — yayına çıkmadan açın',
            'sistem'   => Setting::bool('sistem_bakim_modu', false) ? 'Bakım modu açık' : 'Site yayında',
            default    => '',
        };
    }

    /** Kartta kırmızı/turuncu uyarı göstermeli miyiz? */
    private function groupWarning(string $group): bool
    {
        return match ($group) {
            'eposta'   => Setting::get('mail_surucu', 'kayit') === 'kayit',
            'iletisim' => Setting::get('iletisim_eposta') === '',
            'seo'      => !Setting::bool('seo_indeksleme', true),
            'sistem'   => Setting::bool('sistem_bakim_modu', false),
            default    => false,
        };
    }

    private function socialCount(): int
    {
        $sayi = 0;

        foreach (Setting::all() as $anahtar => $deger) {
            if (str_starts_with($anahtar, 'sosyal_') && trim($deger) !== '') {
                $sayi++;
            }
        }

        return $sayi;
    }

    /* =================================================================
     *  TEK GRUP
     * ============================================================== */

    public function group(Request $request, string $grup): void
    {
        $rows   = $this->groupRows($grup);
        $labels = Setting::groupLabels();

        $this->view('settings/group', [
            'title'    => ($labels[$grup] ?? ucfirst($grup)) . ' Ayarları',
            'subtitle' => self::GROUP_META[$grup]['aciklama'] ?? '',
            'grup'     => $grup,
            'baslik'   => $labels[$grup] ?? ucfirst($grup),
            'ikon'     => self::GROUP_META[$grup]['ikon'] ?? 'settings',
            'rows'     => $rows,
            'errors'   => Flash::errors(),
            'old'      => Flash::old(),
            'scripts'  => ['settings.js'],
        ]);
    }

    /**
     * Grubun satırları — grup yoksa 404.
     *
     * @return array<int,array<string,mixed>>
     */
    private function groupRows(string $grup): array
    {
        $groups = Setting::grouped($this->db);

        if (!array_key_exists($grup, $groups)) {
            throw HttpException::notFound('panel/ayarlar/' . $grup);
        }

        return $groups[$grup];
    }

    /* HIZLI GEÇİŞ ÇUBUĞU KALDIRILDI.
     *
     * Her ayar sayfasının üstünde altı bölümü de listeleyen bir düğme
     * satırı vardı. Sol menüde "Site Ayarları" zaten aynı altı alt
     * bağlantıyı açıyor; aynı gezinmeyi iki kez çizmek ekranın üst
     * şeridini gereksiz yere dolduruyor, mobilde ise formun kendisini
     * kaydırma gerektiren bir yere itiyordu. */

    /* =================================================================
     *  KAYDETME
     * ============================================================== */

    /**
     * YALNIZCA verilen grubun ayarlarını kaydeder.
     *
     * Kapsamı daraltmak bilinçlidir: "E-posta" sayfasını kaydetmek
     * SEO ayarlarına dokunamaz.
     */
    public function update(Request $request, string $grup): void
    {
        $rows   = $this->groupRows($grup);
        $values = [];

        foreach ($rows as $row) {
            if ((int) $row['duzenlenebilir'] === 0) {
                continue;
            }

            $key   = (string) $row['anahtar'];
            $value = trim((string) ($_POST[$key] ?? ''));

            /* PAROLA ALANLARI ekrana asla basılmaz; form her açıldığında
             * boş gelir. Boş geleni kaydedersek kullanıcı sadece site
             * adını değiştirdiğinde SMTP parolası silinirdi. */
            if ($row['tip'] === 'sifre' && $value === '') {
                continue;
            }

            $values[$key] = $row['tip'] === 'onay'
                ? ($request->bool($key) ? '1' : '0')
                : $value;
        }

        Setting::saveMany($this->db, $values);

        Logger::info('Ayarlar güncellendi', ['grup' => $grup, 'alan' => count($values)], 'app');

        Flash::success(count($values) . ' ayar kaydedildi.');
        Response::redirect(url('panel/ayarlar/' . $grup));
    }

    /* =================================================================
     *  LOGO
     * ============================================================== */

    public function uploadLogo(Request $request): void
    {
        $geri = url('panel/ayarlar/genel');

        if (!$request->hasFile('logo')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect($geri);
        }

        try {
            $newFile = Uploader::logo((array) $request->file('logo'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect($geri);
        }

        $old = Setting::get('site_logo');

        Setting::set($this->db, 'site_logo', $newFile);

        if ($old !== '' && $old !== $newFile) {
            Uploader::delete($old);
        }

        Flash::success('Logo güncellendi.');
        Response::redirect($geri);
    }

    public function removeLogo(Request $request): void
    {
        $mevcut = Setting::get('site_logo');

        if ($mevcut !== '') {
            Setting::set($this->db, 'site_logo', '', 'dahili');
            Uploader::delete($mevcut);

            Flash::success('Logo kaldırıldı; varsayılan logo kullanılacak.');
        }

        Response::redirect(url('panel/ayarlar/genel'));
    }

    /* =================================================================
     *  FAVICON
     * -----------------------------------------------------------------
     *  Logodan AYRI bir dosyadır ve öyle olmalıdır: sekmede görünen
     *  simge 16 pikseldir, yatay bir logo orada okunmaz. Yüklenen
     *  görsel kare kırpılıp 256 piksele indirilir.
     * ============================================================== */

    public function uploadFavicon(Request $request): void
    {
        $geri = url('panel/ayarlar/genel');

        if (!$request->hasFile('favicon')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect($geri);
        }

        try {
            $yeni = Uploader::favicon((array) $request->file('favicon'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect($geri);
        }

        $eski = Setting::get('site_favicon');

        Setting::set($this->db, 'site_favicon', $yeni, 'dahili');

        if ($eski !== '' && $eski !== $yeni) {
            Uploader::delete($eski);
        }

        Flash::success('Favicon güncellendi. Tarayıcı eski simgeyi önbellekte tutabilir; sayfayı Ctrl+F5 ile yenileyin.');
        Response::redirect($geri);
    }

    public function removeFavicon(Request $request): void
    {
        $mevcut = Setting::get('site_favicon');

        if ($mevcut !== '') {
            Setting::set($this->db, 'site_favicon', '', 'dahili');
            Uploader::delete($mevcut);

            Flash::success('Favicon kaldırıldı; varsayılan simge kullanılacak.');
        }

        Response::redirect(url('panel/ayarlar/genel'));
    }
}
