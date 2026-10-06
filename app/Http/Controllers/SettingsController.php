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
        'genel'    => ['ikon' => 'settings', 'aciklama' => 'Site adı, açıklama, slogan, marka, logo ve favicon.'],
        'anasayfa' => ['ikon' => 'dashboard', 'aciklama' => 'Ana sayfanın bölümleri, başlığı, düğmeleri, özellik kartları ve sık sorulan sorular.'],
        'iletisim' => ['ikon' => 'phone',    'aciklama' => 'E-posta, telefon, WhatsApp, adres ve çalışma saatleri.'],
        'eposta'   => ['ikon' => 'send',     'aciklama' => 'SMTP bilgileri, gönderen adresi ve otomatik bildirimler.'],
        'sosyal'   => ['ikon' => 'globe',    'aciklama' => 'Alt bilgide görünecek sosyal medya bağlantıları.'],
        'seo'      => ['ikon' => 'search',   'aciklama' => 'İndeksleme, site haritası, robots.txt ve paylaşım görseli.'],
        'sistem'   => ['ikon' => 'server',   'aciklama' => 'Bakım modu, kayıt açık/kapalı, tema rengi ve zaman dilimi.'],
        'pwa'      => ['ikon' => 'mobil',    'aciklama' => 'Uygulama modu: künye (manifest) bilgileri, simge ve çevrimdışı çalışma.'],
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
            'anasayfa' => count(json_decode(Setting::get('anasayfa_bolumler', '[]'), true) ?: []) . ' bölüm görünüyor',
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
            'pwa'      => Setting::bool('pwa_aktif', false)
                ? 'Açık — ' . Setting::get('pwa_ad', Setting::get('site_adi', 'Uygulama'))
                    . (Setting::bool('pwa_cevrimdisi', true) ? ' · çevrimdışı çalışır' : ' · çevrimdışı kapalı')
                : 'Kapalı — site uygulama olarak kurulamaz',
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
        $errors = [];

        foreach ($rows as $row) {
            if ((int) $row['duzenlenebilir'] === 0) {
                continue;
            }

            $key   = (string) $row['anahtar'];
            $type  = (string) $row['tip'];
            $value = trim($request->string($key));

            if ($type === 'sifre') {
                /* PAROLA ALANLARI ekrana asla basılmaz; form her
                 * açıldığında boş gelir. Boş geleni kaydedersek kullanıcı
                 * sadece site adını değiştirdiğinde SMTP parolası
                 * silinirdi. SİLMEK için ayrı bir onay kutusu vardır;
                 * eskiden bir kez girilen parola hiç kaldırılamıyordu. */
                if ($request->bool($key . '__sil')) {
                    $values[$key] = '';

                    continue;
                }

                if ($value === '') {
                    continue;
                }
            }

            if ($type === 'onay') {
                $values[$key] = $request->bool($key) ? '1' : '0';

                continue;
            }

            /* coklu / liste: formdan DİZİ gelir, JSON olarak saklanır. */
            if ($type === 'coklu' || $type === 'liste') {
                [$json, $error] = $type === 'coklu'
                    ? self::validateChoices($row, $request->raw($key, []))
                    : self::validateList($row, $request->raw($key, []));

                if ($error !== null) {
                    $errors[$key] = $error;
                } else {
                    $values[$key] = $json;
                }

                continue;
            }

            $error = $this->validateSetting($row, $value);

            if ($error !== null) {
                $errors[$key] = $error;

                continue;
            }

            $values[$key] = $value;
        }

        /* Tek bir alan bile geçersizse HİÇBİR ŞEY kaydedilmez: yarım
         * kaydedilmiş bir form, kullanıcının hangi değişikliğin tuttuğunu
         * bilememesi demektir. Girilen değerler forma geri döner
         * (parolalar hariç). */
        if ($errors !== []) {
            $old = [];

            foreach ($rows as $row) {
                $anahtar = (string) $row['anahtar'];

                if (in_array($row['tip'], ['coklu', 'liste'], true)) {
                    $ham = $request->raw($anahtar, []);
                    $old[$anahtar] = (string) json_encode(is_array($ham) ? array_values($ham) : [], JSON_UNESCAPED_UNICODE);
                } elseif ($row['tip'] !== 'sifre') {
                    $old[$anahtar] = $request->string($anahtar);
                }
            }

            Flash::error('Bazı alanlar geçersiz; hiçbir ayar kaydedilmedi.');
            Flash::withInput($errors, $old);
            Response::redirect(url('panel/ayarlar/' . $grup));
        }

        Setting::saveMany($this->db, $values);

        Logger::info('Ayarlar güncellendi', ['grup' => $grup, 'alan' => count($values)], 'app');

        Flash::success(count($values) . ' ayar kaydedildi.');
        Response::redirect(url('panel/ayarlar/' . $grup));
    }

    /**
     * "coklu" ayar: yalnızca tanımlı seçenekler kabul edilir; sıra
     * seçenek listesinin sırasıdır (formdaki işaretleme sırası değil).
     *
     * @param array<string,mixed> $row
     * @return array{0:string,1:?string} [JSON, hata]
     */
    private static function validateChoices(array $row, mixed $raw): array
    {
        $secenekler = json_decode((string) ($row['secenekler'] ?? '{}'), true) ?: [];
        $gelen      = is_array($raw) ? array_filter($raw, 'is_string') : [];
        $secilen    = array_values(array_intersect(array_keys($secenekler), $gelen));

        return [(string) json_encode($secilen), null];
    }

    /**
     * "liste" ayar: satır satır JSON. Alanlar "secenekler"deki tanımdan
     * gelir; tanımda olmayan alan atılır, tamamen boş satır silinir.
     *
     * Alan tipleri: metin (≤150), uzun (≤500), ikon (icon_names()
     * içinden), adres (site içi yol ya da http/https).
     *
     * @param array<string,mixed> $row
     * @return array{0:string,1:?string} [JSON, hata]
     */
    private static function validateList(array $row, mixed $raw): array
    {
        $tanim   = json_decode((string) ($row['secenekler'] ?? '{}'), true) ?: [];
        $alanlar = is_array($tanim['alanlar'] ?? null) ? $tanim['alanlar'] : [];
        $enFazla = max(1, (int) ($tanim['en_fazla'] ?? 20));
        $label   = (string) $row['etiket'];
        $satirlar = [];

        foreach (is_array($raw) ? $raw : [] as $satir) {
            if (!is_array($satir)) {
                continue;
            }

            $temiz = [];

            foreach ($alanlar as $alan) {
                $ad    = (string) ($alan['ad'] ?? '');
                $tip   = (string) ($alan['tip'] ?? 'metin');
                $deger = is_string($satir[$ad] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $satir[$ad])) : '';

                if ($deger !== '' && !mb_check_encoding($deger, 'UTF-8')) {
                    return ['', $label . ': geçersiz karakter.'];
                }

                $sinir = $tip === 'uzun' ? 500 : 150;

                if (mb_strlen($deger, 'UTF-8') > $sinir) {
                    return ['', sprintf('%s: "%s" en fazla %d karakter olabilir.', $label, (string) ($alan['etiket'] ?? $ad), $sinir)];
                }

                if ($deger !== '' && $tip === 'ikon' && !in_array($deger, icon_names(), true)) {
                    return ['', $label . ': bilinmeyen ikon "' . $deger . '".'];
                }

                if ($deger !== '' && $tip === 'adres' && !self::isSafeLink($deger)) {
                    return ['', $label . ': adres site içi bir yol (örn. iletisim) ya da https:// ile başlayan bir adres olmalıdır.'];
                }

                $temiz[$ad] = $deger;
            }

            if (implode('', $temiz) !== '') {
                $satirlar[] = $temiz;
            }
        }

        if (count($satirlar) > $enFazla) {
            return ['', sprintf('%s en fazla %d satır olabilir.', $label, $enFazla)];
        }

        return [(string) json_encode($satirlar, JSON_UNESCAPED_UNICODE), null];
    }

    /** Site içi yol ya da http(s) adresi — "javascript:" gibi şemalar reddedilir. */
    public static function isSafeLink(string $value): bool
    {
        if (self::isHttpUrl($value)) {
            return true;
        }

        return preg_match('#^[A-Za-z0-9/_.\#?=&-]*\z#', $value) === 1
            && !str_contains($value, '..')
            && !str_starts_with($value, '//');
    }

    /**
     * Tek bir ayar değerini TİPİNE (ve gerekiyorsa anahtarına) göre
     * doğrular.
     *
     * Eskiden hiçbir doğrulama yoktu: "url" tipindeki sosyal medya
     * alanlarına "javascript:alert(1)" yazılabiliyor, alt bilgideki
     * bağlantıya tıklayan ziyaretçinin tarayıcısında çalışıyordu.
     * "Gönderen Adresi" alanı ise doğrudan sendmail'e argüman olarak
     * gidiyordu (bkz. NativeTransport::safeEnvelopeSender).
     *
     * @param array<string,mixed> $row
     * @return string|null Hata mesajı; geçerliyse null
     */
    private function validateSetting(array $row, string $value): ?string
    {
        $key   = (string) $row['anahtar'];
        $type  = (string) $row['tip'];
        $label = (string) $row['etiket'];

        /* Anahtara özel kurallar — tipten daha sıkıdır. */
        switch ($key) {
            case 'sistem_zaman_dilimi':
                return $value === '' || in_array($value, timezone_identifiers_list(), true)
                    ? null
                    : 'Geçerli bir saat dilimi yazın (örn. Europe/Istanbul).';

            case 'iletisim_harita':
                return $value === '' || self::isHttpUrl($value)
                    ? null
                    : 'Harita bağlantısı http:// ya da https:// ile başlayan bir adres olmalıdır.';

            case 'iletisim_whatsapp':
                return preg_match('/^[+0-9 ()-]{0,30}\z/', $value) === 1
                    ? null
                    : 'WhatsApp numarası yalnızca rakam, boşluk, +, - ve parantez içerebilir.';

            case 'pwa_baslangic':
                return $value === '' || \App\Core\Middleware::safeIntended($value) !== ''
                    ? null
                    : 'Açılış adresi site içinde bir yol olmalıdır (örn. panel). Tam adres yazmayın.';

            case 'seo_og_gorsel':
                return $value === ''
                    || self::isHttpUrl($value)
                    || (preg_match('#^[A-Za-z0-9/_.-]+\z#', $value) === 1 && !str_contains($value, '..'))
                    ? null
                    : 'Paylaşım görseli upload/ altındaki bir dosya adı ya da https:// ile başlayan bir adres olmalıdır.';

            case 'seo_google_dogrulama':
                return preg_match('/^[A-Za-z0-9_-]{0,100}\z/', $value) === 1
                    ? null
                    : 'Yalnızca doğrulama kodunu yazın (etiketin tamamını değil).';

            case 'anasayfa_birincil_adres':
            case 'anasayfa_ikincil_adres':
                return $value === '' || self::isSafeLink($value)
                    ? null
                    : 'Adres site içi bir yol (örn. iletisim) ya da https:// ile başlayan bir adres olmalıdır.';

            case 'anasayfa_gorsel':
                return $value === '' || $value === 'vitrin' || self::isHttpUrl($value)
                    || (preg_match('#^[A-Za-z0-9/_.-]+\z#', $value) === 1 && !str_contains($value, '..'))
                    ? null
                    : 'Görsel boş, "vitrin", upload/ altındaki bir dosya adı ya da https:// adresi olmalıdır.';

            case 'sistem_kvkk_sayfa':
                return preg_match('/^[a-z0-9-]{0,190}\z/', $value) === 1
                    ? null
                    : 'Sayfanın adresini (slug) yazın, örn. gizlilik-ve-kvkk.';

            case 'sistem_ip_saklama':
                return ctype_digit($value) && (int) $value <= 3650
                    ? null
                    : '0 ile 3650 arasında bir gün sayısı yazın.';

            case 'mail_host':
                return $value === '' || preg_match('/^[A-Za-z0-9.-]{1,253}\z/', $value) === 1
                    ? null
                    : 'SMTP sunucusu yalnızca alan adı ya da IP olmalıdır (örn. smtp.gmail.com).';

            case 'mail_gonderen':
                return $value === '' || \App\Core\Mail\NativeTransport::safeEnvelopeSender($value) !== ''
                    ? null
                    : 'Gönderen adresi geçerli ve sade bir e-posta adresi olmalıdır (boşluk ya da özel karakter içeremez).';
        }

        $sayiSiniri = [
            'mail_port'           => [1, 65535],
            'mail_parti_boyutu'   => [1, 200],
            'sistem_sayfa_basina' => [5, 500],
        ];

        return match ($type) {
            'eposta' => $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : $label . ' geçerli bir e-posta adresi olmalıdır.',

            'url' => $value === '' || self::isHttpUrl($value)
                ? null
                : $label . ' http:// ya da https:// ile başlayan geçerli bir adres olmalıdır.',

            'sayi' => (function () use ($value, $key, $label, $sayiSiniri): ?string {
                [$min, $max] = $sayiSiniri[$key] ?? [0, 1_000_000];

                return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) !== false
                    ? null
                    : sprintf('%s %d ile %d arasında bir tam sayı olmalıdır.', $label, $min, $max);
            })(),

            'secim' => in_array($value, (array) (json_decode((string) ($row['secenekler'] ?? '[]'), true) ?: []), true)
                ? null
                : $label . ' için listedeki seçeneklerden birini seçin.',

            'renk' => preg_match('/^#[0-9a-fA-F]{6}\z/', $value) === 1
                ? null
                : $label . ' #RRGGBB biçiminde bir renk olmalıdır.',

            'sifre' => mb_strlen($value) <= 255 && !preg_match('/[\r\n]/', $value)
                ? null
                : $label . ' en fazla 255 karakter olabilir ve satır sonu içeremez.',

            'uzun_metin' => mb_strlen($value) <= ($key === 'seo_analytics' ? 20000 : 5000)
                ? null
                : $label . ' çok uzun.',

            default => mb_strlen($value) <= 500 && !preg_match('/[\r\n]/', $value)
                ? null
                : $label . ' en fazla 500 karakter olabilir ve satır sonu içeremez.',
        };
    }

    /** Yalnızca http(s) şemalı, geçerli bir mutlak adres mi? */
    private static function isHttpUrl(string $value): bool
    {
        return preg_match('#^https?://#i', $value) === 1
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
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

    /* =================================================================
     *  PWA UYGULAMA SİMGESİ
     * -----------------------------------------------------------------
     *  Favicondan da logodan da AYRIDIR: telefonun ana ekranındaki
     *  simge 512 piksele kadar büyütülür ve Android onu daire/kare
     *  kalıba göre kenarlarından kırpar. Yatay bir logo orada başsız
     *  görünür. Yüklenen görsel kare kırpılıp 512 piksele indirilir.
     * ============================================================== */

    public function uploadIcon(Request $request): void
    {
        $geri = url('panel/ayarlar/pwa');

        if (!$request->hasFile('pwa_simge')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect($geri);
        }

        try {
            $yeni = Uploader::pwaIcon((array) $request->file('pwa_simge'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect($geri);
        }

        $eski = Setting::get('pwa_simge');

        Setting::set($this->db, 'pwa_simge', $yeni, 'dahili');

        if ($eski !== '' && $eski !== $yeni) {
            Uploader::delete($eski);
        }

        Flash::success('Uygulama simgesi güncellendi. Telefona zaten kurulmuş uygulamalarda simge, uygulama yeniden kurulana kadar eski kalabilir.');
        Response::redirect($geri);
    }

    public function removeIcon(Request $request): void
    {
        $mevcut = Setting::get('pwa_simge');

        if ($mevcut !== '') {
            Setting::set($this->db, 'pwa_simge', '', 'dahili');
            Uploader::delete($mevcut);

            Flash::success('Uygulama simgesi kaldırıldı; site logosu kullanılacak.');
        }

        Response::redirect(url('panel/ayarlar/pwa'));
    }
}
