<div align="center">

# PHP Başlangıç Şablonu (CY PHP Starter)

### Sıfır bağımlılıklı PHP 8 başlangıç şablonu — her yeni projeye buradan başlayın

**Kurulum sihirbazı · rol tabanlı yönetim paneli · konsol · migration · kuyruk · olay · modül sistemi · REST API · PWA — hepsi hazır.**

[![Sürüm](https://img.shields.io/badge/Sürüm-1.5.0-0b5cb5?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
![Bağımlılık: sıfır](https://img.shields.io/badge/Bağımlılık-Sıfır-16a34a?style=flat-square)
[![Testler](https://img.shields.io/badge/Birim_testi-173-16a34a?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/actions)
[![Lisans](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/blob/main/LICENSE)

[**▶ Canlı Demo**](https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/) · [PHP Başlangıç Şablonu sayfası](https://cilginyazilim.com/kutuphane/php-baslangic-sablonu) · [Çılgın Yazılım](https://cilginyazilim.com)

**[📖 Sistem Kılavuzu](https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md)**
· **[💡 Kod Kütüphanesi](https://cilginyazilim.com/kutuphane)**
· **[📝 Değişiklik Günlüğü](https://github.com/CilginYazilim/cy-php-starter/blob/main/CHANGELOG.md)**

<sub>Zero-dependency PHP 8 starter template (boilerplate) with a setup wizard, role-based admin panel, migrations, queue, events, modules, REST API and PWA. No Composer, no framework, no CDN.</sub>

</div>

---

<div align="center">

## Canlı Demo

**Kurulum yok, kayıt yok, indirme yok — tarayıcınızdan 3 saniyede deneyin.**

<a href="https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/"><img src="https://img.shields.io/badge/CANLI_DEMOYU_A%C3%87-0b5cb5?style=for-the-badge&logo=googlechrome&logoColor=white&labelColor=061321" alt="PHP başlangıç şablonunun canlı demosunu aç" height="42"></a>
<a href="https://cilginyazilim.com/kutuphane/php-baslangic-sablonu"><img src="https://img.shields.io/badge/KAYNAK_KODU_%C4%B0NCELE-0ea5e9?style=for-the-badge&logo=readthedocs&logoColor=white&labelColor=061321" alt="PHP başlangıç şablonunun kaynak kodunu incele" height="42"></a>
<a href="https://github.com/CilginYazilim/cy-php-starter/archive/refs/heads/main.zip"><img src="https://img.shields.io/badge/ZIP_%C4%B0ND%C4%B0R-16a34a?style=for-the-badge&logo=github&logoColor=white&labelColor=061321" alt="PHP başlangıç şablonunu ZIP olarak indir" height="42"></a>

<br><br>

<a href="https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/" title="Canlı demoyu açmak için tıklayın">
  <img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/05-panel-ozet.png" alt="PHP başlangıç şablonu yönetim paneli: özet kartları, kayıt grafiği ve son mesajlar" width="860">
</a>

<sub>▲ Görsele tıklayarak demoyu açabilirsiniz</sub>

</div>

> **`kurulum/` adresini açın; dört adımda çalışan bir site ve yönetim paneli elde edin.**

---

## Ekran Görüntüleri

Aşağıdaki kareler bu depodaki kodun **kurulduktan sonraki** hâlidir; hepsi
`kurulum/` sihirbazı tamamlandıktan sonra, örnek veriyle çekilmiştir.

### Kurulum sihirbazı

Şablonun ayırt edici yanı burada başlar: `.env` dosyasını elle yazmaz,
veritabanını elle açmazsınız. Sihirbaz önce sunucunuzu denetler, sonra
şemayı kurar ve `.env` dosyasını sizin için üretir.

<table>
<tr>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/02-kurulum-gereksinimler.png" alt="PHP kurulum sihirbazı: PHP sürümü ve eklenti gereksinim denetimi"></td>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/03-kurulum-veritabani.png" alt="PHP kurulum sihirbazı: MySQL veritabanı bağlantı adımı"></td>
</tr>
<tr>
<td align="center"><sub><b>1. adım</b> — gereksinim denetimi</sub></td>
<td align="center"><sub><b>2. adım</b> — veritabanı bağlantısı</sub></td>
</tr>
</table>

### Panel

<img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/05-panel-ozet.png" alt="PHP yönetim paneli: özet kartları, 14 günlük kayıt grafiği ve son mesajlar" width="100%">

<sub>Kontrol paneli. Kartlar, 14 günlük kayıt grafiği ve son mesajlar — hepsi veritabanından, sahte veri yok.</sub>

<table>
<tr>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/06-panel-kullanicilar.png" alt="Kullanıcı yönetimi: sunucu taraflı tablo, rol rozetleri ve toplu işlem"></td>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/07-panel-mesajlar.png" alt="Mesaj yönetimi: iletişim formundan gelen kayıtlar ve okundu durumu"></td>
</tr>
<tr>
<td align="center"><sub>Kullanıcılar — rol rozetleri, arama, toplu işlem</sub></td>
<td align="center"><sub>Mesajlar — iletişim formundan gelen kayıtlar</sub></td>
</tr>
<tr>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/08-panel-ayarlar.png" alt="Site ayarları: ayar tanımından otomatik üretilen form"></td>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/09-panel-sistem.png" alt="Sistem bilgisi sayfası ve güvenlik denetim listesi"></td>
</tr>
<tr>
<td align="center"><sub>Site ayarları — form, ayar <b>tanımından</b> üretilir</sub></td>
<td align="center"><sub>Sistem bilgisi — güvenlik denetim listesi</sub></td>
</tr>
</table>

### Ön yüz, koyu tema ve mobil

<table>
<tr>
<td width="40%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/01-anasayfa.png" alt="PHP başlangıç şablonunun ön yüz ana sayfası"></td>
<td width="40%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/11-koyu-tema.png" alt="Yönetim panelinin koyu tema görünümü"></td>
<td width="20%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/12-mobil.png" alt="Yönetim panelinin mobil telefon görünümü"></td>
</tr>
<tr>
<td align="center"><sub>Ön yüz</sub></td>
<td align="center"><sub>Koyu tema — tek CSS değişken seti</sub></td>
<td align="center"><sub>Mobil</sub></td>
</tr>
</table>

---

## Bu şablon nedir?

**CY PHP Starter**, Çılgın Yazılım'ın açık kaynaklı **PHP başlangıç
şablonudur** (starter kit, boilerplate). Her yeni PHP projesinde baştan
yazdığınız teknik altyapıyı — kurulum sihirbazı, giriş ve rol-yetki
sistemi, yönetim paneli, migration, kuyruk, REST API, PWA — hazır ve test
edilmiş olarak sunar.

Belirli bir iş uygulaması **değildir**; ERP, CRM, CMS, blog, SaaS, REST
API ya da şirket içi uygulama — hepsinin altına aynı sağlam temeli koyar.

**Composer yok, framework yok, CDN yok.** Saf PHP 8.1+ ve PDO/MySQL ile
yazılmıştır; paylaşımlı hostingde bile komut satırı olmadan kurulur.
İndirin, `kurulum/` adresini açın, birkaç adımda çalışan bir site ve
yönetim paneli elde edin.

### Tek cümlelik felsefe

> Core, uygulamanın **ne iş yaptığını** bilmez. Yalnızca **nasıl
> çalıştığını** sağlar.

```php
// YANLIŞ — çekirdek iş mantığını biliyor
UserService::register($data);
ErpPersonel::olustur($user);

// DOĞRU — çekirdek duyurur, modül dinler
Events::dispatch(new UserRegistered($user));
```

### Framework mü, bu şablon mu?

Laravel ya da Symfony gibi tam bir framework çoğu proje için doğru
seçimdir. Bu şablon, şu durumlar için yazıldı:

| İhtiyaç | CY PHP Starter | Tam framework |
|---|---|---|
| SSH'siz paylaşımlı hosting | Tarayıcıdan kurulur, migration'lar panelden çalışır | Çoğunlukla Composer ve komut satırı ister |
| Bağımlılık güncellemesi | Yok — `vendor/` klasörü yok | Düzenli `composer update` |
| Kodu baştan sona okuyabilmek | Saf PHP, küçük ve açıklamalı çekirdek | Framework'ün kendi kavram katmanı |
| Hazır paket ekosistemi | Yok — ihtiyacınızı kendiniz yazarsınız | Çok geniş |

---

## Neler hazır?

| Katman | İçerik |
|---|---|
| **Kurulum** | Adım adım sihirbaz · `.env` üretimi · tabloları **ve migration'ları** kurar · yönetici hesabı · isteğe bağlı örnek veri · tek tuşla kendini silme |
| **Kimlik** | Giriş/kayıt/çıkış · **kayıtta e-posta doğrulaması** · **beni hatırla** · güvenilen cihaz · rol-yetki · kaba kuvvet koruması · sertleştirilmiş oturum |
| **Yönlendirme** | Temiz SEO adresleri · `{parametre}` · GET/POST/PUT/PATCH/DELETE · gruplar |
| **Hata yönetimi** | Merkezi işleyici · ölümcül hata yakalama · geliştirici ekranı · güvenli 404/403/419/500 |
| **Günlük** | Kanal bazlı (`app` `error` `security` `auth` `mail` `queue`) · parola maskeleme · rotasyon |
| **Veritabanı** | Migration + rollback + parti · seeder · Repository deseni |
| **Depolama** | `public` / `private` disk · 9 katmanlı yükleme güvenliği · güvenli indirme |
| **Önbellek** | Dosya / veritabanı / kapalı sürücüleri · `remember()` |
| **Olaylar** | Yayıncı-dinleyici · hata yalıtımı · test için `fake()` |
| **Kuyruk** | Veritabanı kuyruğu · atomik ayırma · katlanan yeniden deneme · kilitlenmeye dayanıklı |
| **Zamanlayıcı** | Tek cron satırı · üst üste binme koruması |
| **E-posta** | SMTP / mail() / diske yazma · toplu gönderim · kuyruk · panel arayüzü |
| **REST API** | Standart yanıt zarfı · süreli Bearer anahtarı (yalnızca SHA-256 özeti saklanır) · hız sınırı · sayfalama |
| **Modüller** | **Panelden tek tıkla aç/kapa** · kendi rotaları, tabloları, görünümleri, menüsü, **rollere dağıttığı yetkileri** ve örnek verisi · CRUD, onay akışı ve RBAC'ı gösteren **Örnek Modül** kurulumda açık gelir |
| **Demo modu** | Herkese açık deneme sitesi için: giriş ekranında Yönetici / Editör / Üye ile **tek tıkla giriş** · örnek hesaplara parola, kullanıcı, ayar ve e-posta kilidi |
| **PWA** | Panelden yönetilen künye (ad, simge, açılış adresi, görüntüleme modu, renk) · servis çalışanı · çevrimdışı sayfa |
| **İçerik** | **Sayfa yönetimi** — zengin metin editörü · adres (slug) üretimi · taslak/yayın · menü · sayfa bazlı SEO |
| **SEO** | Temiz adresler · canonical · Open Graph · başlık şablonu · dinamik sitemap.xml & robots.txt (panelden ek kural) |
| **Ayarlar** | Bölüm bölüm sayfalar · durum özetli genel bakış · kapsamlı kaydetme · logo **ve favicon** yükleme |
| **İletişim** | Form + spam koruması · **WhatsApp düğmesi** (hazır mesajla) · sosyal medya bağlantıları |
| **Tema** | Tek renk seçin, panelin ve sitenin tamamı yeniden renklensin |
| **Mobil** | Ön yüz ve panelin tamamı mobil öncelikli · 44px dokunma hedefleri · yapışkan menü · iOS yakınlaştırma ve çentik payı çözülmüş |
| **Konsol** | `php cy` — 21 komut, üreteçler dahil |
| **Testler** | 173 birim testi (veritabanı gerektirmez) · kurulu siteye duman testi · GitHub Actions'ta PHP 8.1–8.4 |

---

## Kurulum

### Gereksinimler

- PHP **8.1+** (`pdo_mysql`, `mbstring`, `json`; `gd` ve `fileinfo` önerilir)
- MySQL 5.7+ / MariaDB 10.3+
- Apache (`mod_rewrite`) ya da Nginx

### Adımlar

```bash
git clone https://github.com/CilginYazilim/cy-php-starter
```

1. Klasörü sunucunuza koyun
2. Tarayıcıda **`http://siteniz/kurulum/`** adresini açın
3. Sihirbazı tamamlayın
4. Son adımdaki düğmeyle **`kurulum/` klasörünü silin**

Hepsi bu. **Komut satırı gerekmez** — sihirbaz `.env` dosyasını yazar,
tabloları kurar, migration'ları çalıştırır (önbellek, kuyruk, API
anahtarları) ve yönetici hesabınızı açar. Paylaşımlı hostingde de
eksiksiz kurulur.

> **Sihirbaz kendini kilitler.** Kurulum bitince `.env` ve
> `storage/installed.lock` yazılır; bu dosyalardan biri varken sihirbaz
> hiçbir adımı çalıştırmaz (veritabanı erişilemese bile). Yeniden kurmak
> için ikisini de **sunucudan elle** silin — tarayıcıdan yeniden kurulum
> bilerek mümkün değildir.

> **Varsayılan ortam yayındır** (`APP_ENV=production`, `APP_DEBUG=false`).
> Kendi bilgisayarınızda hata ayrıntılarını görmek istiyorsanız sihirbazın
> "Site Ayarları" adımında **Geliştirme ortamı** kutusunu işaretleyin.

> **Örnek veri:** Son adımda "Örnek verileri de yükle" kutusu vardır.
> Şablonu ilk kez deniyorsanız işaretleyin — 5 örnek hesap (yönetici,
> editör, üye, pasif, askıda; parola `Demo1234!`) ve mesajlarla listeleri
> dolu görürsünüz. Gerçek bir projeye başlıyorsanız **boş bırakın**,
> veritabanınız tertemiz kalır.

> **Demo modu (herkese açık deneme sitesi):** Son adımdaki **Demo modu**
> kutusu (ya da `.env` → `APP_DEMO=true`) giriş ekranına bir "Demo
> hesaplar · tek tıkla giriş" listesi koyar: Yönetici, Editör ve Üye —
> ziyaretçi parola yazmadan, satıra tıklayarak girer. Örnek hesaplarla hesap bilgileri, kullanıcılar, site
> ayarları ve e-posta gönderimi kilitlidir; kurulumda açtığınız yönetici
> hesabı kısıtlanmaz. **Gerçek bir sitede işaretlemeyin.**

> **Örnek Modül kurulumda açık gelir.** Sihirbazın Site Ayarları
> adımındaki **Modüller** bölümünde işaretlidir; kurulum modülü açar ve
> tablosunu kurar (örnek veri seçildiyse 12 rastgele kayıtla). Panelde
> **Örnek Modül** ekranı rol tabanlı yetkiyi (RBAC) canlı gösterir:
> yönetici her kaydı yönetir, editör yalnızca kendi kaydını, üye
> yalnızca yayındakileri görür; ekrandaki yetki matrisi kodun o anki
> cevabıdır. İstemiyorsanız kutuyu boşaltın.

> **Kayıt formu e-posta doğrulamasıyla gelir.** Yeni hesap, e-postadaki
> bağlantıya tıklanana kadar giriş yapamaz; form, adresin zaten kayıtlı
> olup olmadığını da belli etmez. E-posta ayarları (Panel → Ayarlar →
> E-posta) yapılmadıysa kayıt formu kendiliğinden kapalı kalır. Doğrulamayı
> **Ayarlar → Sistem → Kayıtta E-posta Doğrulaması** ile kapatabilirsiniz.

> **`mod_rewrite` yoksa:** `.env` içinde `APP_PRETTY_URLS=false` yapın.
> Uygulama `index.php?r=…` biçimine döner, başka hiçbir şey değişmez.

---

## Klasör yapısı

```
├── index.php               Web giriş noktası
├── cy                      Konsol giriş noktası (php cy …)
├── sw.js                   Servis çalışanı (PWA)
│
├── app/
│   ├── bootstrap.php       Web + CLI ortak önyükleme
│   ├── Core/               ÇEKİRDEK (iş mantığı içermez)
│   │   ├── Api/ Cache/ Console/ Database/ Events/ Exceptions/
│   │   ├── Log/ Mail/ Modules/ Queue/ Schedule/ Storage/
│   │   └── Auth Config Csrf Database Env ErrorHandler Flash
│   │       Middleware RateLimiter Request Response Router
│   │       Session Setting Uploader Url Validator View
│   ├── Events/ Listeners/ Jobs/
│   ├── Models/             Entity'ler (ORM DEĞİL) + Role
│   ├── Repositories/       SQL yalnızca burada
│   ├── Http/Controllers/   Panel · Api · Site
│   └── Support/helpers.php
│
├── config/                 app db session log cache queue api storage
│                           upload security validation events
├── routes/                 web.php · events.php · schedule.php
├── database/               migrations/ · seeders/
├── modules/                Eklenebilir modüller (Ornek/ = çalışan örnek)
├── views/                  layouts · partials · emails · errors · sayfalar
├── assets/                 css · js · images (CDN yok)
├── storage/                logs · cache · files · mail (web'e kapalı)
├── upload/                 Yüklenen görseller (PHP çalıştırma kapalı)
├── tests/                  unit.php · smoke.php (web'e kapalı)
└── kurulum/                Sihirbaz + database.sql + demo.sql (sonra SİLİN)
```

### İsteğin yolculuğu

```
Tarayıcı → .htaccess → index.php → bootstrap → Session/Güvenlik
   → Ayarlar → Modüller → Router → Middleware → Controller
   → Repository → View / JSON
```

---

## Konsol

```bash
php cy                      # tüm komutlar
php cy yardim migrate       # ayrıntılı yardım
```

```bash
# Şema
php cy make:migration "urunlere aciklama ekle"
php cy migrate                     php cy migrate --pretend
php cy migrate:status              php cy migrate:rollback --step=3
php cy migrate:fresh --seed

# Üreteçler
php cy make:module Stok            # çalışır durumda CRUD modülü
php cy make:controller Urun --api
php cy make:model Urun --repository
php cy make:seeder Urun

# Modüller
php cy module                      php cy module --enable=Stok

# Bakım
php cy cache:clear --expired       php cy config:cache
php cy log:purge --list            php cy queue:work --max=30
php cy schedule:run --list         php cy mail:test ali@ornek.com

# API anahtarı (süre verilmezse 90 gün; süresiz için açıkça --suresiz)
php cy api:token admin "Mobil uygulama" --gun=90
php cy api:token admin --liste     php cy api:token admin --iptal=3
```

> **Çekirdek migration'lar geri alınamaz.** Şablonla gelen her migration
> (sihirbazın kurdukları ve güncellemelerle gelenler) "temel parti" (0)
> olarak işaretlenir; `migrate:rollback` ve `migrate:fresh` yalnızca
> SİZİN eklediğiniz ve modüllerden gelen migration'ları etkiler.
> `php cy migrate:status` bunları `0 (kurulum)` diye gösterir.
>
> **SSH yoksa:** bekleyen migration'lar **Panel → Sistem Bilgisi**
> sayfasındaki düğmeyle çalıştırılır; panel her sayfada uyarır.

**Cron — tek satır yeter:**

```cron
* * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
```

---

## REST API

Dış istemciler (mobil uygulama, başka bir sunucu) `/api/v1/…` uçlarına
**Bearer anahtarıyla** bağlanır. Anahtar **Panel → Hesabım → API
Anahtarları** ekranından ya da `php cy api:token` ile üretilir; açık hali
yalnızca bir kez gösterilir, veritabanında yalnızca SHA-256 özeti durur.

```bash
curl -H "Authorization: Bearer cy_…" https://siteniz.com/api/v1/ben
```

| Uç | Koruma | Açıklama |
|---|---|---|
| `GET api/v1/ben` | anahtar | Anahtarın sahibi (bağlantı sınaması) |
| `GET api/v1/kullanicilar?sayfa=1&boyut=25&ara=…` | anahtar + `users.view` | Sayfalanmış kullanıcı listesi |
| `GET api/v1/sayfalar` | anahtarsız, hız sınırlı | Yayındaki içerik sayfaları |
| `GET api/v1/sayfalar/{slug}` | anahtarsız, hız sınırlı | Tek sayfanın içeriği |

- Anahtarla gelen istekler **durumsuzdur**: oturum açılmaz, çerez dönmez.
  Anahtar iptal edildiği an erişim biter.
- Anahtar, sahibinin yetkilerinden fazlasına erişemez; sahibi pasife
  alınırsa anahtarı da çalışmaz.
- Panelden anahtar üretmek **mevcut parolayı** ister; süre 30/90/180/365
  gün seçilir. Parola değişince kullanıcının **bütün anahtarları iptal
  edilir**; "Diğer cihazlardan çıkış" da isteğe bağlı olarak iptal eder.
- Tarihler ISO 8601 biçimindedir (`2026-10-06T14:05:00+03:00`).
- Bakım modunda API de `503` döner (bakımı aşma yetkisi olanın anahtarı hariç).
- Kendi uçlarınız için örnek: `app/Http/Controllers/Api/V1Controller.php`
  ve `routes/web.php` içindeki `api/v1` grubu.

---

## Testler

```bash
php tests/unit.php                                   # veritabanı gerektirmez
php tests/smoke.php http://localhost/proje           # kurulu siteye HTTP denetimi
php tests/smoke.php http://localhost/proje --kullanici=admin --parola=… --api=cy_…
```

`unit.php` 173 testi veritabanı olmadan çalıştırır. `smoke.php` siteyi
değiştirmez: kurulum kilidi, gizli dosyalar (`.env`, `.git/`), açık
yönlendirme, kaba kuvvet kilidi, kullanıcı tespiti, oturum çerezi, servis
çalışanı ve API'yi dışarıdan sınar. Kısa sürede arka arkaya
çalıştırırsanız IP kilidi devreye girer; ilgili testler "atlandı" görünür
(koruma çalışıyor demektir).

GitHub Actions her itmede PHP 8.1–8.4 üzerinde sözdizimi denetimi ve
birim testlerini çalıştırır (`.github/workflows/ci.yml`).

---

## Modül eklemek

```bash
php cy make:module Stok
php cy module --enable=Stok
php cy migrate
```

Panelde `/panel/stok` hazır: listeleme, ekleme, silme. Modül kendi
rotalarını, tablolarını, görünümlerini ve menü girdisini taşır.

SSH erişimi yoksa modülü panelden açın: **Sistem Bilgisi → Modüller**
bölümündeki düğme modülü açar ve tablolarını kurar. Kapatmak hiçbir
veriyi silmez; menüsü, sayfaları ve yetkileri devreden çıkar.

**Kapalı modül hiç yüklenmez** — rotaları tanımlanmaz, sınıfları
yüklenmez, olayları dinlenmez.

Modül yetkilerini kendisi dağıtır — çekirdeğin `Role.php`'sine
dokunulmaz:

```json
"yetkiler": { "editor": ["stok.view", "stok.create"], "uye": ["stok.view"] }
```

### Örnek Modül: kopyalanacak şablon

`modules/Ornek` kendi modülünüzü yazarken örnek alacağınız, kurulumda
açık gelen eksiksiz bir modüldür: liste, ekleme, düzenleme, silme ve
**onay akışı** (üye yazar, editör onaylar, sonra yayına çıkar).

| Rol | Görür | Yapar |
|---|---|---|
| Yönetici | Her kaydı | Her kaydı düzenler, siler, yayınlar |
| Editör | Yayındakileri, onay bekleyenleri, kendi kayıtlarını | Onaylar ve yayınlar; yalnızca kendi kaydını düzenler |
| Üye | Yayındakileri ve kendi kayıtlarını | Yazar, düzenler, onaya gönderir; **yayınlayamaz** |

Rol yetkisi `module.json`'da, kayıt düzeyi kural `OrnekPolicy`'dedir.
Görünüm ve denetleyici aynı sınıfa sorar; elle gönderilen yetkisiz
istek 403, görülemeyen kayda istek 404 alır. Adım adım rehber:
[modules/Ornek/README.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/modules/Ornek/README.md).

`module.json` içinde `"kurulumda_acik": true` yazan her modül
sihirbazda işaretli gelir.

---

## Sık kullanılanlar

```php
// Adres ve varlık (hepsi köke göreli)
url('panel/ayarlar');            asset('css/site.css');

// Ayarlar
config('db.host');               // config/ dosyaları (geliştirici)
setting('site_adi');             // ayarlar tablosu (yönetici, panelden)

// Yetki
can('users.delete');

// Marka rengi (Ayarlar → Sistem → Tema Rengi)
Theme::brand();                  // '#7c3aed' — doğrulanmış
Theme::styleTag();               // düzenlere basılan <style> bloğu

// Günlük
Logger::info('…', ['id' => 3], 'auth');
Logger::security('Şüpheli istek', ['ip' => $ip]);

// Önbellek
Cache::remember('rapor', 900, fn () => $agirHesap());

// Olay
Events::dispatch(new UserRegistered($user));

// Kuyruk
Queue::push(new RaporUret(3));

// Dosya
Uploader::store($request->file('belge'), ['disk' => 'private', 'group' => 'belge']);

// API yanıtı
ApiResponse::paginated($items, $total, $page, $perPage);
```

---

## Örnek kodlar ve belgeler

Şablonun her parçası için çalışan örnek kod, açıklamalı anlatım ve
kopyalanabilir parçacıklar **Çılgın Yazılım Kod Kütüphanesi**'nde:

| Kaynak | Bağlantı |
|---|---|
| Tüm konular | [Çılgın Yazılım Kod Kütüphanesi](https://cilginyazilim.com/kutuphane) |
| Bu şablonun tanıtım sayfası | [PHP Başlangıç Şablonu](https://cilginyazilim.com/kutuphane/php-baslangic-sablonu) |
| Çalışan örnek | [Canlı demo](https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/) |
| Mimari ve genişletme rehberi | [Sistem Kılavuzu (SISTEM.md)](https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md) |
| Sürüm notları | [Değişiklik Günlüğü (CHANGELOG.md)](https://github.com/CilginYazilim/cy-php-starter/blob/main/CHANGELOG.md) · [GitHub sürümleri](https://github.com/CilginYazilim/cy-php-starter/releases) |
| Kaynak kod | [GitHub deposu](https://github.com/CilginYazilim/cy-php-starter) |

Bu bağlantılar sitenin **alt bilgisinde de sabit durur** (Örnek Kodlar
sütunu); kurduğunuz projede gezinirken bir tık uzaktadır.

---

## Mobil

Ön yüz ve panel **ayrı ayrı mobil için tasarlanmıştır** — masaüstü
düzeninin küçültülmüş hâli değildir.

| Konu | Ön yüz | Panel |
|---|---|---|
| Menü | Yapışkan üst çubuk; hamburger açılır panel, kendi içinde kayar | Sol menü off-canvas çekmeceye döner (arka plan örtüsü + ESC) |
| Tema düğmesi | Hamburgerin **dışında** — menüyü açmadan tek dokunuş | Üst çubukta sabit |
| Dokunma hedefleri | Düğmeler ≥ 44px, menü satırları 46px | Düğmeler ≥ 44px, sayfalama 40px |
| Formlar | Alanlar 16px — iOS'un otomatik yakınlaştırması engellenir | Aynı |
| Yerleşim | Hero eylemleri tek sütun; iletişimde **form önce** gelir | Özet kartlar 2 sütun; tablolarda ikincil sütunlar ad hücresine iner |
| Modallar | — | Alttan açılan sayfa görünümü, gövde kendi içinde kayar |
| Çentikli ekran | `env(safe-area-inset-*)` payları | Aynı |

Kırılma noktaları iki yüzde de aynıdır: `992px` (tablet) ve `768px`
(telefon); `360px` altı için ayrıca sadeleştirme vardır.

---

## Güvenlik

Şablon iki ayrı güvenlik incelemesinden geçti (1.3.0 ve 1.4.0). Her bulgu
yerel bir kurulumda yeniden üretildi, düzeltildi ve testle sınandı.

| Tehdit | Önlem |
|---|---|
| SQL Injection | Hazırlıklı sorgular; sıralama sütunu beyaz listeden |
| XSS | `e()` kaçışlama + CSP (`script-src 'self'`, satır içi JS yok) |
| CSP çakışması | Analytics kodunun adresi ve sha256 özeti otomatik tanıtılır; politika gevşetilmez |
| CSRF | Her POST'ta token (form alanı veya `X-CSRF-Token`) |
| Oturum çalma | `httponly` + `samesite` + `secure` + parmak izi + yenileme |
| Oturum karışması | Kuruluma özel çerez adı, çerez yolu ve oturum klasörü (`storage/sessions`); oturum `APP_KEY`'e bağlı |
| Parola değişikliği | Diğer cihazlardaki oturumlar, "beni hatırla" jetonu ve **API anahtarları** anında geçersiz ("oturum sürümü"); parolayı değiştiren cihaz açık kalır |
| Çalınan "beni hatırla" çerezi | Jeton her kullanımda KOŞULLU döndürülür; eski jeton ve parola değişiminden sonraki jeton çalışmaz |
| Kaba kuvvet | Deneme parola doğrulanmadan ÖNCE yazılır (paralel istekler sınırı aşamaz); kimlik kilidi + IP başına **farklı kimlik** sayan, giderek artan bekleme; IPv6 /64 tek adres sayılır; güvenilen cihaz takılmaz; vekil arkasında `TRUSTED_PROXIES` |
| Kullanıcı tespiti | Var olan/olmayan hesap aynı sürede ve aynı mesajla yanıtlanır; kayıt formu kayıtlı adreste de aynı yanıtı verir |
| Kayıt formu kötüye kullanımı | E-posta doğrulaması (imzalı, tablosuz bağlantı) · imzalı ve zorunlu form zaman damgası · hatalı gönderimler de sayılır · kilitli sayaç |
| Açık yönlendirme | "Girişten sonra dön" adresi yalnızca uygulama içi, beyaz listeli yol |
| Kurulum ele geçirme | Sihirbaz `.env` / kilit dosyası varken hiçbir adımı çalıştırmaz |
| Path traversal | Segment bazlı doğrulama + `realpath()`; görünüm adları beyaz listeden |
| Kötücül yükleme | Gerçek MIME + beyaz/kara liste + rastgele ad + GD yeniden üretimi + piksel/bellek sınırı |
| Spam rölesi | Otomatik yanıt varsayılan kapalı, ziyaretçi metnini içermez, aynı adrese günde bir |
| Dosya ifşası | `app/` `config/` `storage/` `views/` `.env` `.git/` `cy` web'e kapalı |
| Bilgi sızması | Yayında yığın izi ve dosya yolu gösterilmez; varsayılan `APP_DEBUG=false` |
| Yanlış rota tanımı | Bilinmeyen ara katman adı hata fırlatır (rota sessizce herkese açılmaz) |
| Herkese açık demo | Demo modunda örnek hesaplar parolayı, kullanıcıları, ayarları değiştiremez, e-posta gönderemez; anahtar `.env`'de (panelden kapatılamaz); demo kapalıyken unutulan örnek hesaplar için panel uyarır |

**Panel → Sistem Bilgisi** sayfası bunların canlı denetimini yapar ve
her sorunun nasıl çözüleceğini yazar.

---

## Canlıya çıkış

```bash
# .env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=info
APP_KEY=<64 onaltılık karakter, kuruluma özel>
# Cloudflare / yük dengeleyici / nginx ters vekil arkasındaysa:
TRUSTED_PROXIES=<vekil adresleri>

php cy config:cache && php cy migrate
```

- [ ] `kurulum/` klasörü silindi
- [ ] Demo sitesi değilse `APP_DEMO=false` ve örnek hesaplar silindi
      (`DELETE FROM kullanicilar WHERE eposta LIKE '%.demo@ornek.com';`)
- [ ] Panelin üstünde kırmızı/turuncu uyarı yok (debug, bekleyen migration, APP_KEY, örnek hesaplar)
- [ ] HTTPS aktif
- [ ] `storage/` ve `upload/` yazılabilir
- [ ] `php cy mail:test` başarılı
- [ ] Cron kuruldu
- [ ] Sistem Bilgisi sayfasındaki tüm denetimler yeşil

### Nginx kullanıyorsanız

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# Nokta ile başlayan her şey (.env, .git/, .github/ …) — .well-known hariç
location ~ /\.(?!well-known) { deny all; }

location ~ ^/(app|config|storage|views|database|modules|routes|tests|docs|kurulum)/ { deny all; }
location ~ ^/cy$   { deny all; }

location ~* ^/upload/.*\.(php\d?|phtml|phar|pl|py|cgi|sh)$ { deny all; }
location ^~ /upload/ {
    add_header X-Content-Type-Options nosniff;
    add_header Content-Security-Policy "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";
}

# API'nin Bearer anahtarı PHP-FPM'e iletilsin
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

> Kurulum bittiyse `kurulum` klasörünü silin; Nginx'te yukarıdaki kural
> zaten kapatır, sihirbazı açmak için kuralı geçici olarak kaldırmanız gerekir.

---

## Yeni projeye başlarken

```bash
git clone https://github.com/CilginYazilim/cy-php-starter yeni-proje
cd yeni-proje && rm -rf .git

# kurulum/ adresini aç, sihirbazı tamamla
# ("Örnek verileri de yükle" ve "Demo modu" kutularını işaretlemeyin,
#  "Örnek Modül açık kurulsun" kutusunu boşaltın)

rm -rf modules/Ornek app/Jobs/OrnekIs.php

php cy make:module KendiModulun
```

Ardından Panel → **Site Ayarları**'ndan site adını, logoyu ve tema
rengini kendinize göre ayarlayın; arayüz anında yeni renginizi alır.

---

## Sürüm

Güncel sürüm **1.5.0** (7 Ekim 2026). Sürüm numarası **kodda, tek yerde**
durur:

```php
// config/app.php
'version' => '1.5.0',
```

`Panel → Sistem Bilgisi` sayfası bu değeri okur. Şablonu güncellediğinizde
numara kendiliğinden gelir; veritabanında ayrıca tutulmaz.

### Sürüm geçmişi

| Sürüm | Tarih | Öne çıkanlar |
|---|---|---|
| [1.5.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.5.0) | 2026-10-07 | Demo modu (tek tıkla giriş, örnek hesaplara kilit); panelden modül aç/kapa; modüllerin rollere yetki dağıtması ve onay akışlı RBAC örnek modülü; sade panel tasarımı |
| [1.4.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.4.0) | 2026-10-06 | İkinci güvenlik incelemesi: boşluklu/Türkçe klasörde ve Redis oturumunda giriş, kuyrukta kilitlenme, kayıtta e-posta doğrulaması, parola değişince düşen API anahtarları, hizmet engellemeye dayanıklı IP kilidi |
| [1.3.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.3.0) | 2026-10-06 | Kapsamlı güvenlik incelemesi: kurulum sihirbazı ele geçirme, açık yönlendirme, kaba kuvvet kilidi, oturum sürümü; REST API anahtarları, birim ve duman testleri, GitHub Actions |
| [1.2.1](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.2.1) | 2026-09-05 | Takma ad (vitrin) adresinden servis edilen kurulumda 404 ve yönlendirme döngüsü düzeltildi |
| [1.2.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.2.0) | 2026-09-04 | 12 ekran görüntüsü ve Canlı Demo bölümü |
| [1.1.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.1.0) | 2026-08-30 | Mobil düzen baştan elden geçirildi, sürüm numarası tek kaynağa indi |
| [1.0.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.0.0) | 2026-08-18 | İlk kararlı sürüm |

Her sürümün ayrıntısı:
[CHANGELOG.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/CHANGELOG.md).

### Güncelleme (1.4.0 → 1.5.0)

Çekirdekte migration yok; kodu çekmeniz yeterli. Demo modu varsayılan
olarak kapalıdır. Var olan bir kurulumda Örnek Modül'ü açmanın en kolay
yolu panel: **Sistem Bilgisi → Modüller** bölümündeki düğme modülü açar
ve tablolarını kurar. Komut satırından açıp deneme kayıtlarıyla
doldurmak için:

```bash
php cy module --enable=Ornek
php cy migrate                          # modülün 2 migration'ı
php cy db:seed --class=OrnekIcerik      # 12 rastgele kayıt
```

Ornek modülü zaten açıksa yalnızca `php cy migrate` yeterlidir (SSH
yoksa Panel → Sistem Bilgisi'ndeki düğme).

### Güncelleme (1.2.x ya da 1.3.x → 1.4.0)

1. **Yedek alın** (veritabanı + `.env`).
2. Kodu çekin.
3. `php cy migrate` çalıştırın — SSH yoksa **Panel → Sistem Bilgisi →
   Migration'ları Çalıştır**. Migration'lar çalıştırılmadan da giriş
   yapılabilir; panel her sayfada uyarır. Eski kurulum migration'ları bu
   adımda **kendiliğinden temel partiye (0) taşınır**; elle SQL gerekmez.
4. `.env` dosyanızı açın ve şunları kontrol edin (eski sihirbaz bu
   satırları geliştirme değerleriyle yazıyordu):
   - `APP_ENV=production` ve `APP_DEBUG=false` olmalı (satırları SİLMEYİN,
     değerlerini değiştirin).
   - `APP_KEY` boşsa doldurun: `php -r "echo bin2hex(random_bytes(32));"`
   - `SESSION_NAME` boşsa doldurun (örn. `CYS_` + 10 rastgele harf/rakam);
     aynı alan adında başka kurulum varsa her biri farklı olmalı.
   - Site bir vekilin arkasındaysa `TRUSTED_PROXIES` yazın.
   - Eksik anahtarlar için `.env.example`'a bakın.
5. `php cy config:cache` kullanıyorsanız yeniden çalıştırın.

Kurulum kimliği artık yalnızca `APP_KEY`'den türetildiği için bütün
kullanıcılar **bir kez** yeniden giriş yapar. 1.1.0'dan güncelliyorsanız
da aynı adımlar yeterlidir.

---

<div align="center">

**Ayrıntılı mimari, tasarım kararları ve genişletme rehberi:
[Sistem Kılavuzu](https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md)**

**Örnek kodlar: [Çılgın Yazılım Kod Kütüphanesi](https://cilginyazilim.com/kutuphane)**

[MIT Lisansı](https://github.com/CilginYazilim/cy-php-starter/blob/main/LICENSE) · **Çılgın Yazılım** · [cilginyazilim.com](https://cilginyazilim.com)

</div>
