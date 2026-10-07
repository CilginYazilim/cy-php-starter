# Değişiklik Günlüğü — CY PHP Starter

[PHP Başlangıç Şablonu (CY PHP Starter)](https://github.com/CilginYazilim/cy-php-starter)
sürümlerinde yapılan bütün önemli değişiklikler bu dosyadadır: güvenlik
düzeltmeleri, yeni özellikler ve her sürümün güncelleme adımları.

Bu dosyanın biçimi [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/)
kalıbını izler ve proje [Semantic Versioning](https://semver.org/lang/tr/)
kurallarına uyar. Sürümlerin indirilebilir paketleri
[GitHub sürümleri](https://github.com/CilginYazilim/cy-php-starter/releases)
sayfasındadır.

| Sürüm | Tarih | Özet |
|---|---|---|
| [1.6.1](#161--2026-10-07) | 2026-10-07 | Form yardım metinleri, güvenlik ve bilgilendirme bildirimleri, duyuru tercihi, koyu temada AA kontrast, menüyle hizalı ana sayfa |
| [1.6.0](#160--2026-10-07) | 2026-10-07 | Parola sıfırlama, mobil oturum API'si, KVKK araçları, hesap silme, 2 ekranlı kurulum, panelden yönetilen ana sayfa, CI'da MySQL 8 ile rol matrisi |
| [1.5.1](#151--2026-10-07) | 2026-10-07 | Veritabanı: sık çalışan sorgular için eksik indeksler, giriş temizliğinde kilit |
| [1.5.0](#150--2026-10-07) | 2026-10-07 | Demo modu (tek tıkla giriş), panelden modül aç/kapa, onay akışlı RBAC örnek modülü, sade panel tasarımı |
| [1.4.0](#140--2026-10-06) | 2026-10-06 | İkinci güvenlik incelemesi |
| [1.3.0](#130--2026-10-06) | 2026-10-06 | Güvenlik ve kararlılık |
| [1.2.1](#121--2026-09-05) | 2026-09-05 | Takma ad (vitrin) adresi düzeltmesi |
| [1.2.0](#120--2026-09-04) | 2026-09-04 | Ekran görüntüleri ve Canlı Demo |
| [1.1.0](#110--2026-08-30) | 2026-08-30 | Mobil düzen, tek kaynaktan sürüm |
| [1.0.0](#100--2026-08-18) | 2026-08-18 | İlk kararlı sürüm |

---

## [1.6.1] — 2026-10-07

Cowork'un 1.6.0 kontrol raporu ve önerileri üzerine: formlar ne yazılacağını
anlatıyor, güvenlik ve bilgilendirme mektupları tamamlandı, koyu tema her
yerde WCAG AA kontrastında, ana sayfa menüyle aynı çizgide.

**Güncelleme:** kodu çekin, `php cy migrate` (iki migration: ayar
açıklamaları, bildirimler). Değerlerinize dokunmaz.

### Eklendi

- **Form yardım metinleri.** Kayıt, giriş, iletişim, Hesabım, kullanıcı
  modalı, sayfa düzenleyici, toplu e-posta, logo/favicon/PWA simgesi,
  Örnek Modül ve kurulum sihirbazındaki alanların altında ne yazılacağını
  anlatan satırlar; hepsi `aria-describedby` ile alana bağlı.
- **Tek kaynaktan kural metinleri:** `password_hint()`, `username_hint()`
  ve `upload_max_mb()` yardımcıları. Parola kuralı ya da yükleme sınırı
  config'te değişince formlar da değişir (eskiden "en az 8 karakter" ve
  "2 MB" dört dosyada elle yazılıydı).
- **Rol ve durum açıklamaları** kullanıcı modalında (`Role::description()`):
  her rolün neye erişebildiği, Pasif ile Askıda arasındaki fark.
- **Karakter sayacı:** `data-sayac="#hedef"` taşıyan her alan "120 / 1000"
  gösterir, sınırın %90'ında uyarı rengine döner; `data-sayac-oneri="60"`
  ile SEO alanlarında "42 / 60 önerilen".
- **"E-posta adresiniz değiştirildi" mektubu ESKİ adrese.** Adres Hesabım'dan
  ya da bir yönetici tarafından değişince gider; yeni adres maskelenir
  (`EmailChanged` olayı, `EpostaDegistiBildir` dinleyicisi). Ele geçirilen
  bir oturumla adres değiştirilirse asıl sahibin bunu fark etmesinin yolu
  budur: sıfırlama bağlantıları artık yeni adrese gider.
- **"Hesabınız silinecek" mektubu.** Silme isteğinden sonra tarih ve iptal
  yolu (`AccountDeletionScheduled` olayı, `HesapSilmeBildir` dinleyicisi).
- **Yeni üye bildirimi (yöneticiye).** Ayarlar → E-posta → Yeni Üye
  Bildirimi (varsayılan kapalı): hesap etkinleşince iletişim adresine ad,
  kullanıcı adı, e-posta, tarih ve "Kullanıcıyı aç" düğmesi. Saatte en
  fazla 10 mektup (`YeniUyeyiBildir` dinleyicisi).
- **Yöneticinin açtığı hesaba karşılama.** Kullanıcı modalında "Kullanıcıya
  hesap bilgisi gönder" (varsayılan açık). Mektupta parola YAZMAZ: 48 saat
  geçerli, tek kullanımlık "Parolanızı belirleyin" bağlantısı gider
  (`PasswordReset::setupLink`). Parola alanı boş bırakılabilir; o zaman
  kimsenin bilmediği rastgele bir parola konur. Mektup editörden gizlenir.
- **E-posta bildirim tercihleri (Hesabım).** Üye toplu duyuruları
  kapatabilir; yönetici ayrıca site geneli yeni mesaj / yeni üye
  bildirimlerini buradan açıp kapatır. Güvenlik mektupları kapatılamaz ve
  ekranda bu açıkça yazar. Kitle duyurusunda (tüm üyeler / bir rol)
  duyuruyu kapatanlar alıcıdan düşer, özet "3 kişi duyuruları kapattığı
  için atlandı" der; her duyurunun altında imzalı, giriş gerektirmeyen
  "Duyuruları almak istemiyorum" bağlantısı vardır.
- **Panel bildirimleri:** bakım modu açık (uyarı), e-posta doğrulaması
  bekleyen hesap sayısı ve gönderilemeyen e-posta sayısı (kapatılabilir
  bilgi). Son bağlantı e-posta geçmişini "Başarısız" süzgeciyle açar.

### Değişti

- **Giriş ekranı demo parolalarını YALNIZCA demo modunda listeler**
  (`APP_DEMO=true`). Geliştirme modunda (`APP_DEBUG=true`) altı hesap
  "Demo1234!" ile görünüyordu; sihirbazın "geliştirme modu" kutusu açık
  kurulup yayına alınan bir sitede de. Artık yalnızca parolasız bir not
  çıkar ("Örnek veri yüklü; demo hesaplar README'de"). CI'da duman testi
  üçüncü kez `APP_DEBUG=true` ile koşar.
- **"Örnek veriyi kaldır" markayı da kaldırır:** slogan, açıklama, marka
  adı, sosyal hesaplar, anahtar kelimeler, PWA adları ve iletişim
  saatleri nötre döner (`DemoData::MARKA_AYARLARI`). Yalnızca değeri hâlâ
  örnek değerle aynı olanlar; yöneticinin değiştirdiği ayar ve site adı
  kalır.
- **Geliştirme modunda parola sıfırlama açık:** mektup diske yazılır,
  bağlantı doğrulama mektubunda olduğu gibi Panel → E-posta'da yöneticiye
  görünür. Editörden gizleme (`GUVENLIK_SABLONLARI`) değişmedi.
- **Ana sayfa bölümleri menüyle aynı hizada:** içerik genişliği Bootstrap
  `.container` kırılımlarını izler (540/720/960/1140/1320). 820px'de logo
  62px'te, başlık 16px'teydi; artık her genişlikte fark 0.
- **Bildirimler (toast) ön yüzde menünün altından başlar**, telefonda
  altta durur (`safe-area-inset-bottom`). Giriş/çıkış bildirimi menü
  bağlantılarını kapatıyordu.
- **İç sayfalarda başlık bandı kaldırıldı:** Hakkımızda, İletişim, KVKK gibi
  sayfaların üstündeki renkli bant ("Ana Sayfa › …" izi, başlık, özet)
  yok; sayfa adı içerik kartının ilk satırıdır, İletişim doğrudan bilgiler
  ve formla açılır. Özet meta açıklamada, "Ana Sayfa › …" izi arama
  motorları için BreadcrumbList yapısal verisinde (JSON-LD) duruyor.
- **Telefonda özellik ızgarası** 480px altında tek sütun, ikon solda.
- **Kullanıcılar özet kartı** onay bekleyen hesap sayısını da gösterir.
- `giris?demo=…` ile gelinince vurgulanan hesap ekranın ortasına kaydırılır.
- **Kurulum sihirbazı:** "Örnek veriyle kur" kapatılınca, ad değiştirilmediyse
  site adı "Yeni Proje" olur.
- **Ayar açıklamaları** yeniden yazıldı; boş olanlar dolduruldu (ana sayfa
  düğmeleri, SSS, son bant). Bakım modu açıklaması artık editörlerin de
  siteyi gezebildiğini söylüyor. Var olan kurulumlar
  `2026_10_08_010000_ayar_aciklamalari` migration'ıyla güncellenir.

### Düzeltildi

- **Koyu temada kontrast (WCAG AA ≥ 4.5:1).** Marka mavisi hem düğme
  zemini hem yazı rengiydi; koyu zeminde etkin menü bağlantısı 2.6:1,
  karşılama rozeti 1.7:1, üst etiketler 2.4:1 kalıyordu. Yazı için ayrı
  token'lar: `--cy-brand-text`, `--cy-*-text` (rol ve durum rozetleri).
  Bootstrap'in `.text-muted`, tablo hücresi, sekme ve `<code>` renkleri
  de temaya bağlandı. Site ve panelin ölçülen bütün sayfaları iki temada
  AA'yı geçiyor.
- **Toplu e-posta ekranı açılır açılmaz kırmızı hata gösteriyordu**
  ("En az bir e-posta adresi girin"). Alıcı henüz seçilmemişse alıcı
  ucu artık ipucu döner; hatalı adres yine reddedilir.
- **Parola sıfırlama kayıtlarının temizliği** oluşturma tarihine bakıyordu;
  artık süresi dolmuş kayıtları siler (48 saatlik hesap açılış bağlantısı
  ertesi gün silinmesin).
- **"Örnek hesaplar duruyor" uyarısı ham `DELETE` SQL'i öneriyordu.** Artık
  Sistem → Kurulum ve örnek veri → "Örnek veriyi kaldır" düğmesine götürür.
- **Panel bildirim bağlantıları güzel adres kapalıyken bozuluyordu**
  (`panel/sistem#migration` → `index.php?r=panel/sistem%23migration`).
  Sorgu ve çapa artık ayrı ele alınır (`PanelNotices::href`).

---

## [1.6.0] — 2026-10-07

Bağımsız bir test raporunun (Cowork) bütün bulguları üzerine hazırlanan
sürüm. Kurulum iki ekrana indi, ana sayfa panelden yönetilir hâle geldi,
örnek veri tek kaynaktan gelir; parola sıfırlama, KVKK araçları, hesap
silme ve **mobil uygulama için oturum API'si** eklendi. Rol ve yetki
sistemi artık her itmede gerçek bir MySQL 8 kurulumunda otomatik sınanıyor.

### Eklendi

- **Parolamı unuttum.** Tek kullanımlık, 60 dakika geçerli bağlantı;
  veritabanında yalnızca SHA-256 özeti durur. Ekran her durumda aynı
  cevabı verir (adresin kayıtlı olup olmadığı anlaşılmaz), IP ve adres
  başına hız sınırı vardır. Başarılı sıfırlama bütün oturumları, "beni
  hatırla" jetonunu ve API anahtarlarını kapatır. Site e-posta
  gönderemiyorsa bağlantı hiç görünmez.
- **"Parolanız değiştirildi" e-postası.** Parola profilden, sıfırlama
  bağlantısıyla ya da bir yönetici tarafından değiştiğinde sahibine gider
  (`ParolaDegistiBildir` dinleyicisi; `PasswordChanged` olayı artık
  değişikliğin kaynağını taşır).
- **Mobil uygulama API'si.** `POST /api/v1/oturum` (kullanıcı adı/parola →
  Bearer token), `DELETE /api/v1/oturum`, `GET /api/v1/oturumlar`,
  `DELETE /api/v1/oturumlar/{id}`, `GET /api/v1/dosyalar` ve
  `/api/v1/dosyalar/{ad}`. Mobil giriş, tarayıcı girişiyle aynı kaba kuvvet
  korumasından geçer (`Auth::verifyCredentials`). Gövde JSON olabilir.
  Kullanıcı açık cihazlarını **Hesabım → Bağlı cihazlar**'dan görüp tek tek
  kapatır. Rehber: [docs/MOBIL-API.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/docs/MOBIL-API.md).
- **API anahtarı kapsamı:** "yalnız okuma" anahtarı `GET` dışında
  `403 kapsam_yetersiz` alır. Panelden üretilen anahtarın varsayılanı
  yalnız okumadır.
- **Hesabımı sil (KVKK).** Parola ile onaylanır, 7 gün bekler; bu sürede
  giriş yapmak silmeyi iptal eder. Son yönetici kendini silemez.
- **KVKK:** kayıt ve iletişim formlarında aydınlatma metni onayı (ayarla
  kapatılır, seçilen sayfa yayında değilse kutu çıkmaz); iletişim
  mesajlarındaki IP ve tarayıcı bilgisi saklama süresi (varsayılan
  180 gün) dolunca zamanlanmış görevle silinir. "Gizlilik ve KVKK" sayfası
  kurulumla gelir.
- **Ana Sayfa ayar grubu.** Karşılama, teknoloji şeridi, 3 adım,
  özellikler, "Rolleri deneyin", kod örneği, SSS, hakkımızda, iletişim ve
  son bant panelden açılıp kapanır; metinler, düğmeler, özellik/adım/SSS
  listeleri panelde satır satır düzenlenir (yeni ayar tipleri: `liste`,
  `coklu`). `{surum}` `{php}` `{komut}` `{migration}` `{test}` yer
  tutucuları koddan sayılır.
- **Tek kaynaklı örnek veri** (`App\Core\DemoData`): sihirbaz,
  `php cy db:seed`, `php cy demo:reset` (demo modunda 3 saatte bir
  kendiliğinden) ve `php cy demo:temizle` / Panel → Sistem "Örnek veriyi
  kaldır" aynı veriyi kullanır. 6 demo hesap, 3 sayfa, 9 mesaj, 7 e-posta,
  ÇILGIN Yazılım markalı vitrin, Örnek Modül'de her rol ve durumdan kayıt.
- **Sayfa düzenleyici:** 30 dakika geçerli imzalı **önizleme** (taslak
  da görülür), **kapak görseli** (paylaşım görseli olarak da kullanılır),
  editörden **görsel yükleme**; geniş ekranda yapışkan Kaydet, dar ekranda
  altta eylem çubuğu. İçerik sayfalarına WebPage + BreadcrumbList JSON-LD.
- **`php cy serve`**: XAMPP olmadan geliştirme sunucusu.
- Kontrol paneli kartları role göre; modüller `DashboardCards` ile kart
  ekler; üyeye profil tamamlama çubuğu.

### Değişti

- **Kurulum sihirbazı 2 ekran + bitiş:** "Bağlantıyı dene", sınıflandırılmış
  veritabanı hataları (yanlış parola, sunucu yok, yetki…), ad soyad tek alan,
  parola gücü göstergesi, adım adım kurulum raporu, demo hesaplar tablosu.
  Yerel olmayan sunucuda kurulum anahtarı istenir. Kurulum klasörünü
  yalnızca kurulumu yapan oturum ya da Panel → Sistem siler.
- **Ana sayfa yeniden tasarlandı** (mobil öncelikli, açık/koyu panel
  görüntüsü, JSON-LD Organization + WebSite + SoftwareApplication).
- **Panel mobilde:** Kullanıcılar, Mesajlar, E-posta ve Sayfalar tabloları
  768px altında etiketli kartlara döner; bildirimler tek satır ve oturum
  boyunca kapatılabilir.
- Marka adı ayardan (`site_brand()`); koda gömülü "Çılgın Yazılım" kalmadı,
  "CY PHP Starter ile geliştirildi" imzası ayarla kapanır.
- Örnek Modül düğmeleri davranışı söyler: sahibi "Taslağa al", editör
  "Onaya geri gönder" görür (`OrnekPolicy::transitionLabel`).
- jQuery 3.7.0 ve Bootstrap 5.3.0 sıkıştırılmış sürümler (SRI ile doğrulandı).
- `kurulum/demo.sql` kaldırıldı (yerini `DemoData` aldı).

### Düzeltildi

- **Demo kilidi yetki denetiminden önce çalışıyordu**: editör, zaten
  yetkisi olmayan bir işlemde "demo modunda kapalı" mesajı görüyordu.
  Artık önce yetki, sonra demo kilidi (`Middleware::afterAll`).
- Panel üst çubuğu ve site menüsü kaydırınca kayboluyordu
  (`overflow-x: hidden` yapışkan konumlandırmayı bozuyordu → `clip`).
- Hesabım sayfası mobilde ekrandan taşıyordu (ızgara `minmax(0, 1fr)`).
- Yan menüde iki öğe aynı anda etkin görünüyordu.
- Büyük harfli sayfa adresi (`/Hakkimizda`) ikinci bir kopya üretiyordu → 301.
- Doğrulama bağlantısı imzadan önce hesabı sorguluyordu.
- Toast bildirimleri masaüstünde üst menünün üstüne biniyordu.
- 11px yazılar 12px'e çıktı; soluk metin rengi WCAG AA (#64748b).

### Güvenlik

- **Yönetici hesapları** yalnızca yöneticilerce değiştirilir, silinir ya da
  pasife alınır; yönetici rolünü yalnız yönetici verir. Bir yöneticinin ya
  da kendi hesabının e-posta/parolası değişirken işlemi yapanın parolası
  istenir. "Son aktif yönetici" kuralı `SELECT … FOR UPDATE` ile atomik.
- Doğrulama ve parola sıfırlama mektuplarının gövdesi panelde gizli,
  alıcı adresi maskeli.
- İçerikteki `<img>` yalnızca kendi alan adını (ya da `CSP_IMG_SRC`)
  gösterebilir; dış görsel kaydederken silinir.
- Önizleme ve sıfırlama sayfaları `Referrer-Policy: no-referrer` ile
  gönderilir (adres çubuğundaki imza/jeton sızmaz).
- E-posta gönderilemeyen sitede doğrulamalı kayıt formu kendiliğinden kapanır.
- **Kaba kuvvet kilidi her 20 denemede bir atlanabiliyordu.** Giriş denemesi
  yazıldıktan sonra %5 olasılıkla eski kayıtlar budanıyor, deneme numarası
  budamadan SONRA okunuyordu; MySQL'de araya giren DELETE numarayı 0 yaptığı
  için o denemede kilit sorgusu hiçbir şey saymıyor ve kilitli hesaba yapılan
  deneme parola denetimine ulaşıyordu. Numara artık budamadan önce alınır
  (CI'daki MySQL 8 duman testi yakaladı; gerileme testi eklendi).

### Testler

- Birim testleri 180 → **268**: rol matrisi (`tests/rol-matrisi.php` —
  96 rotanın her biri için kim erişebilir; yeni ya da yetkisi değişen rota
  testi kırar), 18 XSS vektörü, önizleme imzası, sıfırlama jetonu, kapsam.
- Duman testi 27 → **50**: demo hesaplarla rol matrisi (GET ve CSRF'li
  POST), editörün reddinin "yetki" mesajıyla gelmesi, demo açık/kapalı,
  pasif/askıda/onay bekleyen giriş reddi, Örnek Modül satır düzeyi, mobil
  API akışı.
- **CI'da gerçek kurulum:** MySQL 8 ile PHP 8.1 ve 8.4'te depo yerleşik
  sunucuda açılır, sihirbaz HTTP üzerinden çalıştırılır
  (`tests/kurulum.php`), duman testi demo modu açık ve kapalı iki kez koşar.
- **Şema kayması testi** (`tests/sema.php`): v1.2.0'dan bugüne her sürümden
  yükseltilen veritabanı, yeni kurulumla sütun, indeks, yabancı anahtar ve
  ayar düzeyinde birebir aynı.

### Güncelleme (1.5.x → 1.6.0)

1. Yedek alın, kodu çekin.
2. `php cy migrate` (SSH yoksa panelde çıkan **"Şimdi çalıştır"**). Tek
   migration (`2026_10_07_020000_surum_1_6`): yeni ayarlar, parola
   sıfırlama tablosu, hesap silme sütunu, API anahtarı kapsam sütunları,
   KVKK sayfası. Var olan ayarlarınıza dokunmaz.
3. Ana sayfanız eskisi gibi görünür; yeni bölümleri **Panel → Ayarlar →
   Ana Sayfa**'dan açın. Örnek vitrini görmek için `php cy db:seed`.
4. Parola sıfırlama, e-posta ayarları yapılmış sitede kendiliğinden açılır.

---

## [1.5.1] — 2026-10-07

Veritabanı performansı. Sık çalışan sorgular yeniden ölçüldü (300 bin
e-posta kaydı, 400 bin giriş denemesi, 100 bin mesaj, 50 bin kullanıcı);
büyüyen tablolarda tam tarama yapan beşi kapatıldı.

### Düzeltildi

- **Giriş denemeleri temizliği kilitleniyordu.** Bir günden eski
  denemeleri silen sorgu (her 20 denemede bir çalışır) `attempted_at`
  indeksi olmadığı için bütün tabloyu tarıyor ve taradığı satırları
  kilitliyordu: silme sürerken gelen giriş denemeleri kilit beklemesine
  düşüyor, kaba kuvvet saldırısı anında gerçek kullanıcılar da giriş
  yapamıyordu. 233 ms + kilit → 0,2 ms, kilit yok.
- **"Bugün gönderilen e-posta" sayacı** tarih sütununu `DATE()`
  fonksiyonuna sardığı için indeks kullanamıyor, gönderilmiş bütün
  kayıtları tarıyordu. Kontrol paneli ve Sistem sayfası her açılışta
  soruyordu: 968 ms → 1,7 ms. "Bugün gelen mesaj" sayacı da aynı
  biçimde düzeltildi (78 ms → 0,3 ms).
- E-posta durum sayaçları dört ayrı `COUNT` yerine tek `GROUP BY`
  (310 ms → 126 ms).

### Eklendi

- İndeksler (`2026_10_07_010000_sorgu_indeksleri` migration'ı ve
  `kurulum/database.sql`):
  - `mail_kayitlari (durum, gonderildi_at)` — bugün gönderilen sayacı
  - `mail_kayitlari (alici_eposta, created_at)` — iletişim formu ve kayıt
    doğrulamasının "bu adrese son X saatte mektup gitti mi?" denetimi;
    alıcı sütununda hiç indeks yoktu
  - `login_attempts (attempted_at)` — eski denemelerin silinmesi
  - `kullanicilar (created_at)` — kontrol panelindeki son eklenenler ve
    14 günlük grafik (43 ms → 0,2 ms)
- Birim testi: `app/` ve `modules/` içinde sütunu fonksiyona sarıp
  karşılaştıran sorgu (ör. `DATE(sutun) = …`) kalmadığını denetler.
  Birim testleri 173 → 180.

### Güncelleme (1.5.0 → 1.5.1)

Bir çekirdek migration var. Kodu çektikten sonra `php cy migrate`
çalıştırın; SSH yoksa panelde çıkan **"1 migration bekliyor → Şimdi
çalıştır"** bağlantısını kullanın. Yalnızca indeks ekler, veri
değiştirmez; InnoDB indeksleri tabloyu kilitlemeden kurar (750 bin
satırda ~5 sn).

---

## [1.5.0] — 2026-10-07

Herkese açık deneme siteleri için demo modu, panelden açılıp kapanan
modüller, kendi modülünüzü yazarken kopyalayacağınız onay akışlı örnek
modül ve baştan sona sadeleşen panel.

### Eklendi

- **Demo modu** (`.env` → `APP_DEMO=true`, ya da sihirbazın Yönetici
  adımındaki **Demo modu** kutusu). Giriş ekranı Yönetici, Editör ve Üye
  örnek hesaplarını kullanıcı adı ve parolasıyla listeler; bir satıra
  tıklamak doğrudan giriş yapar. Giriş normal yoldan, CSRF ve kaba
  kuvvet korumasıyla yapılır.
- **Demo kilidi.** Parolası herkesçe bilinen tam yetkili bir yönetici,
  ilk ziyaretçinin demoyu herkes için bozmasına izin verirdi (parolayı
  değiştirip herkesi dışarıda bırakmak, bakım modunu açmak, asıl
  yöneticiyi silmek, sitenin SMTP'siyle toplu e-posta göndermek). Demo
  modunda örnek hesaplar için hesap bilgileri, kullanıcı yönetimi, site
  ayarları, sistem işlemleri ve e-posta gönderimi kapalıdır; okuma ve
  içerik işleri açıktır. Kurulumda açılan yönetici hesabı kısıtlanmaz.
  Kilit `auth` ara katmanında durur (`App\Core\Demo`).
- **Örnek veride yönetici hesabı** (`ali.yonetici`). Örnek veri artık 5
  hesap içerir: yönetici, editör, üye, pasif, askıda.
- **Örnek modül kurulumda açık gelir.** Sihirbazın Site Ayarları
  adımında **Modüller** bölümü var; `module.json`'da
  `"kurulumda_acik": true` olan modül işaretli gelir. Kurulum modülü açar
  ve tablolarını kurar.
- **Panelden modül aç/kapa:** Sistem Bilgisi → **Modüller** bölümünde her
  modülün yanında bir düğme. Açarken modülün yalnızca KENDİ migration'ları
  çalışır (bekleyen çekirdek migration'ları habersizce çalışmaz); hata
  olursa modül yeniden kapatılır. Kapatmak tablo ve kayıt silmez. Demo
  hesabında kilitlidir. Eskiden SSH'siz hostingte kurulumdan sonra modül
  açmanın yolu yoktu.
- Panel uyarıları: demo hesabına kilitli işlemler; asıl yöneticiye "demo
  modu açık"; demo modu kapalı bir sitede örnek hesaplar duruyorsa
  kırmızı uyarı.
- **Modüller rollere yetki dağıtır:** `module.json` → `"yetkiler"`
  (ör. `"editor": ["stok.view"]`). Eskiden editöre bir modülü açmak için
  çekirdeğin `Role.php`'sini düzenlemek gerekiyordu. Kapalı modülün
  yetkisi kimseye geçmez. `make:module` şablonu bloğu boş listelerle
  üretir.
- **Modül örnek verisi:** `modules/Ad/seeders/*.php`; `php cy db:seed` ve
  sihirbazın "Örnek verileri de yükle"si açık modüllerinkini de çalıştırır.
- **Örnek Modül 1.2.0 — CRUD, onay akışı ve RBAC örneği.** Kayıtların
  sahibi ve durumu var (taslak → onay bekliyor → yayında). **Üye** kayıt
  yazar, düzenler, siler ve onaya gönderir ama yayınlayamaz; yayındaki
  kaydını değiştirirse kayıt yeniden onaya düşer. **Editör** onay
  bekleyenleri görür ve yayınlar (`ornek.publish`), yalnızca kendi kaydını
  düzenler. **Yönetici** her şeyi yapar. Kural `OrnekPolicy`'de; görünüm ve
  denetleyici aynı sınıfa sorar, elle gönderilen yetkisiz istek 403,
  görülemeyen kayda istek 404 alır. Ekranda "Bu modül bir şablondur"
  açıklaması (dosya yapısı, üç adım), "Sizin yetkileriniz", `Role::can()`'in
  gerçek cevabından üretilen yetki matrisi ve "Rastgele 5 kayıt ekle"
  (yönetici). Kurulum 12 rastgele kayıt üretir; sahipleri yönetici, editör
  ve üyeler arasında dağılır. Adım adım rehber: `modules/Ornek/README.md`.
- **Sistem Bilgisi → Kuyruklar kartı:** iş kuyruğu ve e-posta kuyruğu
  birlikte; son başarısız e-postalar; gönderim yöntemi "Kayıt" ise
  mektupların gönderilmeyip `storage/mail`'e yazıldığı açıkça yazar.
  Eskiden yalnızca iş kuyruğu görünüyor, başarısız bir e-posta burada
  hiç çıkmıyordu.
- Hesabım → API anahtarı: yeni anahtar için **Kopyala** düğmesi ve
  listede "anahtarın tamamı yalnızca bir kez gösterilir" açıklaması.
- Kontrol paneli, istatistik yetkisi olmayan rollere (editör, üye) boş
  bir sayfa yerine erişebildikleri bölümlerin kısayollarını gösterir;
  açık modüller kendiliğinden eklenir.
- `Html::toText()`: HTML'den düz metin (özet, meta açıklama, kelime sayısı).
- Birim testleri 112 → 173.

### Değiştirildi

- Giriş ekranındaki demo hesap bölümü yeniden tasarlandı: rol rozeti,
  ad, kullanıcı adı ve parola tek satırda; 56 px dokunma hedefi.
- Örnek hesap temizleme komutu daraltıldı:
  `DELETE FROM kullanicilar WHERE eposta LIKE '%.demo@ornek.com';`
  Eskisi (`'%@ornek.com'`) `@ornek.com` adresiyle açılmış gerçek bir
  yöneticiyi de siliyordu.
- Örnek modülün ekran başlığı "Ornek" → "Örnek Modül".
- **Sistem Bilgisi sayfası sadeleşti:** hafif kartlar (`cy-panel`),
  denetimlerde uyarılar önce ve açıklamalı, geçenler tek satır; bilgi
  kartları sütunlara akar (yanındakinin boyuna gerilip boş kutu
  bırakmaz). Masaüstünde sayfa ~%35 kısaldı.
- **Sade panel tasarımı.** Kontrol panelindeki gradyan karşılama şeridi ve
  renkli istatistik kartları kaldırıldı: tek satır selamlama, düz kartlar,
  her sayı ilgili sayfaya bağlantı. Kartlarda gölge yok, birincil düğme düz
  renk, grafikte boş günler soluk.
- **Panel bildirimleri düzenlendi:** her sayfanın üstündeki uyarılar tek bir
  kutuda alt alta; her biri ikon, kısa başlık, tek cümle ve sağda bağlantı.
  Bilgi bildirimleri (ör. demo hesabı) kapatılabilir, tarayıcı oturumu
  boyunca gizlenir. Sayfa içi uyarı kutuları (`cy-alert`) renkli zemin
  yerine soldaki renk şeridiyle gösterilir; metin okunur kalır.
- Ana sayfadaki özellik ızgarası geniş ekranda 3 + 3 dizilir (eskiden
  4 + 2, ikinci satır yarım kalıyordu).
- Sol menüdeki modül bağlantıları `Module::$menu`'den gelir (künye bir kez
  okunur).
- Panoya kopyalama ortak yardımcıya taşındı (`CY.copy`, `data-copy-target`).
- Duman testi demo modunda demo parolası denetimini atlar (bilinçli
  gösterim).

### Düzeltildi

- Ana sayfadaki Hakkımızda özeti ve sayfaların meta açıklaması cümleleri
  bitişik basıyordu ("Biz kimiz?Bu metni…"): `strip_tags` blok
  sınırlarına boşluk koymuyordu.
- Günlük satırları temiz adres kipinde hep `yol=/` yazıyordu; artık
  isteğin gerçek adresini yazar.

### Güncelleme (1.4.0 → 1.5.0)

Çekirdekte migration yok. Kodu çekmeniz yeterli; demo modu varsayılan
olarak kapalıdır. Var olan bir kurulumda:

- Örnek modülü açmak için panelde Sistem Bilgisi → Modüller'deki düğme
  yeterli (tablolarını da kurar). Komut satırından: `php cy module
  --enable=Ornek` ve `php cy migrate`; doldurmak için `php cy db:seed
  --class=OrnekIcerik`. Modül zaten açıksa `php cy migrate` yeni
  sütunları (sahip, durum, açıklama) ekler.
- Demo modunu açmak için `.env`'ye `APP_DEMO=true` ekleyin. Giriş
  ekranı yalnızca veritabanında duran örnek hesapları listeler;
  `ali.yonetici` hesabı eski kurulumlarda yoktur (yeniden kurulumla
  gelir).

---

## [1.4.0] — 2026-10-06

İkinci güvenlik incelemesinin bulguları. 1.3.0'ın getirdiği iki gerilemeyi
(boşluklu klasör ve Redis oturumunda giriş yapılamaması) kapatır; eski
kurulumların güncellemede korumasız kalmasını önler. Her madde yerelde
yeniden üretildi, düzeltildi ve canlı sınandı.

### Güncelleme adımları (1.2.x ya da 1.3.x → 1.4.0)

1. **Yedek alın** (veritabanı + `.env`).
2. Kodu çekin ve `php cy migrate` çalıştırın. SSH yoksa **Panel → Sistem
   Bilgisi → Migration'ları Çalıştır** (migration'lar çalıştırılmadan da
   giriş yapılabilir; panel her sayfada uyarır). 3 yeni migration var;
   sonuncusu eski kurulum migration'larını **kendiliğinden temel partiye
   (0)** taşır — 1.3.0'daki elle SQL adımı artık gerekmez.
3. `.env`'de **`APP_ENV=production`** ve **`APP_DEBUG=false`** olduğundan
   emin olun. 1.2.1 sihirbazı `local` / `true` yazıyordu; satırları
   silmeyin, değerlerini değiştirin. Debug açık ve site yerel bir adresten
   (localhost, *.test, *.local) açılmıyorsa panel artık her sayfada
   kırmızı uyarı gösterir.
4. `APP_KEY` ve `SESSION_NAME` boşsa doldurun (bkz. `.env.example`).
   Site Cloudflare / yük dengeleyici / ters vekil arkasındaysa
   `TRUSTED_PROXIES` yazın.
5. Kurulum kimliği artık yalnızca `APP_KEY`'den türetilir: bütün
   kullanıcılar bir kez yeniden giriş yapar.
6. Kayıt formu artık varsayılan olarak **e-posta doğrulaması** ister.
   E-posta ayarları yapılmadıysa kayıt formu kapanır; istemiyorsanız
   **Ayarlar → Sistem → Kayıtta E-posta Doğrulaması**'nı kapatın.
7. `php cy api:token` süre verilmezse artık **90 gün** geçerli anahtar
   üretir; süresiz anahtar için `--suresiz` yazın.

### Düzeltildi — yüksek (giriş tamamen bozuluyordu)

- **Klasör adında boşluk ya da Türkçe karakter varsa giriş
  yapılamıyordu.** Oturum çerezinin yolu çözülmüş hâliyle (`/my app/`)
  yazılıyor, tarayıcı ise kodlanmış yolu (`/my%20app/`) karşılaştırıyordu;
  çerez geri gelmediği için her form CSRF hatasıyla düşüyordu. "Beni
  hatırla" çerezi de boşluk yüzünden `setcookie()` içinde hata verip
  sayfayı 500'e düşürüyordu. Çerez yolu artık tarayıcının gönderdiği
  biçimde kodlanır (`Url::cookiePath`).
- **Redis/Memcached oturum sürücüsünde giriş yapılamıyordu.** Oturum
  klasörü sürücüye bakılmadan dosya yoluna çekiliyordu. Artık yalnızca
  `session.save_handler=files` iken değiştirilir.

### Düzeltildi — orta

- **Mail ve iş kuyruğunda kilitlenme (deadlock).** Takılı satırları
  serbest bırakan aralık UPDATE'i her ayırmada çalışıyor, eşzamanlı
  işçilerle çakışıp 1213 üretiyordu; gönderilmiş bir mektup "başarısız
  (Deadlock)" sayılabiliyordu (yeniden kuyruğa alınınca kopya giderdi).
  Artık: temizlik süreç başına dakikada bir; önce numaralar okunur, sonra
  birincil anahtarla güncellenir; 1213/1205 yeniden denenir
  (`Database::retry`); gönderim başarılıysa durum yazılamasa bile mektup
  asla "başarısız" sayılmaz. Tamamlanan iş, silinirken kilitlenme olsa
  bile yeniden çalıştırılmaz. 6 işçi × 3 turda 180 mektup kilitlenmeden,
  tekrarsız gönderildi.
- **Çöken işçinin ayırdığı partinin tamamı kalıcı olarak "başarısız"
  sayılıyordu.** Mektuplar artık TEK TEK, gönderimden hemen önce
  ayrılır (`MailRepository::claimNext`); 15 dakika takılan satır deneme+1
  ile kuyruğa döner, yalnızca deneme hakkı (3) bitince başarısız olur.
- **1.2.1'den yükseltilen kurulumlar korumasız kalıyordu.** Kurulum
  migration'ları 1. partide kaldığı için ilk `migrate:rollback` kurulum
  tablolarını siliyordu; CHANGELOG'daki debug adımı yanıltıcıydı. Artık
  bir migration eski kayıtları temel partiye taşır; çekirdek
  migration'lar `baseline()` ile kendilerini temel partiye yazar (elle
  kurulumda da); panel debug + yerel olmayan adres, bekleyen migration ve
  boş `APP_KEY` için uyarır.
- **Migration çalıştırılmadan güncellenen kurulumda giriş 500
  veriyordu** (`oturum_surumu` sütunu yok). Sütun yoksa sürüm 0 kabul
  edilir, giriş çalışır; bekleyen migration'lar panelden tek tıkla
  çalıştırılır (SSH'siz hostlar için).
- **API anahtarları parola değişiminde ve "Diğer cihazlardan çıkış"ta
  düşmüyordu.** Parola değişince kullanıcının bütün anahtarları silinir;
  "Diğer cihazlardan çıkış" için "API anahtarlarımı da iptal et" kutusu
  eklendi (varsayılan işaretli). Panelden anahtar üretmek mevcut parolayı
  ister (hız sınırlı); "Süresiz" seçeneği kalktı.
- **IP geneli kilit hizmet engellemeye açıktı.** 30 rastgele kullanıcı
  adı yöneticiyi 15 dakika kilitliyor; vekil arkasında bütün site
  kilitleniyor; IPv6'da adres değiştirerek aşılıyor; paralel isteklerle
  sınır aşılıyordu. Artık:
  - deneme parola doğrulanmadan ÖNCE yazılır, yalnızca kendinden önceki
    denemeler sayılır (20 paralel denemeden en fazla 5'i geçer);
  - IP için ham hata yerine **farklı kimlik sayısı** ölçülür ve sert kilit
    yerine giderek artan bekleme (1, 2, 4… sn, en fazla kilit süresi)
    uygulanır;
  - başarılı girişte imzalı **güvenilen cihaz çerezi** verilir; o
    tarayıcı IP yavaşlatmasına takılmaz, kimlik kilidi ona ayrı sayılır
    (parola değişince geçersiz);
  - `TRUSTED_PROXIES` / `TRUSTED_PROXY_HEADER` ile vekil arkasında gerçek
    adres okunur (zincir sağdan okunur, yalnızca güvenilen vekilden);
  - IPv6 /64 bloğu tek adres sayılır; `(ip, attempted_at)` indeksi eklendi.
- **Sihirbazın "dolu veritabanının üstüne yaz" onayı yarıda
  kalabiliyordu.** Kendi tablonuz `kullanicilar`'a yabancı anahtar
  veriyorsa beş tablo silindikten sonra işlem hata veriyor, `.env`
  yazılmıyordu. Yabancı anahtarlar artık hiçbir şeye dokunmadan önce
  denetlenir; onay ekranı silinecek ve dokunulmayacak tabloları adıyla
  listeler.
- **Kayıt formunda dört açık.** "Bu e-posta zaten kayıtlı" mesajı
  hesapları ele veriyordu; başarısız gönderimler sayılmıyordu; zaman
  damgası imzasızdı ve alan gönderilmezse kontrol atlanıyordu; paralel
  isteklerle saatlik sınır aşılıyordu. Artık: **e-posta doğrulaması**
  (tablosuz, `APP_KEY` ile imzalı bağlantı; hesap `onay_bekliyor`
  durumunda bekler, hoş geldin mektubu doğrulamadan sonra gider); kayıtlı
  adreste ekran yeni kayıtla AYNI yanıtı verir, adresin sahibine "zaten
  hesabınız var" mektubu gider; damga imzalı ve zorunlu (iletişim
  formunda da); her gönderim sayılır (saatte 20); sayaçlar kilitli
  dosyada (`Throttle::attempt`) — 10 paralel kayıttan en fazla 5'i açılır.
- **"Beni hatırla" yarışı.** Çalınan çerezle sürekli istek atan saldırgan,
  parola değişimiyle aynı anda yenileme yapınca oturumunu koruyabiliyordu.
  Jeton artık `WHERE hatirla_token = eski AND oturum_surumu = v` ile
  koşullu döndürülür; satır güncellenmezse oturum açılmaz.
- **Her dağıtım herkesi çıkarıyordu** (Deployer/Capistrano gibi sürüm
  klasörlü dağıtımlar). `APP_KEY` doluyken kurulum kimliği yalnızca
  ondan türetilir.

### Düzeltildi — düşük

- Bakım modunda `/api/v1/...` ve `sitemap.xml` artık `503` (+ `Retry-After`)
  döner; bakımı aşma yetkisi olanın anahtarı çalışır, `robots.txt` açık kalır.
- `405` yanıtı `Allow` başlığını taşır; `429` hız sınırları ortak yoldan
  (`HttpException::tooManyRequests`, `Retry-After` ile) döner.
- `//klasor/` ile gelinince taban yolu çift bölüyle (`//klasor`) çıkıyor,
  bağlantılar başka bir siteye gidiyordu; taban artık tek bölüye indirilir.
- `pwa.js` PWA kapatılınca aynı alan adındaki BÜTÜN servis çalışanlarını
  siliyordu; artık yalnızca bu uygulamanın kapsamındakini. Önbellek adları
  kuruluma özel (`cy-v3|<yol>|tür`); kurulumda önceden alınan sayfalar
  çerezsiz istenir; çıkışta sayfa önbelleği silinir.
- `_method` yalnızca POST gövdesinden okunur ve POST yalnızca
  PUT/PATCH/DELETE'e dönüşebilir (`POST /giris?_method=GET` GET gibi
  işleniyordu).
- `make:controller Controller` ve `make:model Die` artık reddedilir;
  üretilen sınıf, dosyanın içe aktardığı bir adla çakışırsa dosya yazılmaz.
- Görsel bellek tahmini gerçekçi hâle geldi (12 MP fotoğraf 128 MB'ta
  işlenir); EXIF yönü küçültülmüş görsele uygulanır ve aynalı yönler
  (2, 4, 5, 7) dahil 8 değerin hepsi desteklenir.
- Doğrulama düzenli ifadelerinde `$` yerine `\z` (sondaki satır sonu
  kabul edilmiyor; 26 kalıp).
- `api:token`: numarasız `--iptal` 1 numaralı anahtarı siliyordu, `--gun=90g`
  sessizce süresiz anahtar üretiyordu — ikisi de artık hata verir; süre
  verilmezse 90 gün. Panelde 99999 gün de reddedilir. API tarihleri
  ISO 8601.
- Yönetici kendi parolasını Kullanıcılar ekranından değiştirince kendi
  oturumu kapanıyordu; "Diğer cihazlardan çıkış" bu cihazın "beni
  hatırla" kaydını da siliyordu. İkisi de düzeldi
  (`Auth::refreshCurrentDevice`).
- Profil formunda tarayıcı otomatik doldurması telefonu bozuyordu:
  "Mevcut parola" alanı yalnızca e-posta değişince görünür, parola
  yöneticileri için gizli kullanıcı adı alanı eklendi.
- `.env.example` kodun okuduğu bütün anahtarları içerir; sihirbazın
  yazdığı `.env`'de eksik 7 anahtar eklendi; `EVENTS_STRICT` `APP_DEBUG`
  yokken artık kapalı.

### Eklendi

- `App\Core\Ip` (vekil desteği, CIDR, IPv6 kovası), `App\Core\Signer`
  (APP_KEY ile HMAC; APP_KEY boşsa `storage/app.key`), `App\Core\Registration`,
  `App\Core\PanelNotices`, `Database::retry()`, `Migration::baseline()`,
  `Input::positiveIntOption()`.
- Ayar: `sistem_kayit_dogrulama`. Durum: `onay_bekliyor`. Rota:
  `kayit/dogrula`, `panel/sistem/migrate`.
- Birim testleri 68 → 112.

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
3. **`.env`'de `APP_ENV=production` ve `APP_DEBUG=false` yapın.**
   *(Düzeltme, 1.4.0: bu madde ilk yayında "satırlar yoksa production /
   false varsayılır" diyordu. 1.2.1 sihirbazı bu satırları
   `APP_ENV=local` / `APP_DEBUG=true` olarak AÇIKÇA yazdığı için
   yükseltilen siteler debug modunda kalıyordu. Satırları silmeyin,
   değerlerini değiştirin.)*
4. Tüm kullanıcılar bir kez yeniden giriş yapar (oturumlar kuruluma
   bağlandı).
5. Eski kurulumlarda sihirbazın çalıştırdığı migration'lar 1. partidedir;
   `migrate:rollback` onları geri alabilir. *(1.4.0 ile gereksiz: oradaki
   migration bu kayıtları kendiliğinden temel partiye taşır. Elle SQL
   çalıştırmayın.)*

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

> 1.2.0 ve 1.2.1'de `config/app.php` içindeki sürüm numarası
> güncellenmemişti; Sistem Bilgisi sayfası bu iki sürümde "1.1.0"
> gösterir. 1.3.0'da düzeltildi.

---

## [1.1.0] — 2026-08-30

Mobil düzenin baştan sona elden geçirildiği ve sürüm numarasının tek
kaynağa indirildiği sürüm.

### Değiştirildi — mobil

- Ön yüz menüsü yapışkan hâle geldi; hamburger açılır panel kendi içinde
  kayıyor, simgesi açıkken çarpıya dönüyor.
- Tema düğmesi hamburgerin dışına alındı — menüyü açmadan erişilebiliyor.
- Ön yüzdeki düğme ve menü satırları 44–46px dokunma hedefine çıkarıldı.
- Ön yüz form alanlarına 16px kuralı eklendi (iOS'un otomatik
  yakınlaştırması artık düzeni bozmuyor); daha önce yalnızca panelde vardı.
- Ana sayfadaki hero eylemleri telefonda tek sütuna iniyor.
- İletişim sayfasında telefonda **form önce**, bilgi kartları sonra geliyor.
- Alt bilgi sütunları telefonda alt alta diziliyor; sabit WhatsApp düğmesi
  artık alt bilginin son satırını kapatmıyor.
- Panelde özet kartların tek sütuna inme sınırı 400px'ten 360px'e
  çekildi — eski sınır iPhone 12/13/14 (390px) ve SE (375px) dahil
  neredeyse her telefonu kapsıyor, kontrol paneli gereksiz yere uzuyordu.

### Düzeltildi

- `site.css` ile `site-sections.css` arasındaki çakışan kurallar
  temizlendi. Bunlardan biri telefonda hero kartının logosunu tamamen
  gizliyordu (`.cy-hero__logo { display: none }`).
- Sürüm numarası tek kaynağa indirildi (`config/app.php` → `'version'`).
  Üç farklı yerde üç farklı değer duruyordu: `kurulum/database.sql`
  "2.1.0", Sistem Bilgisi varsayılanı "1.0.0", GitHub sürüm etiketi
  "v1.0.0".

### Eklendi

- Alt bilgide **Örnek Kodlar** sütunu: kod kütüphanesi, şablonun sayfası
  ve depo bağlantısı.
- `icon()` yardımcısına `book` ve `code` simgeleri.

### Güncelleme

`php cy migrate` çalıştırın. Tek migration var, veritabanındaki ölü
`sistem_surum` ayarını siler.

---

## [1.0.0] — 2026-08-18

İlk kararlı sürüm.

### Eklendi

- **Görsel kurulum sihirbazı:** gereksinim denetimi, veritabanı bağlantısı,
  `.env` üretimi, yönetici hesabı; komut satırı gerektirmez ve son adımda
  kendi klasörünü siler.
- **MVC benzeri yapı:** `app/`, `config/`, `routes/`, `views/` ayrımı;
  temiz adresli yönlendirici, ara katmanlar, Repository deseni.
- **Kimlik ve oturum:** giriş/kayıt/çıkış, "beni hatırla", rol-yetki
  sistemi, CSRF koruması, sertleştirilmiş oturum.
- **Yönetim paneli:** kullanıcılar, mesajlar, sayfa yönetimi, bölüm bölüm
  site ayarları, logo ve favicon yükleme, sistem bilgisi.
- **Ön yüz:** ana sayfa, iletişim formu, içerik sayfaları, SEO alanları.
- **PWA:** panelden yönetilen künye, servis çalışanı, çevrimdışı sayfa.
- **Altyapı:** PDO/MySQL, migration ve seeder, önbellek, olaylar, kuyruk,
  zamanlayıcı, e-posta, REST API temeli, modül sistemi ve `php cy` konsolu.

[1.5.1]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/CilginYazilim/cy-php-starter/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.0.0
