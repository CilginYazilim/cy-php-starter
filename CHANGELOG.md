# Değişiklik Günlüğü

Bu dosyanın biçimi [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/)
kalıbını izler ve proje [Semantic Versioning](https://semver.org/lang/tr/)
kurallarına uyar.

---

## [1.3.0] — 2026-10-06

Kapsamlı bir güvenlik incelemesinin (yerel kurulum üzerinde canlı
denenmiş bulgular) kapatıldığı sürüm. Her madde yerelde yeniden üretildi,
düzeltildi ve yeniden sınandı; testler `tests/` klasöründe.

### Güncelleme adımları

1. Kodu çekin, ardından `php cy migrate` çalıştırın (2 yeni migration:
   `oturum_surumu`, `mail_kuyrugu_kilidi`).
2. `.env` dosyanıza şunları ekleyin (yoksa güvenli varsayılanlar
   kullanılır):
   `APP_KEY=` (64 onaltılık karakter: `php -r "echo bin2hex(random_bytes(32));"`)
   ve `SESSION_NAME=` (ör. `CYS_projeadi`).
3. `.env`'de `APP_ENV` / `APP_DEBUG` satırları yoksa artık
   **production / false** varsayılır.
4. Tüm kullanıcılar bir kez yeniden giriş yapar (oturumlar kuruluma
   bağlandı).
5. Eski kurulumlarda sihirbazın çalıştırdığı migration'lar 1. partidedir;
   `migrate:rollback` onları geri alabilir. Kurulumdan sonra hiç
   migration çalıştırmadıysanız bir kez:
   `UPDATE migrasyonlar SET parti = 0 WHERE parti = 1;`

### Güvenlik — kritik

- **Kurulum sihirbazı herkes tarafından yeniden çalıştırılabiliyordu.**
  `?yeniden=1` kilidi kimlik sormadan kaldırıyordu; anonim bir ziyaretçi
  `.env`'i kendi veritabanına yönlendirip kendine yönetici hesabı
  açabiliyordu. Kilit ayrıca veritabanı sorgusuna bağlıydı ve veritabanı
  bir an düştüğünde AÇILIYORDU. Artık: `yeniden` parametresi yok;
  `.env` ya da `storage/installed.lock` varsa hiçbir adım çalışmaz;
  `.env` "x" kipiyle (varsa yazmadan) oluşturulur; klasör silinemezse
  `kurulum/.htaccess` ile web'e kapatılır; seçilen veritabanı boş değilse
  açık onay istenir (şema `DROP TABLE` içerir).

### Güvenlik — yüksek

- **Açık yönlendirme.** "Girişten sonra dön" adresi ham `r`
  parametresinden (POST dahil) alınıyordu; `/\evil.example` gibi bir
  değer girişten sonra başka siteye götürüyordu. Artık yalnızca GET
  isteklerinde, isteğin kendi yolundan yazılır ve okurken beyaz listeyle
  doğrulanır (`Middleware::safeIntended`). Temiz adreslerde hiç çalışmayan
  "istenen sayfaya dön" özelliği de böylece çalışır oldu.
- **Saat dilimi farkında kaba kuvvet kilidi devre dışı kalıyordu.**
  Kayıtlar MySQL `NOW()` ile yazılıp PHP `strtotime()` ile okunuyordu.
  Bağlantının saat dilimi artık PHP'ninkiyle eşitleniyor
  (`Database::syncTimezone`) ve süreler SQL'de hesaplanıyor. Aynı kök
  sorun kuyruktaki `hazir_at` ve API anahtarı süresinde de düzeltildi.
- **Parola değişince eski oturumlar ve "beni hatırla" çerezi geçerli
  kalıyordu.** Yeni `kullanicilar.oturum_surumu` sütunu: parola değişince
  artar, "beni hatırla" jetonu aynı sorguda silinir; diğer cihazlar bir
  sonraki istekte düşer. Hesabım ekranına "Diğer cihazlardan çıkış yap"
  eklendi.
- **Kurulum her siteyi `APP_ENV=local` + `APP_DEBUG=true` ile kuruyordu**;
  `config/app.php` varsayılanı da `true` idi. Canlı sitelerde hata
  ayrıntıları ve giriş ekranında demo parolası görünüyordu. Varsayılan
  artık yayın modu; geliştirme modu sihirbazda bilinçli seçilir. Demo
  hesaplar yalnızca debug + yayın-dışı ortamda VE hesap gerçekten varsa
  önerilir.
- **Kurulumdan hemen sonra `migrate:rollback` veri siliyordu** (sayfalar
  tablosu, 17 ayar satırı, "beni hatırla" sütunları). Kurulumun
  migration'ları artık "temel parti" (0) olarak yazılır ve rollback/fresh
  onlara dokunmaz; `database.sql` şemada zaten bulunan migration'ları da
  bu partiyle işaretler.

### Güvenlik — orta

- **Servis çalışanı panel sayfalarını önbelleğe alıyordu.** Panel, giriş,
  kayıt, çıkış artık yalnızca ağdan gelir; giriş yapmış kullanıcıya
  üretilen her yanıt `X-CY-Onbellek: hayir` taşır. Önbellek sürümü
  `cy-v2` (eski önbellekler silinir).
- **`.git/` dışarıya açıktı** (`FilesMatch` yalnızca dosya adına
  bakıyordu). Nokta ile başlayan her yol (`.well-known` hariç) ve
  `tests/`, `docs/` artık kapalı.
- **`upload/.htaccess` PHP-FPM'de bütün görselleri bozuyordu**
  (`php_flag` `<IfModule>` dışındaydı). Ayrıca yükleme klasörüne
  `nosniff` + kısıtlı CSP eklendi. Diğer klasörlerdeki `Require` + `Deny`
  karışımı (mod_access_compat yoksa 500) tek kalıba indirildi.
- **Kullanıcı tespiti.** Sahte parola özeti geçersizdi ve maliyeti 12'ydi;
  olmayan hesap ~260 ms, var olan ~60 ms sürüyordu. Artık aynı maliyette
  geçerli bir özet doğrulanır; "kalan deneme hakkı" iki durumda da aynı
  kurala göre gösterilir. Tek IP'den farklı hesaplara yapılan denemeler
  için IP geneli kilit eklendi (`LOGIN_IP_MAX_ATTEMPTS`, varsayılan 30).
- **İletişim formu spam rölesi olarak kullanılabiliyordu.** Otomatik yanıt
  varsayılan KAPALI, ziyaretçinin yazdığı metni içermiyor ve aynı adrese
  24 saatte en fazla bir kez gidiyor.
- **Mail kuyruğunda çift gönderim.** Satırlar atomik bir UPDATE ile
  "gonderiliyor" durumuna alınarak ayrılıyor; yarıda kalanlar (yeniden
  gönderip kopya üretmemek için) başarısız işaretleniyor. `Mailer::send`
  kaydı doğrudan "gonderiliyor" açıyor.
- **Kuyrukta sonsuz yeniden deneme.** `releaseStuck` deneme sınırına
  bakmıyordu; hakkı biten iş artık "basarisiz" olur.
- **Görsel bombası.** Toplam piksel sınırı (`UPLOAD_MAX_PIXELS`, 25 MP) ve
  kullanılabilir bellek kontrolü; `Uploader::store` yolu da dahil.
- **`.env` yazıcısı yalnızca `"` kaçışlıyordu**; `\t` ya da `${` içeren
  veritabanı parolası okunurken bozuluyordu. Okuyucu ve yazıcı artık aynı
  dosyada (`Env::quote` / `Env::parseValue`), gidiş-dönüş testli.
- **API katmanı.** `.htaccess` `Authorization` başlığını PHP'ye iletiyor;
  Bearer istekleri durumsuz (oturum/çerez yok, `Auth::actingAs`); oturumla
  gelen API isteklerinde CSRF zorunlu; örnek `api/v1` uçları, Hesabım
  ekranında anahtar yönetimi ve `php cy api:token` komutu eklendi.
- **Aynı alan adındaki kurulumların oturumları karışıyordu.** Çerez adı
  (`SESSION_NAME`, sihirbaz rastgele üretir), çerez yolu (uygulama
  klasörü), oturum klasörü (`storage/sessions`) ve oturum parmak izi
  (`APP_KEY`) artık kuruluma özel.
- **`mail_gonderen` doğrulanmıyordu** ve sendmail'e `-f` argümanı olarak
  gidiyordu (komut satırı argüman enjeksiyonu). Katı doğrulama hem ayar
  kaydında hem gönderimde (`NativeTransport::safeEnvelopeSender`).

### Düzeltildi — düşük ve eksikler

- Sürüm tutarsızlığı giderildi (kod, README rozeti ve bu dosya: 1.3.0).
- `database.sql`: şablon yazarının WhatsApp numarası ve sosyal hesapları
  varsayılan değerlerden kaldırıldı; `USE yeni_proje` kaldırıldı; DROP
  listesine `api_anahtarlari`, `isler`, `onbellek`, `migrasyonlar` eklendi.
- Bakım modu `dashboard.view` (her üyede var) yerine `maintenance.bypass`
  yetkisine bağlandı; girişli üyeler de bakımda paneli kullanamaz.
- Kayıt formuna IP başına saatlik sınır, bal küpü ve süre denetimi eklendi;
  parolalar artık "eski girdi" olarak oturuma yazılmıyor.
- E-posta değişikliği mevcut parolayı istiyor.
- Ayarlar tipine göre doğrulanıyor (`javascript:` bağlantı yazılamaz,
  sayı aralıkları, saat dilimi, renk, seçenek listesi); SMTP parolası
  "Kayıtlı parolayı sil" kutusuyla silinebiliyor; `settings.js`'teki ölü
  kod temizlendi, "kaydedilmemiş değişiklik" uyarısı yeniden çalışıyor.
- `make:*` adları doğrulanıyor: Türkçe harfler çevriliyor ("Ürün" → `Urun`,
  eskiden `RN`), rakamla başlayan ve ayrılmış sözcük adlar reddediliyor,
  çekirdek tabloyla çakışan modül/model adı (`Kullanicilar`) engelleniyor.
- Kapalı modülün migration'ları rollback/fresh sırasında bulunuyor.
- Bilinmeyen ara katman adı (`'Auth'`, `'cann:…'`) artık hata fırlatıyor;
  rota sessizce herkese açık kalmıyor.
- `?r[]=x` gibi dizi parametreleri 500 üretmiyor (`Request::string`).
- 405, 429 ve 503 kendi sayfasıyla (ve `Retry-After` ile) gösteriliyor;
  bakım 503'ü günlüğe CRITICAL değil INFO olarak yazılıyor.
- `View::resolve` kara liste yerine beyaz liste kullanıyor.
- `FileStore` bozuk dosyada `false` yerine `null` döndürüyor.
- Fotoğraflarda EXIF yönü uygulanıyor (dikey çekimler yan durmuyor).
- `.htaccess`'teki sondaki-bölü yönlendirmesi göreli hedef yüzünden bazı
  sunucularda disk yolunu içeren bir adrese (`/C:/xampp/htdocs/...`)
  gidiyordu; hedef artık `REQUEST_URI`'den üretiliyor. Vitrin/takma ad
  istisnası şablon yazarının sitesine özel yol yerine genel
  `REDIRECT_STATUS` koşuluna bağlandı.
- Panelden seçilen saat dilimi artık komut satırında (kuyruk, zamanlanmış
  görevler) da uygulanıyor.

### Eklendi

- `tests/unit.php` (veritabanı gerektirmez) ve `tests/smoke.php` (kurulu
  siteye salt-okunur HTTP denetimi); GitHub Actions iş akışı
  (PHP 8.1–8.4 sözdizimi + birim testleri).
- `php cy api:token` komutu, `api/v1` örnek uçları, Hesabım → API
  Anahtarları ve Oturumlar bölümleri.

### Kaldırıldı

- `docs/KURULUM-TEST-RAPORU.md` — eski sürüme ait, artık doğru olmayan
  bir rapordu ve kişisel e-posta adresi içeriyordu. Güncel doğrulama
  `tests/` klasöründeki betiklerle yapılır.

---

## [1.2.1] — 2026-09-05

### Düzeltildi

- **Vitrin adresinden açıldığında 404.** Uygulama, cilginyazilim.com
  kütüphanesinde `/kutuphane/uygulama/cy-php-starter/` adresinden de servis
  ediliyor; sunucu bu adresi gerçek klasöre içeriden bağlıyor. Böyle bir
  istekte `SCRIPT_NAME` gerçek klasörü, `REQUEST_URI` ise ziyaretçinin
  gördüğü adresi gösterir. Taban yolu yalnızca `SCRIPT_NAME`'den
  türetildiği için ikisi tutmuyor, rota `kutuphane/uygulama/cy-php-starter`
  diye okunuyor ve uygulama kendi "Sayfa bulunamadı" sayfasını basıyordu.

  `Url::base()` artık istek gerçek klasörün altından gelmiyorsa tabanı
  isteğin kendisinden türetiyor (`Url::aliasTaban()`). Rota doğru çözülüyor
  ve üretilen bütün adresler — `assets/`, form `action`'ları,
  yönlendirmeler — ziyaretçinin bulunduğu adreste kalıyor. İstekten gelen
  yol, `Url::current()` ile aynı dar karakter kümesine indiriliyor ve `..`
  içeren yol koşulsuz reddediliyor.

  `Url::absolute()` de buna uyduruldu: `APP_URL`'in yol kısmı aktif tabanla
  uyuşmuyorsa yalnızca alan adı kullanılıyor. Aksi hâlde iki yol üst üste
  binip `/demos/cy-php-starter/kutuphane/uygulama/...` gibi adresler
  çıkıyordu.

- **Vitrin adresinde eğik çizgi kırpılması.** `.htaccess`'teki "sondaki
  bölü işaretini temizle" kuralı vitrin üzerinden gelen isteklerde artık
  çalışmıyor. Site kökündeki kural o adrese eğik çizgiyi eklerken buradaki
  kural siliyor, ikisi karşılıklı 301 üretiyordu.

---

## [1.2.0] — 2026-09-04

### Eklendi

- **Ekran görüntüleri geri geldi — 12 kare.** `docs/screenshots/` klasörü
  boştu ve kendi README'sinde şu not duruyordu: *"Eski görseller şablonun
  prosedürel sürümüne aitti; artık ürünü doğru göstermiyorlardı."* Doğru
  bir karardı — yanlış bir ekran görüntüsü, hiç ekran görüntüsü
  olmamasından kötüdür. Ama bir başlangıç şablonunun en çok sorulan
  sorusu "kurulunca ne çıkıyor?" olduğu için o boşluk pahalıydı.

  Kareler, o README'nin kendi koyduğu kurallara göre çekildi: kurulum
  sihirbazı tamamlandıktan sonra, sihirbazın yüklediği örnek veriyle,
  1360 px genişlikte (mobil 390 px), hepsi 500 KB altında ve dosya adları
  klasörün belirlediği listeye birebir uygun. Kurulum ekranlarında gerçek
  şifre veya veritabanı bilgisi yoktur.

- **"Canlı Demo" bölümü eklendi.** README başlığının altına çalışan
  demoya / kaynak kütüphanesine / ZIP indirmeye giden üç düğme ve demoya
  bağlanan tıklanabilir bir panel önizlemesi kondu.

- **"Ekran Görüntüleri" bölümü eklendi.** Kurulum sihirbazının iki adımı,
  panelin dört ekranı, ön yüz, koyu tema ve mobil görünüm — her biri ne
  gösterdiğini anlatan bir alt yazıyla.

### Değiştirildi

- `docs/screenshots/README.md` artık klasörün boş olduğunu söylemiyor;
  hangi karenin ne gösterdiğini listeliyor. Görsellerin ne zaman
  yenileneceğine dair kural korundu.

---

> Bu sürümden öncesi ayrı bir günlükte tutulmuyordu. Daha eski
> değişiklikler için depo geçmişine ve `SISTEM.md` dosyasına
> bakabilirsiniz.
