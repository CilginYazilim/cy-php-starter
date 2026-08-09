<div align="center">

# Çılgın Yazılım – PHP Başlangıç Şablonu

### Her yeni örnek projeye buradan başlayın

**Kurulum sihirbazı + OOP mimari + rol tabanlı panel + ön yüz + tasarım kalıbı hazır. Siz sadece işin özüne odaklanın.**

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.2-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](LICENSE)

[cilginyazilim.com](https://cilginyazilim.com)

</div>

---

## Bu şablon nedir?

Her yeni PHP projesine "boş bir klasörden" başlamak yerine, gerçek bir
uygulamada olması gereken iskeletle başlamanız için hazırlandı: katmanlı
OOP mimari, kurulum sihirbazı, rol tabanlı yönetim paneli, tipli ayar
sistemi ve baştan sona güvenlik.

**Composer yok, framework yok, CDN yok.** İndirin, `kurulum/` adresini
açın, birkaç adımda çalışan bir site + panel elde edin.

> Bu şablonun önceki sürümü prosedürel (fonksiyon tabanlı) bir yapıya
> sahipti. Bu sürüm, [PHP PDO MySQL Ajax CRUD](https://github.com/CilginYazilim/PHP-PDO-MySQL-Ajax-CRUD-DataTables-Bootstrap-5-Modals)
> örneğiyle **aynı mimari kalıbı** kullanacak şekilde OOP'a taşındı:
> `app/Core` çatı sınıfları, `Repository` deseni, tek giriş noktası,
> rota tablosu ve ara katmanlar (middleware).

---

## Öne çıkan özellikler

### Kurulum
- 5 adımlı **kurulum sihirbazı** (`kurulum/`) — veritabanını oluşturur, şemayı kurar, site ayarlarını ve yönetici hesabını yazar, `.env` dosyasını üretir
- Bağlantı bilgileri 2. adımda **hemen test edilir**
- İş bitince tek tıkla **kendi klasörünü siler**

### Mimari
- Katmanlı OOP yapı — Core / Models / Repositories / Controllers / Views
- PSR-4 mantığında otomatik yükleyici (Composer'sız)
- Tek giriş noktası (front controller) + rota tablosu
- Ability tabanlı yetkilendirme: `Auth::can('users.delete')`

### Ön yüz (site)
- Ana sayfa, Hakkımızda, İletişim, Giriş, Kayıt — oturum durumuna göre değişir
- **Tipli ayar sistemi**: `ayarlar` tablosuna satır eklemek, panelde otomatik form alanı üretir
- İletişim formu → `mesajlar` tablosu (SMTP gerekmez), bal küpü + hız sınırı korumalı

### Yönetim paneli
- Daraltılabilir sol menü, kontrol paneli (özet kartlar + grafik + son işlemler)
- Kullanıcı yönetimi: sunucu taraflı DataTables, rol/durum filtresi, modal CRUD
- Mesaj yönetimi: okundu/okunmadı, toplu işlem, arama
- Site Ayarları: form otomatik üretilir — yeni ayar eklemek bir SQL satırı kadar kolay
- Sistem sayfası: canlı güvenlik denetim listesi
- Açık / koyu tema — tercih çerezde saklanır

---

## Kurulum

### Gereksinimler

| | En az | Önerilen |
|---|---|---|
| PHP | 8.1 | 8.2+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0+ |
| PHP eklentileri | `pdo_mysql`, `mbstring` | + `gd` |

### Adımlar

```bash
# 1) Şablondan yeni proje oluştur
git clone https://github.com/CilginYazilim/cy-php-starter.git yeni-projem
cd yeni-projem
rm -rf .git && git init

# 2) Çalıştır
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/` adresini açın. `.env` dosyası henüz
yoksa otomatik olarak **kurulum sihirbazına** yönlendirilirsiniz:

```
Gereksinimler → Veritabanı → Site Ayarları → Yönetici → Tamamlandı
```

Son adımdaki **"Kurulum Klasörünü Sil"** butonu, `kurulum/` klasörünü
içindeki her şeyle birlikte kaldırır; proje kökünde kuruluma ait tek
bir dosya bile kalmaz.

> **Elle kurulum tercih ederseniz:** `mysql -u root -p < kurulum/database.sql`
> ile şemayı kendiniz kurup `.env.example` dosyasını `.env` olarak
> kopyalayabilirsiniz. Yönetici hesabını da kendiniz eklemeniz gerekir
> — bkz. `kurulum/database.sql` dosyasının sonundaki not.

---

## Klasör yapısı

```
├── index.php              ← TEK giriş noktası (ön yüz + panel)
├── kurulum/                ← Kurulum sihirbazı (iş bitince silinir)
│   ├── index.php             5 adımlı, app/ klasöründen BAĞIMSIZ çalışır
│   └── database.sql          Şema: ayarlar · kullanicilar · login_attempts · mesajlar
│
├── config/config.php      ← Tüm ayarlar tek dizide
├── routes/web.php         ← Rota tablosu + yetki kuralları
│
├── app/
│   ├── Core/                 Autoloader, Env, Config, Database, Session, Csrf,
│   │                         Auth, Middleware, Router, Request, Response,
│   │                         Validator, Uploader, RateLimiter, Flash, View, Setting
│   ├── Models/                User (entity) · Role (yetki tablosu) · Message
│   ├── Repositories/          UserRepository · MessageRepository
│   ├── Http/Controllers/      Auth, Dashboard, User, Profile, Settings, System, Message
│   │   ├── Api/                UserApiController · MessageApiController (AJAX)
│   │   └── Site/                HomeController · ContactController (ön yüz)
│   └── Support/helpers.php    e(), url(), can(), icon(), setting() …
│
├── views/
│   ├── layouts/               admin · site · plain
│   ├── partials/               sidebar · topbar · site-nav · site-footer
│   ├── site/ auth/ dashboard/ users/ messages/ settings/ system/ profile/ errors/
│
├── assets/
│   ├── css/   cilginyazilim.css (tasarım kalıbı) · admin.css (panel) · site.css (ön yüz)
│   └── js/    app.js (kabuk) · users.js · messages.js · login.js · register.js · contact.js
│
└── upload/                 ← Yüklenen görseller (PHP çalıştırma kapalı)
```

### İsteğin yolculuğu

```
Tarayıcı → index.php → routes/web.php → Middleware → Controller → Repository → View/JSON
```

`installed` ara katmanı, `.env` yoksa isteği otomatik olarak
`kurulum/` adresine yönlendirir — ana uygulama veritabanı olmadan hiç
çalışmayı denemez.

---

## Roller ve yetkiler

Yetkiler `app/Models/Role.php` içinde tek bir tabloda tanımlıdır:

```php
Auth::can('users.delete')          // ✔ doğru
Auth::user()->rol === 'admin'      // ✘ kırılgan
```

| Yetki | Yönetici | Editör | Üye |
|---|:--:|:--:|:--:|
| Kontrol paneli | ✔ | ✔ | ✔ |
| Özet istatistikler | ✔ | ✔ | — |
| Kullanıcı yönetimi | ✔ | — | — |
| Mesaj yönetimi | ✔ | ✔ | — |
| Site ayarları | ✔ | — | — |
| Sistem sayfası | ✔ | — | — |
| Kendi profili | ✔ | ✔ | ✔ |

Ek iş kuralları: kimse kendi rolünü/durumunu değiştiremez, kendini
silemez; sistemdeki **son aktif yönetici** silinemez/pasifleştirilemez;
kayıt formundan gelen `rol` alanı yok sayılır — yeni hesap her zaman
`uye` olur.

---

## Ayarları kullanmak

```php
use App\Core\Setting;

echo Setting::get('site_adi');                    // oku
if (Setting::bool('sistem_bakim_modu')) { … }      // aç/kapa

Setting::saveMany($db, ['site_adi' => 'Yeni Ad']); // yaz (transaction'lı)
```

**Yeni bir ayar eklemek** için `kurulum/database.sql` içindeki
`ayarlar` tablosuna (veya kurulu bir sisteme doğrudan) satır eklemeniz
yeterlidir — yönetim panelindeki form **otomatik** üretilir:

```sql
INSERT INTO ayarlar (anahtar, deger, grup, tip, etiket, sira)
VALUES ('site_favicon', '', 'genel', 'metin', 'Favicon', 70);
```

Desteklenen `tip` değerleri: `metin`, `uzun_metin`, `sayi`, `eposta`, `url`, `secim`, `onay`, `renk`.

---

## Yeni sayfa eklemek

```php
// 1. app/Http/Controllers/Site/ServicesController.php
final class ServicesController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('site/services', ['title' => 'Hizmetler'], 'layouts/site');
    }
}

// 2. routes/web.php
$router->get('hizmetler', ServicesController::class, 'index', ['installed']);

// 3. views/site/services.php — plain PHP/HTML
```

Panel sayfaları için `layouts/admin` düzenini ve ilgili `can:...` ara
katmanını kullanın; menüde görünmesi için `views/partials/sidebar.php`
içindeki `$menu` dizisine bir satır eklemeniz yeterlidir.

---

## Güvenlik önlemleri

| Tehdit | Önlem |
|---|---|
| SQL Injection | Prepared statement; sıralama sütunu beyaz listeden |
| XSS | `e()` ile kaçışlama + Content-Security-Policy, satır içi script yok |
| CSRF | Oturum bazlı token, `hash_equals` sabit süreli karşılaştırma |
| Kaba kuvvet | 5 hatalı denemede 15 dakika kilit (veritabanı tabanlı sayaç) |
| Kullanıcı sayımı | Giriş hatalarında tek/aynı mesaj + sahte `password_verify` |
| Session fixation/hijacking | Girişte kimlik yenileme, `httponly`+`samesite`+`secure` çerez |
| Yetki yükseltme | Rol/durum alanları yetki olmadan okunmaz; güncellemede beyaz liste |
| Kötü amaçlı dosya | İçerik doğrulaması, sunucu tarafından üretilen ad, görsel yeniden üretilir |
| Parola saklama | `password_hash()` / bcrypt, girişte otomatik `needs_rehash` |
| Spam (iletişim formu) | Bal küpü (honeypot) + oturum başına hız sınırı |
| Kod dosyasına erişim | `.htaccess` ile `app/`, `config/`, `views/`, `routes/` erişimi engelli |

**Sistem** sayfası (yalnızca yönetici) bu önlemlerin kurulumunuzda
aktif olup olmadığını canlı olarak denetler.

---

## Canlıya alma kontrol listesi

- [ ] **`kurulum/` klasörünü silin** (sihirbazın son adımındaki buton bunu yapar)
- [ ] `.env` içinde **`APP_DEBUG=false`**
- [ ] `root` yerine sınırlı yetkili bir veritabanı kullanıcısı
- [ ] HTTPS sertifikası kurun (oturum çerezi otomatik `secure` olur)
- [ ] `.env`, `app/`, `config/`, `views/`, `routes/` tarayıcıdan erişilemez olsun
- [ ] `upload/` klasöründe PHP çalıştırma kapalı olsun
- [ ] **Sistem** sayfasındaki denetim listesini kontrol edin

### Nginx kullanıyorsanız

```nginx
location ~ ^/(app|config|views|routes)/ { deny all; return 404; }
location ~ /\.                          { deny all; return 404; }
location ~ \.(sql|md|log|ini|bak)$      { deny all; return 404; }
location ^~ /upload/ { location ~ \.php$ { deny all; } }
```

---

## Örnek Projeler

Bu şablonla üretilen örnekler:

- [PHP PDO MySQL Ajax CRUD](https://github.com/CilginYazilim/PHP-PDO-MySQL-Ajax-CRUD-DataTables-Bootstrap-5-Modals) — aynı mimari kalıbın CRUD odaklı örneği

---

## Lisans

[MIT](LICENSE) — ticari kullanım dahil serbesttir.

<div align="center">

**[cilginyazilim.com](https://cilginyazilim.com)**

</div>
