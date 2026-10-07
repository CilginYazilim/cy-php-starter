<?php
/**
 * =====================================================================
 *  DemoData – Örnek verinin TEK KAYNAĞI
 * ---------------------------------------------------------------------
 *  Kurulum sihirbazındaki "Örnek veriyle kur", "php cy db:seed"
 *  (database/seeders/DemoSeeder.php), canlı demonun 3 saatte bir
 *  sıfırlanması ("php cy demo:reset") ve "demo verisini kaldır"
 *  ("php cy demo:temizle", Panel → Sistem) hepsi bu sınıfı kullanır.
 *  Eskiden örnek veri kurulum/demo.sql'deydi; komut satırından ve
 *  sıfırlama görevinden aynı veriye ulaşmanın yolu yoktu.
 *
 *  NE KURAR?
 *    · 6 örnek hesap (Demo::HESAPLAR) — 14 güne yayılmış kayıt tarihleri
 *    · Sayfalar: Hakkımızda, Başlarken, Özellikler (+ var olan KVKK)
 *    · 9 iletişim mesajı, 7 e-posta kaydı (gönderildi/başarısız/kuyrukta)
 *    · ÇILGIN Yazılım markalı ayarlar ve ana sayfa vitrini
 *    · Paylaşım (OG) görseli ve API için örnek dosyalar
 *  Örnek Modül kayıtları modülün kendi seeder'ından gelir.
 *
 *  TEKRAR ÇALIŞTIRMAK ÇOĞALTMAZ: hesaplar kullanıcı adıyla, sayfalar
 *  adresle güncellenir; demo mesajları ve e-postaları silinip yeniden
 *  yazılır (yalnızca *.demo@ornek.com adresliler).
 *
 *  MARKA YALNIZCA BURADA. kurulum/database.sql nötrdür ("Yeni Proje");
 *  şablonu kendi projesine alan geliştiricinin sitesine markamız zorla
 *  gömülmez — örnek veri seçilmezse hiçbiri yazılmaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Support\Surum16;
use Closure;
use PDO;
use Throwable;

final class DemoData
{
    /** Demo mesaj/e-posta adreslerinin ortak soneki — temizlik bunu arar. */
    public const EPOSTA_SONEKI = '.demo@ornek.com';

    /** Demo sayfaları (DemoData'nın oluşturduğu, kaldırırken silinenler). */
    public const SAYFALAR = ['baslarken', 'ozellikler'];

    /**
     * Örnek verinin İÇERİĞİNİ değiştirdiği korumalı sayfaların kurulumdaki
     * nötr hâli. remove() sayfayı, içeriği hâlâ örnek metinse buna
     * döndürür; yönetici değiştirdiyse dokunmaz. Eskiden Hakkımızda örnek
     * veri kaldırıldıktan sonra da ÇILGIN Yazılım tanıtımıyla kalıyordu.
     *
     * kurulum/database.sql'deki INSERT ile birebir aynıdır; tests/unit.php
     * denetler.
     */
    public const NOTR_SAYFALAR = [
        'hakkimizda' => [
            'baslik'       => 'Hakkımızda',
            'ozet'         => 'Kim olduğumuzu, ne yaptığımızı ve nasıl çalıştığımızı anlatan kısa bir tanıtım.',
            'icerik'       => '<h2>Biz kimiz?</h2><p>Bu metni <strong>Panel → Sayfalar → Hakkımızda</strong> ekranından değiştirebilirsiniz. Zengin metin editörü başlık, kalın/italik yazı, listeler, bağlantılar ve alıntı desteği sunar.</p><h3>Ne yapıyoruz?</h3><ul><li>İhtiyaca göre kurumsal web çözümleri geliştiriyoruz.</li><li>Var olan sistemleri bakım ve destek altına alıyoruz.</li><li>Sürecin her adımında ölçülebilir sonuç hedefliyoruz.</li></ul><h3>Neden biz?</h3><p>İşimizi sade, hızlı ve sürdürülebilir yapmaya çalışıyoruz. Sorularınız için <a href="iletisim">iletişim sayfamızdan</a> bize yazabilirsiniz.</p>',
            'seo_aciklama' => '',
        ],
    ];

    /**
     * Örnek verinin yazdığı MARKA ayarları => kurulum/database.sql'deki
     * (ya da Surum16'daki) nötr değer. remove() bunları, değer hâlâ
     * örnek değerle aynıysa nötre döndürür. Site adı bilerek YOK: kurulumda
     * yöneticinin seçtiği addır, kaldırmak siteyi adsız bırakırdı.
     * tests/unit.php nötr değerlerin kurulumla aynı olduğunu denetler.
     */
    public const MARKA_AYARLARI = [
        'site_slogan'           => '',
        'site_aciklama'         => '',
        'site_marka'            => '',
        'sosyal_github'         => '',
        'sosyal_x'              => '',
        'sosyal_facebook'       => '',
        'sosyal_instagram'      => '',
        'seo_anahtar_kelimeler' => '',
        'pwa_ad'                => '',
        'pwa_kisa_ad'           => '',
        'pwa_aciklama'          => '',
        'mail_gonderen_adi'     => '',
        'iletisim_saatler'      => '',
        'iletisim_adres'        => '',
    ];

    private ?Closure $say;

    public function __construct(private PDO $db, ?callable $say = null)
    {
        $this->say = $say !== null ? Closure::fromCallable($say) : null;
    }

    /* =================================================================
     *  KUR
     * ============================================================== */

    /** @return array<string,int> ne kadar yazıldı */
    public function seed(): array
    {
        $sayilar = [
            'hesap'  => $this->seedUsers(),
            'sayfa'  => $this->seedPages(),
            'mesaj'  => $this->seedMessages(),
            'eposta' => $this->seedMails(),
            'ayar'   => $this->seedSettings(),
        ];

        $this->copyAssets();

        $this->report(sprintf(
            '%d hesap, %d sayfa, %d mesaj, %d e-posta kaydı, %d ayar.',
            $sayilar['hesap'], $sayilar['sayfa'], $sayilar['mesaj'], $sayilar['eposta'], $sayilar['ayar']
        ));

        return $sayilar;
    }

    private function seedUsers(): int
    {
        $hash = password_hash(Demo::PAROLA, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO kullanicilar
                (ad, soyad, kullanici_adi, eposta, sifre, rol, durum, telefon, hakkinda,
                 son_giris, son_giris_ip, giris_sayisi, created_at)
             VALUES
                (:ad, :soyad, :kadi, :eposta, :sifre, :rol, :durum, :telefon, :hakkinda,
                 :son_giris, :ip, :giris, NOW() - INTERVAL :gun DAY)
             ON DUPLICATE KEY UPDATE
                ad = VALUES(ad), soyad = VALUES(soyad), sifre = VALUES(sifre), rol = VALUES(rol),
                durum = VALUES(durum), telefon = VALUES(telefon), hakkinda = VALUES(hakkinda),
                avatar = \'\', hatirla_token = NULL, hatirla_bitis = NULL'
        );

        $adet = 0;

        foreach (Demo::HESAPLAR as $kadi => $hesap) {
            $girisYapabilir = $hesap['durum'] === 'aktif';

            $stmt->execute([
                ':ad'        => $hesap['ad'],
                ':soyad'     => $hesap['soyad'],
                ':kadi'      => $kadi,
                ':eposta'    => $hesap['eposta'],
                ':sifre'     => $hash,
                ':rol'       => $hesap['rol'],
                ':durum'     => $hesap['durum'],
                ':telefon'   => $hesap['telefon'],
                ':hakkinda'  => $hesap['hakkinda'],
                ':son_giris' => $girisYapabilir ? date('Y-m-d H:i:s', time() - $hesap['gun'] * 3600) : null,
                ':ip'        => $girisYapabilir ? '127.0.0.1' : '',
                ':giris'     => $girisYapabilir ? 3 + $hesap['gun'] : 0,
                ':gun'       => $hesap['gun'],
            ]);

            $adet++;
        }

        return $adet;
    }

    private function seedPages(): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sayfalar (baslik, slug, ozet, icerik, durum, menude, korumali, sira, seo_aciklama)
             VALUES (:baslik, :slug, :ozet, :icerik, \'yayin\', :menude, :korumali, :sira, :seo)
             ON DUPLICATE KEY UPDATE baslik = VALUES(baslik), ozet = VALUES(ozet), icerik = VALUES(icerik),
                durum = \'yayin\', menude = VALUES(menude), sira = VALUES(sira), seo_aciklama = VALUES(seo_aciklama)'
        );

        foreach (self::pages() as $sayfa) {
            $stmt->execute([
                ':baslik'   => $sayfa['baslik'],
                ':slug'     => $sayfa['slug'],
                ':ozet'     => $sayfa['ozet'],
                ':icerik'   => $sayfa['icerik'],
                ':menude'   => $sayfa['menude'],
                ':korumali' => $sayfa['korumali'],
                ':sira'     => $sayfa['sira'],
                ':seo'      => $sayfa['seo'],
            ]);
        }

        return count(self::pages());
    }

    private function seedMessages(): int
    {
        $this->db->prepare('DELETE FROM mesajlar WHERE eposta LIKE :sonek')
            ->execute([':sonek' => '%' . self::EPOSTA_SONEKI]);

        $stmt = $this->db->prepare(
            'INSERT INTO mesajlar (ad, eposta, konu, mesaj, okundu, ip, tarayici, created_at)
             VALUES (:ad, :eposta, :konu, :mesaj, :okundu, \'127.0.0.1\', \'Demo verisi\', NOW() - INTERVAL :saat HOUR)'
        );

        foreach (self::MESAJLAR as [$ad, $kadi, $konu, $mesaj, $okundu, $saat]) {
            $stmt->execute([
                ':ad'     => $ad,
                ':eposta' => $kadi . self::EPOSTA_SONEKI,
                ':konu'   => $konu,
                ':mesaj'  => $mesaj,
                ':okundu' => $okundu,
                ':saat'   => $saat,
            ]);
        }

        return count(self::MESAJLAR);
    }

    private function seedMails(): int
    {
        $this->db->prepare('DELETE FROM mail_kayitlari WHERE alici_eposta LIKE :sonek')
            ->execute([':sonek' => '%' . self::EPOSTA_SONEKI]);

        $stmt = $this->db->prepare(
            'INSERT INTO mail_kayitlari
                (alici_eposta, alici_ad, konu, govde, sablon, tur, durum, hata, deneme, gonderildi_at, created_at)
             VALUES
                (:eposta, :ad, :konu, :govde, :sablon, :tur, :durum, :hata, :deneme,
                 IF(:gitti = 1, NOW() - INTERVAL :saat HOUR, NULL), NOW() - INTERVAL :saat2 HOUR)'
        );

        foreach (self::EPOSTALAR as [$kadi, $ad, $konu, $sablon, $tur, $durum, $hata, $saat]) {
            $stmt->execute([
                ':eposta' => $kadi . self::EPOSTA_SONEKI,
                ':ad'     => $ad,
                ':konu'   => $konu,
                ':govde'  => self::mailBody($konu, $sablon),
                ':sablon' => $sablon,
                ':tur'    => $tur,
                ':durum'  => $durum,
                ':hata'   => $hata,
                ':deneme' => $durum === 'kuyrukta' ? 0 : ($durum === 'basarisiz' ? 3 : 1),
                ':gitti'  => $durum === 'gonderildi' ? 1 : 0,
                ':saat'   => $saat,
                ':saat2'  => $saat,
            ]);
        }

        return count(self::EPOSTALAR);
    }

    private function seedSettings(): int
    {
        $stmt = $this->db->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
        $adet = 0;

        foreach (self::settings() as $anahtar => $deger) {
            $stmt->execute([':deger' => $deger, ':anahtar' => $anahtar]);
            $adet += $stmt->rowCount() > 0 ? 1 : 0;
        }

        Setting::flush();

        return $adet;
    }

    /** OG görseli ve API örnek dosyaları (database/seeders/demo/). */
    private function copyAssets(): void
    {
        $kaynak = CY_BASE . '/database/seeders/demo';

        if (is_file($kaynak . '/og-cy-php-starter.png')) {
            @mkdir(CY_BASE . '/upload/img', 0755, true);
            @copy($kaynak . '/og-cy-php-starter.png', CY_BASE . '/upload/img/og-cy-php-starter.png');
        }

        $hedef = CY_BASE . '/storage/files/ornek';

        foreach (glob($kaynak . '/dosyalar/*') ?: [] as $dosya) {
            @mkdir($hedef, 0755, true);
            @copy($dosya, $hedef . '/' . basename($dosya));
        }
    }

    /* =================================================================
     *  SIFIRLA (canlı demo, 3 saatte bir)
     * ============================================================== */

    /**
     * Ziyaretçilerin değiştirdiği her şeyi geri alır ve örnek veriyi
     * baştan kurar. Kurulumdaki gerçek yönetici hesabına DOKUNMAZ.
     */
    public function reset(): void
    {
        $sonSifirlama = Setting::get('demo_son_sifirlama');

        $this->db->exec('DELETE FROM mesajlar');
        $this->db->exec('DELETE FROM mail_kayitlari');
        $this->db->exec('DELETE FROM login_attempts');

        // Ziyaretçinin (demo editörü) eklediği sayfalar; korunan ve KVKK kalır.
        $korunan = array_merge(self::SAYFALAR, ['hakkimizda', 'iletisim', Surum16::KVKK_SAYFA['slug']]);
        $this->db->prepare(
            'DELETE FROM sayfalar WHERE korumali = 0 AND slug NOT IN (' . implode(',', array_fill(0, count($korunan), '?')) . ')'
        )->execute($korunan);

        /* Son sıfırlamadan sonra kayıt formundan açılmış ÜYE hesapları
         * (yönetici ve editörler, yani kurulumu yapan kişi kalır). */
        if ($sonSifirlama !== '') {
            $this->db->prepare(
                "DELETE FROM kullanicilar WHERE rol = 'uye' AND created_at > :son AND kullanici_adi NOT IN ("
                . implode(',', array_map(fn (string $k): string => $this->db->quote($k), array_keys(Demo::HESAPLAR))) . ')'
            )->execute([':son' => $sonSifirlama]);
        }

        if ($this->tableExists('ornek')) {
            $this->db->exec('DELETE FROM ornek');
        }

        $this->removeUnusedUploads();
        $this->seed();

        Setting::set($this->db, 'demo_son_sifirlama', date('Y-m-d H:i:s'), 'dahili', false);
    }

    /**
     * Hiçbir kaydın kullanmadığı yüklemeleri siler: demo editörünün
     * sayfaya eklediği görseller, silinmiş hesapların avatarları.
     */
    private function removeUnusedUploads(): void
    {
        $kullanilan = [];

        foreach ($this->db->query("SELECT avatar FROM kullanicilar WHERE avatar <> ''")->fetchAll(PDO::FETCH_COLUMN) as $yol) {
            $kullanilan[(string) $yol] = true;
        }
        foreach ($this->db->query("SELECT kapak FROM sayfalar WHERE kapak <> ''")->fetchAll(PDO::FETCH_COLUMN) as $yol) {
            $kullanilan[(string) $yol] = true;
        }
        foreach ($this->db->query("SELECT deger FROM ayarlar WHERE anahtar IN ('site_logo','site_favicon','pwa_simge','seo_og_gorsel') AND deger <> ''")->fetchAll(PDO::FETCH_COLUMN) as $yol) {
            $kullanilan[(string) $yol] = true;
        }

        foreach (['avatar', 'sayfa', 'kapak'] as $tur) {
            foreach (glob(CY_BASE . '/upload/img/' . $tur . '/*') ?: [] as $dosya) {
                if (!isset($kullanilan['img/' . $tur . '/' . basename($dosya)])) {
                    @unlink($dosya);
                }
            }
        }
    }

    /* =================================================================
     *  KALDIR ("demo verisini kaldır")
     * ============================================================== */

    /**
     * Örnek veriyi kaldırır; gerçek hesaplara, kullanıcının yazdığı
     * sayfalara ve mesajlara dokunmaz. Ana sayfa, vitrin ve marka ayarları
     * nötr varsayılanlarına döner (marka: yalnızca hâlâ örnek değerdeyse).
     *
     * @return array<string,int>
     */
    public function remove(): array
    {
        $sonek = '%' . self::EPOSTA_SONEKI;

        $adlar = array_keys(Demo::HESAPLAR);
        $stmt  = $this->db->prepare(
            'DELETE FROM kullanicilar WHERE kullanici_adi IN (' . implode(',', array_fill(0, count($adlar), '?')) . ') AND eposta LIKE ?'
        );
        $stmt->execute([...$adlar, $sonek]);
        $hesap = $stmt->rowCount();

        $stmt = $this->db->prepare('DELETE FROM mesajlar WHERE eposta LIKE :s');
        $stmt->execute([':s' => $sonek]);
        $mesaj = $stmt->rowCount();

        $stmt = $this->db->prepare('DELETE FROM mail_kayitlari WHERE alici_eposta LIKE :s');
        $stmt->execute([':s' => $sonek]);
        $eposta = $stmt->rowCount();

        $stmt = $this->db->prepare(
            'DELETE FROM sayfalar WHERE korumali = 0 AND slug IN (' . implode(',', array_fill(0, count(self::SAYFALAR), '?')) . ')'
        );
        $stmt->execute(self::SAYFALAR);
        $sayfa = $stmt->rowCount();

        $notrSayfa = $this->restoreCorePages();

        $kayit = 0;
        if ($this->tableExists('ornek')) {
            $kayit = (int) $this->db->exec('DELETE FROM ornek');
        }

        /* MARKA AYARLARI: değeri hâlâ örnek veriyle AYNI olan nötre döner;
         * yöneticinin değiştirdiğine dokunulmaz. Eskiden kalıyordu: örnek
         * veri kaldırılmış bir müşteri sitesinin alt bilgisinde ÇILGIN
         * Yazılım'ın sosyal hesapları ve sloganı görünüyordu. */
        $ornek = self::settings();
        $marka = $this->db->prepare('UPDATE ayarlar SET deger = :notr WHERE anahtar = :anahtar AND deger = :ornek');
        foreach (self::MARKA_AYARLARI as $anahtar => $notrDeger) {
            $marka->execute([':notr' => $notrDeger, ':anahtar' => $anahtar, ':ornek' => $ornek[$anahtar] ?? '']);
        }

        // Vitrin ayarları nötre döner (site adı yönetici değiştirene kadar kalır).
        $notr = $this->db->prepare('UPDATE ayarlar SET deger = :deger WHERE anahtar = :anahtar');
        foreach (Surum16::AYARLAR as [$anahtar, $deger]) {
            if (str_starts_with($anahtar, 'anasayfa_') || $anahtar === 'vitrin_tanitim_goster') {
                $notr->execute([':deger' => $deger, ':anahtar' => $anahtar]);
            }
        }
        $notr->execute([':deger' => '', ':anahtar' => 'seo_og_gorsel']);
        @unlink(CY_BASE . '/upload/img/og-cy-php-starter.png');

        foreach (glob(CY_BASE . '/storage/files/ornek/*') ?: [] as $dosya) {
            @unlink($dosya);
        }

        Setting::flush();

        return compact('hesap', 'mesaj', 'eposta', 'sayfa', 'kayit') + ['notr' => $notrSayfa];
    }

    /**
     * Örnek verinin yazdığı korumalı sayfaları (Hakkımızda) nötr metne
     * döndürür; yalnızca başlık, özet, içerik ve SEO açıklaması hâlâ
     * BİREBİR örnek değerdeyse. Karşılaştırma PHP'de yapılır: tablonun
     * Türkçe sıralaması büyük/küçük harf ayırmaz, SQL eşitliği yönetici
     * yalnızca harf büyüklüğünü değiştirdiyse de "aynı" derdi.
     *
     * @return int nötre dönen sayfa sayısı
     */
    private function restoreCorePages(): int
    {
        $ornek = array_column(self::pages(), null, 'slug');
        $oku   = $this->db->prepare('SELECT id, baslik, ozet, icerik, seo_aciklama FROM sayfalar WHERE slug = :slug');
        $yaz   = $this->db->prepare('UPDATE sayfalar SET baslik = :baslik, ozet = :ozet, icerik = :icerik, seo_aciklama = :seo WHERE id = :id');
        $adet  = 0;

        foreach (self::NOTR_SAYFALAR as $slug => $notr) {
            $oku->execute([':slug' => $slug]);
            $satir = $oku->fetch();
            $o     = $ornek[$slug] ?? null;

            if ($satir === false || $o === null
                || (string) $satir['baslik'] !== $o['baslik'] || (string) $satir['ozet'] !== $o['ozet']
                || (string) $satir['icerik'] !== $o['icerik'] || (string) $satir['seo_aciklama'] !== $o['seo']) {
                continue;
            }

            $yaz->execute([
                ':baslik' => $notr['baslik'],
                ':ozet'   => $notr['ozet'],
                ':icerik' => $notr['icerik'],
                ':seo'    => $notr['seo_aciklama'],
                ':id'     => (int) $satir['id'],
            ]);
            $adet++;
        }

        return $adet;
    }

    /**
     * Açık modüllerin örnek verisi (modules/Ad/seeders). Modül sınıfları
     * yüklü olmalıdır (Modules::boot). Hata veren seeder diğerlerini
     * durdurmaz; mesajı döner.
     *
     * @return array<int,string> hata mesajları
     */
    public function seedModules(): array
    {
        $hatalar = [];

        foreach (Modules\Modules::seederFiles() as $dosya) {
            try {
                $seeder = require $dosya;

                if ($seeder instanceof Database\Seeder) {
                    $seeder->setConnection($this->db);
                    $seeder->setReporter($this->say);
                    $seeder->run();
                }
            } catch (Throwable $e) {
                $hatalar[] = basename($dosya) . ": " . $e->getMessage();
            }
        }

        return $hatalar;
    }

    /** Veritabanında örnek veri var mı? (Sistem sayfasındaki düğme için.) */
    public static function present(PDO $db): bool
    {
        return Demo::existing($db) !== [];
    }

    /**
     * Örnek veri kaldırılınca panele girebilecek etkin bir yönetici kalır mı?
     *
     * Sihirbaz her kurulumda gerçek bir yönetici açar. Ama site
     * database/ornek-veritabani.sql ile kurulduysa tek yönetici örnek
     * hesaptır (ali.yonetici); remove() onu da silse kimse panele
     * giremezdi. Ölçüt remove()'unkiyle aynıdır.
     */
    public static function leavesAdmin(PDO $db): bool
    {
        $adlar = array_keys(Demo::HESAPLAR);
        $stmt  = $db->prepare(
            "SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin' AND durum = 'aktif'
               AND NOT (kullanici_adi IN (" . implode(',', array_fill(0, count($adlar), '?')) . ') AND eposta LIKE ?)'
        );
        $stmt->execute([...$adlar, '%' . self::EPOSTA_SONEKI]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /* =================================================================
     *  İÇERİK
     * ============================================================== */

    /** @return array<string,string> anahtar => değer */
    public static function settings(): array
    {
        $aciklama = 'Kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, kuyruk ve PWA ile gelen, '
                  . 'Composer gerektirmeyen açık kaynak PHP 8 başlangıç şablonu.';

        $json = static fn (array $veri): string => (string) json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'site_adi'              => 'CY PHP Starter',
            'site_slogan'           => 'Her yeni PHP projesine buradan başlayın',
            'site_aciklama'         => $aciklama,
            'site_marka'            => 'ÇILGIN Yazılım',
            'site_imza_goster'      => '1',
            'vitrin_tanitim_goster' => '1',

            'sosyal_github'    => 'https://github.com/CilginYazilim/cy-php-starter',
            'sosyal_x'         => 'https://x.com/cilginyazilim',
            'sosyal_facebook'  => 'https://www.facebook.com/cilginyazilim/',
            'sosyal_instagram' => 'https://www.instagram.com/cilginyazilim/',

            'seo_anahtar_kelimeler' => 'php başlangıç şablonu, php starter kit, php boilerplate, php yönetim paneli, php kurulum sihirbazı',
            'seo_og_gorsel'         => 'img/og-cy-php-starter.png',

            'pwa_ad'            => 'CY PHP Starter',
            'pwa_kisa_ad'       => 'CY Starter',
            'pwa_aciklama'      => $aciklama,
            'mail_gonderen_adi' => 'CY PHP Starter',
            'iletisim_saatler'  => 'Hafta içi 09:00 – 18:00',
            'iletisim_adres'    => 'İstanbul',
            'sistem_tema_rengi' => '#0b5cb5',

            // ---- Ana sayfa vitrini ----
            'anasayfa_bolumler'       => $json(['hero', 'teknoloji', 'adimlar', 'ozellikler', 'roller', 'kod', 'sss', 'cta']),
            'anasayfa_rozet'          => 'v{surum} · Açık kaynak · MIT',
            'anasayfa_baslik'         => 'Her yeni PHP projesine buradan başlayın',
            'anasayfa_metin'          => 'Kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, kuyruk ve PWA hazır gelir. Composer, framework ya da CDN gerekmez; yüklediğiniz her sunucuda çalışır.',
            'anasayfa_birincil_metin' => 'Canlı demoyu aç',
            'anasayfa_birincil_adres' => 'giris',
            'anasayfa_ikincil_metin'  => 'GitHub\'da incele',
            'anasayfa_ikincil_adres'  => 'https://github.com/CilginYazilim/cy-php-starter',
            'anasayfa_guven'          => 'PHP 8.1–8.4 · 0 bağımlılık · {test} birim testi · MIT',
            'anasayfa_gorsel'         => 'vitrin',
            'anasayfa_ozellikler'     => $json([
                ['ikon' => 'shield', 'baslik' => 'Güvenlik baştan hazır', 'metin' => 'CSRF, XSS ve SQL enjeksiyonu korumaları, kaba kuvvet kilidi ve sertleştirilmiş oturum kurulumla gelir.'],
                ['ikon' => 'users',  'baslik' => 'Rol ve yetki', 'metin' => 'Yönetici, editör ve üye; modüller kendi yetkilerini dağıtır, kayıt düzeyi kurallar tek sınıfta durur.'],
                ['ikon' => 'code',   'baslik' => 'REST API', 'metin' => 'Süreli Bearer anahtarları, okuma/yazma kapsamları ve mobil uygulama için oturum uçları.'],
                ['ikon' => 'send',   'baslik' => 'E-posta ve kuyruk', 'metin' => 'SMTP, toplu gönderim ve arka plan kuyruğu; giden her mektup kayıt altında.'],
                ['ikon' => 'box',    'baslik' => 'Modüller', 'metin' => 'php cy make:module çalışan bir iskelet üretir; panelden tek tıkla açılır.'],
                ['ikon' => 'mobil',  'baslik' => 'PWA ve mobil', 'metin' => 'Telefona kurulabilen uygulama modu ve baştan sona mobil uyumlu panel.'],
            ]),
            'anasayfa_adimlar' => $json([
                ['baslik' => 'İndirin', 'metin' => 'GitHub\'dan ZIP olarak indirin ve sunucunuza yükleyin.'],
                ['baslik' => 'kurulum/ adresini açın', 'metin' => 'Sihirbaz veritabanını, yönetici hesabını ve ayarları iki ekranda kurar.'],
                ['baslik' => 'Panele girin', 'metin' => 'Örnek veriyle kurduysanız demo hesaplarla her rolü hemen deneyin.'],
            ]),
            'anasayfa_sss' => $json([
                ['soru' => 'Composer gerekiyor mu?', 'cevap' => 'Hayır. Şablonun hiçbir dış bağımlılığı yok; dosyaları yükleyip kurulum sihirbazını açmanız yeterli.'],
                ['soru' => 'Hangi PHP sürümleriyle çalışır?', 'cevap' => 'PHP 8.1, 8.2, 8.3 ve 8.4 ile her sürümde test edilir; MySQL 5.7+ ya da MariaDB 10.3+ ister.'],
                ['soru' => 'Paylaşımlı hostingde çalışır mı?', 'cevap' => 'Evet. SSH gerekmez: migration\'ları çalıştırmak ve modülleri açmak panelden yapılır.'],
                ['soru' => 'Ticari projede kullanabilir miyim?', 'cevap' => 'Evet, MIT lisanslıdır. Alt bilgideki imzayı Ayarlar → Genel\'den kapatabilirsiniz.'],
                ['soru' => 'Yeni bir özelliği nasıl eklerim?', 'cevap' => '"php cy make:module Stok" çalışan bir modül iskeleti üretir; Örnek Modül adım adım anlatılmış bir şablondur.'],
                ['soru' => 'Mobil uygulama yazabilir miyim?', 'cevap' => 'Evet. REST API; oturum açma, cihaz oturumları ve örnek dosya uçlarıyla gelir. Ayrıntılar README\'deki "Mobil uygulama" bölümünde.'],
            ]),
            'anasayfa_cta_baslik'   => 'Bir sonraki projenize bugün başlayın',
            'anasayfa_cta_metin'    => 'Açık kaynak, ücretsiz ve Türkçe. İndirin, kurun, geliştirmeye başlayın.',
            'anasayfa_cta_dugmeler' => $json([
                ['metin' => 'ZIP indir', 'adres' => 'https://github.com/CilginYazilim/cy-php-starter/archive/refs/heads/main.zip'],
                ['metin' => 'GitHub', 'adres' => 'https://github.com/CilginYazilim/cy-php-starter'],
                ['metin' => 'Belgeler', 'adres' => 'https://cilginyazilim.com/kutuphane/php-baslangic-sablonu'],
            ]),
        ];
    }

    /** @return array<int,array{baslik:string,slug:string,ozet:string,icerik:string,menude:int,korumali:int,sira:int,seo:string}> */
    public static function pages(): array
    {
        return [
            [
                'baslik' => 'Hakkımızda', 'slug' => 'hakkimizda', 'menude' => 1, 'korumali' => 1, 'sira' => 10,
                'ozet'   => 'CY PHP Starter nedir, kimin için yapıldı ve nasıl bir felsefeyle geliştiriliyor.',
                'seo'    => 'CY PHP Starter, ÇILGIN Yazılım\'ın açık kaynak PHP 8 başlangıç şablonu: kurulum sihirbazı, yönetim paneli, REST API ve modül sistemi.',
                'icerik' => '<h2>CY PHP Starter nedir?</h2>'
                    . '<p>CY PHP Starter, ÇILGIN Yazılım\'ın her yeni PHP projesinde tekrar tekrar yazdığı altyapıyı tek pakette topladığı <strong>açık kaynak bir başlangıç şablonudur</strong>: kurulum sihirbazı, rol tabanlı yönetim paneli, REST API, e-posta kuyruğu, modül sistemi ve PWA. Composer, framework ya da CDN gerektirmez.</p>'
                    . '<h3>Kimin için?</h3><ul>'
                    . '<li>Kurumsal site, yönetim paneli ya da küçük bir SaaS projesine hızlı başlamak isteyen geliştiriciler</li>'
                    . '<li>Composer kurulamayan paylaşımlı hosting kullananlar</li>'
                    . '<li>PHP öğrenen ve gerçek bir projenin iskeletini satır satır incelemek isteyenler</li></ul>'
                    . '<h3>Felsefe</h3><blockquote>Çekirdek ne iş yaptığınızı bilmez; işinizin nasıl çalışacağını sağlar.</blockquote>'
                    . '<p>Her parça okunabilir, açıklamalı ve tek başına değiştirilebilir. Bir özelliği eklemek için çekirdeğe dokunmazsınız; modül yazarsınız.</p>'
                    . '<h3>ÇILGIN Yazılım</h3>'
                    . '<p>Şablonun ayrıntılı tanıtımı <a href="https://cilginyazilim.com/kutuphane/php-baslangic-sablonu">PHP Başlangıç Şablonu</a> sayfasında; diğer açık kaynak çalışmalarımız <a href="https://cilginyazilim.com/kutuphane">cilginyazilim.com/kutuphane</a> adresinde.</p>',
            ],
            [
                'baslik' => 'Başlarken', 'slug' => 'baslarken', 'menude' => 1, 'korumali' => 0, 'sira' => 20,
                'ozet'   => 'Dört adımda kurulum, demo hesaplar ve ilk modülünüz.',
                'seo'    => 'CY PHP Starter kurulumu dört adımda: indirin, kurulum sihirbazını açın, demo hesaplarla deneyin, ilk modülünüzü üretin.',
                'icerik' => '<h2>Dört adımda çalışan proje</h2><ol>'
                    . '<li><strong>İndirin:</strong> GitHub\'dan ZIP olarak indirin ya da <code>git clone</code> ile alın, sunucunuzun web klasörüne koyun.</li>'
                    . '<li><strong>Boş bir veritabanı açın:</strong> phpMyAdmin ya da hosting panelinden; karakter seti utf8mb4.</li>'
                    . '<li><strong>Sihirbazı çalıştırın:</strong> tarayıcıda <code>/kurulum</code> adresini açın; veritabanı ve yönetici bilgilerini girin.</li>'
                    . '<li><strong>Kurulum klasörünü silin:</strong> son ekrandaki düğmeyle ya da Panel → Sistem Bilgisi\'nden.</li></ol>'
                    . '<h2>Demo hesaplar</h2><p>Örnek veriyle kurduysanız şu hesaplarla her rolü deneyebilirsiniz. Demo modunda giriş ekranı bu hesapları tek tıkla girişle listeler.</p>'
                    . '<ul><li><strong>Yönetici:</strong> ali.yonetici — her ekran</li><li><strong>Editör:</strong> elif.editor — sayfalar, mesajlar, e-posta geçmişi</li><li><strong>Üye:</strong> mehmet.uye — kendi profili ve Örnek Modül</li></ul>'
                    . '<h2>İlk modülünüz</h2><p>Terminalde <code>php cy make:module Stok</code> yazın: listeleme, ekleme ve silmeyle çalışan bir modül oluşur. Panel → Sistem Bilgisi → Modüller\'den açın.</p>'
                    . '<h2>Belgeler</h2><ul><li><a href="https://github.com/CilginYazilim/cy-php-starter/blob/main/README.md">README</a> — kurulum, özellikler, API</li>'
                    . '<li><a href="https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md">SISTEM.md</a> — mimari ve genişletme rehberi</li></ul>',
            ],
            [
                'baslik' => 'Özellikler', 'slug' => 'ozellikler', 'menude' => 1, 'korumali' => 0, 'sira' => 30,
                'ozet'   => 'Modüller, roller, REST API, kuyruk ve PWA: şablonla hazır gelen her şeyin özeti.',
                'seo'    => 'CY PHP Starter özellikleri: modül sistemi, rol tabanlı yetki, REST API, e-posta kuyruğu, zamanlayıcı ve PWA.',
                'icerik' => '<h2>Hazır gelenler</h2><table><thead><tr><th>Alan</th><th>Ne var?</th></tr></thead><tbody>'
                    . '<tr><td>Modüller</td><td>Kendi rotası, tabloları, görünümü, menüsü ve yetkileriyle gelen klasörler; panelden aç/kapa.</td></tr>'
                    . '<tr><td>Roller</td><td>Yönetici, editör, üye; kayıt düzeyi kurallar için Policy sınıfları.</td></tr>'
                    . '<tr><td>REST API</td><td>Bearer anahtarları, okuma/yazma kapsamı, mobil uygulama için oturum uçları, hız sınırı.</td></tr>'
                    . '<tr><td>Kuyruk</td><td>Veritabanı kuyruğu, katlanan yeniden deneme, tek cron satırıyla zamanlayıcı.</td></tr>'
                    . '<tr><td>E-posta</td><td>SMTP ya da mail(), toplu gönderim, giden her mektubun kaydı.</td></tr>'
                    . '<tr><td>PWA</td><td>Panelden yönetilen uygulama künyesi, servis çalışanı, çevrimdışı sayfa.</td></tr>'
                    . '<tr><td>Güvenlik</td><td>CSRF, katı CSP, kaba kuvvet kilidi, parola sıfırlama, KVKK onayı.</td></tr>'
                    . '</tbody></table>',
            ],
        ];
    }

    /** ad, kullanıcı adı (e-posta = kadi + sonek), konu, mesaj, okundu, kaç saat önce */
    private const MESAJLAR = [
        ['Deniz Aksoy', 'deniz', 'Teklif talebi', 'Merhaba, kurumsal web sitemizi bu şablonla yenilemeyi düşünüyoruz. Kurulum ve özelleştirme için fiyat teklifi alabilir miyiz?', 0, 3],
        ['Burak Öztürk', 'burak', 'Destek: SMTP ayarı', 'Panelde e-posta ayarlarını yaptım ama test mektubu ulaşmadı. 465 ve 587 kapılarını denedim; ne kontrol etmeliyim?', 0, 9],
        ['Selin Kara', 'selin', 'İş birliği önerisi', 'Ajansımızın projelerinde şablonu kullanmak istiyoruz. Modül geliştirme konusunda birlikte çalışabilir miyiz?', 1, 26],
        ['Emre Çelik', 'emre', 'Hata bildirimi', 'Sayfalar ekranında çok uzun bir başlık yazınca tablonun hücresi taşıyor. Ekran görüntüsünü ekleyebilirim.', 1, 50],
        ['Gizem Aydın', 'gizem', 'Eğitim', 'Üniversitede web programlama dersinde şablonu örnek olarak göstermek istiyorum. Ders notu hazırlamamda sakınca var mı?', 0, 74],
        ['Ozan Polat', 'ozan', 'Mobil uygulama', 'REST API ile mobil uygulamaya giriş yaptırmak istiyorum. Oturum anahtarının süresi nasıl ayarlanıyor?', 1, 120],
        ['Ebru Koç', 'ebru', 'Kurulum sorusu', 'Paylaşımlı hostingde kurulum sihirbazı "yazma izni yok" diyor. Hangi klasörlere izin vermem gerekiyor?', 1, 170],
        ['Kerem Şen', 'kerem', 'Fatura bilgisi', 'Geçen ayki destek hizmeti için faturayı şirket unvanımızla yeniden düzenleyebilir misiniz?', 1, 230],
        ['Nazlı Er', 'nazli', 'Öneri', 'Panelde koyu tema çok güzel olmuş. Ayarlar sayfasına arama kutusu eklenirse çok iyi olur.', 1, 300],
    ];

    /** alıcı kullanıcı adı, alıcı adı, konu, şablon, tür, durum, hata, kaç saat önce */
    private const EPOSTALAR = [
        ['mehmet', 'Mehmet Kaya', 'Hoş geldiniz!', 'hosgeldin', 'otomatik', 'gonderildi', '', 190],
        ['deniz', 'Deniz Aksoy', 'Mesajınızı aldık', 'iletisim-yanit', 'otomatik', 'gonderildi', '', 3],
        ['elif', 'Elif Demir', 'Yeni iletişim mesajı: Teklif talebi', 'iletisim-bildirim', 'bildirim', 'gonderildi', '', 3],
        ['zeynep', 'Zeynep Arslan', 'E-posta adresinizi doğrulayın', 'dogrulama', 'sistem', 'gonderildi', '', 20],
        ['ali', 'Ali Yılmaz', 'Ekim bülteni: yeni özellikler', 'duyuru', 'toplu', 'gonderildi', '', 46],
        ['burak', 'Burak Öztürk', 'Ekim bülteni: yeni özellikler', 'duyuru', 'toplu', 'basarisiz', '550 Mailbox unavailable', 46],
        ['selin', 'Selin Kara', 'Ekim bülteni: yeni özellikler', 'duyuru', 'toplu', 'gonderildi', '', 45],
    ];

    /** Örnek mektup gövdesi (önizlemede görünür). */
    private static function mailBody(string $konu, string $sablon): string
    {
        $govde = $sablon === 'dogrulama'
            ? '<p>Hesabınızı etkinleştirmek için aşağıdaki bağlantıya tıklayın:</p><p><a href="https://ornek.com/kayit/dogrula?k=0&amp;s=0&amp;i=demo">E-postamı doğrula</a></p><p>Bu bir demo mektubudur; bağlantı çalışmaz.</p>'
            : '<p>Merhaba,</p><p>Bu, CY PHP Starter demo verisindeki örnek bir mektuptur. Gerçek bir sitede şablon (' . htmlspecialchars($sablon) . ') burada kendi içeriğiyle görünür.</p>';

        return '<!DOCTYPE html><html lang="tr"><body style="font-family:system-ui,sans-serif;background:#f6f8fb;padding:24px">'
            . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #e6ebf2">'
            . '<h1 style="font-size:20px;margin:0 0 12px">' . htmlspecialchars($konu) . '</h1>' . $govde
            . '<p style="color:#64748b;font-size:13px;margin-top:24px">CY PHP Starter · ÇILGIN Yazılım</p></div></body></html>';
    }

    /* ================================================================= */

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query('SELECT 1 FROM `' . $table . '` LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function report(string $message): void
    {
        if ($this->say !== null) {
            ($this->say)($message);
        }
    }
}
