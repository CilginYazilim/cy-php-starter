# CY PHP Starter — Sistem Kılavuzu

> Bu dosya sistemin **tamamını** anlatır: mimari kararlar, her katmanın
> ne işe yaradığı, neden öyle yazıldığı ve nasıl genişletileceği.
> Hızlı başlangıç için [README.md](README.md) yeterlidir.

---

## İçindekiler

1. [Felsefe: Core ne bilir, ne bilmez?](#1-felsefe)
2. [Bir isteğin yolculuğu](#2-bir-isteğin-yolculuğu)
3. [Klasör yapısı](#3-klasör-yapısı)
4. [Önyükleme (bootstrap)](#4-önyükleme)
5. [Yapılandırma ve ortam](#5-yapılandırma-ve-ortam)
6. [Adresler ve yönlendirme](#6-adresler-ve-yönlendirme)
7. [Kimlik ve yetkilendirme](#7-kimlik-ve-yetkilendirme)
8. [Hata yönetimi ve günlükler](#8-hata-yönetimi-ve-günlükler)
9. [Veritabanı, migration, seeder](#9-veritabanı-migration-seeder)
10. [Depolama ve dosya yükleme](#10-depolama-ve-dosya-yükleme)
11. [Önbellek](#11-önbellek)
12. [Olaylar](#12-olaylar)
13. [Kuyruk ve zamanlanmış görevler](#13-kuyruk-ve-zamanlanmış-görevler)
14. [E-posta](#14-e-posta)
15. [REST API](#15-rest-api)
16. [Modül sistemi](#16-modül-sistemi)
17. [PWA](#17-pwa)
18. [Konsol (`php cy`)](#18-konsol-php-cy)
19. [Arayüz ve tasarım](#19-arayüz-ve-tasarım)
20. [Güvenlik](#20-güvenlik)
21. [Yeni projeye başlarken](#21-yeni-projeye-başlarken)
22. [Canlıya çıkış listesi](#22-canlıya-çıkış-listesi)

---

## 1. Felsefe

Bu proje belirli bir iş uygulaması **değildir**. Amaç, her yeni PHP
projesinde baştan yazılan teknik altyapıyı hazır sunmaktır.

**Tek cümlelik kural:**

> Core, uygulamanın **NE İŞ YAPTIĞINI** bilmez. Yalnızca **NASIL
> ÇALIŞTIĞINI** sağlar.

| Core'a ait | Core'a ait değil |
|---|---|
| Kimlik, yetki, oturum | Personel, puantaj, maaş |
| Router, Request, Response | Sipariş, stok, fatura |
| Veritabanı, migration | Blog yazısı, kategori |
| E-posta, kuyruk, olay | "Kullanıcı kaydolunca personel kartı aç" |

Son satır ayrımın kalbidir:

```php
// YANLIŞ — çekirdek ERP'yi biliyor
UserService::register($data);
ErpPersonel::olustur($user);          // ← çekirdeğe sızmış iş mantığı

// DOĞRU — çekirdek duyurur, modül dinler
Events::dispatch(new UserRegistered($user));

// modules/Erp/events.php
Events::listen(UserRegistered::class, PersonelKartiAc::class);
```

**Sıfır bağımlılık.** Composer yok, `vendor/` yok. İndir, `kurulum/`
adresini aç, çalıştır. Bu, starter'ın en güçlü yanıdır ve bilerek
korunmuştur.

### Tekrarlanan tasarım kararları

Bu kararlar tüm kod tabanında tutarlıdır; yeni kod yazarken de
uyulmalıdır:

- **Arayüz ancak ikinci uygulama varken açılır.** `Cache` arayüzü var
  (3 sürücü), `Storage` yok (1 sürücü). Tek uygulaması olan arayüz ölü
  soyutlamadır.
- **Altyapı hataları sessizdir, iş hataları gürültülüdür.** Logger disk
  dolunca istisna atmaz; kullanıcı kaydı başarısız olunca atar.
- **Geliştirmede gürültülü, yayında dayanıklı.** Aynı hata `APP_DEBUG`
  açıkken ekrana düşer, kapalıyken loglanıp yutulur.
- **Kullanıcı girdisi asla dosya adına/SQL'e/HTML'e doğrudan girmez.**

---

## 2. Bir isteğin yolculuğu

```
Tarayıcı
   │
   ▼
.htaccess ──► mod_rewrite: /panel/ayarlar → index.php
   │
   ▼
index.php ──► app/bootstrap.php
   │              ├── Autoloader (PSR-4, Composer'sız)
   │              ├── Env::load(.env)
   │              ├── Config::load(config/)
   │              ├── ErrorHandler::register()   ← buradan sonrası güvenli
   │              ├── helpers.php
   │              └── routes/events.php
   │
   ├── Session::start()          oturum sertleştirme
   ├── Response::securityHeaders()  CSP, HSTS, nosniff…
   ├── Setting::load($db)        panelden yönetilen ayarlar
   ├── Modules::boot()           açık modüllerin sınıf + olayları
   │
   ▼
routes/web.php ──► Router
   │                 └── Modules::loadRoutes()   modül rotaları
   ▼
Middleware  installed → auth → csrf → can:…
   │
   ▼
Controller ──► Repository ──► PDO
   │
   ▼
View (+ layout)  ya da  Response::json()
   │
   ▼
Tarayıcı
```

**Hata olursa** akış nerede olursa olsun `ErrorHandler`'a düşer:
loglanır, ortama göre HTML sayfası / JSON / terminal metni üretilir.

---

## 3. Klasör yapısı

```
cy-php-starter/
├── index.php               Web giriş noktası (ön denetleyici)
├── cy                      Konsol giriş noktası (php cy …)
├── sw.js                   Servis çalışanı (PWA) — kökte olmak ZORUNDA
├── .htaccess               Temiz adres + güvenlik + önbellek + sıkıştırma
├── .env                    Gizli ayarlar (asla depoya gitmez)
│
├── app/
│   ├── bootstrap.php       Web + CLI ortak önyükleme
│   ├── Core/               ÇEKİRDEK — iş mantığı içermez
│   │   ├── Api/            ApiResponse · ApiToken · ApiGuard
│   │   ├── Cache/          Cache · CacheStore + File/Database/Null
│   │   ├── Console/        Kernel · Command · Input · Output · Commands · stubs
│   │   ├── Database/       Migrator · Migration · Seeder
│   │   ├── Events/         Events · Dispatcher · Event
│   │   ├── Exceptions/     HttpException
│   │   ├── Log/            Logger
│   │   ├── Mail/           Mailer · Mailable · Mime · MailUi · Notifier · Transport'lar
│   │   ├── Modules/        Modules · Module
│   │   ├── Queue/          Queue · Job
│   │   ├── Schedule/       Schedule · Task
│   │   ├── Storage/        Storage · Disk · StoredFile
│   │   └── (Auth, Config, Csrf, Database, Env, ErrorHandler, Flash,
│   │        Middleware, RateLimiter, Request, Response, Router,
│   │        Session, Setting, Theme, Uploader, Url, Validator, View)
│   │
│   ├── Events/             Uygulamanın kendi olayları (UserRegistered…)
│   ├── Listeners/          Olay dinleyicileri
│   ├── Jobs/               Kuyruk işleri
│   ├── Models/             Entity'ler (ORM DEĞİL) + Role
│   ├── Repositories/       SQL yalnızca burada
│   ├── Http/Controllers/   Panel · Api · Site
│   └── Support/helpers.php e() url() asset() can() icon() config() …
│
├── config/                 Her dosya bir üst anahtar
│   └── app db session log cache queue api storage upload security
│       validation events
│
├── routes/
│   ├── web.php             Adres → denetleyici
│   ├── events.php          Olay → dinleyici
│   └── schedule.php        Zamanlanmış görevler
│
├── database/
│   ├── migrations/         Şema değişiklikleri (kurulum bunları da çalıştırır)
│   └── seeders/            Örnek veri (php cy db:seed)
│
├── modules/                Eklenebilir modüller
│   └── Ornek/              Çalışan örnek (silinebilir)
│
├── views/                  layouts · partials · emails · errors · sayfalar
├── assets/                 css · js · images (CDN YOK)
├── storage/                logs · cache · files · mail   (web'e kapalı)
├── upload/                 Yüklenen görseller (PHP çalıştırma kapalı)
└── kurulum/                Kurulum sihirbazı (kurulum sonrası SİLİN)
    ├── index.php           Sihirbaz
    ├── database.sql        Temel şema — HER kurulumda çalışır
    └── demo.sql            Örnek veri — YALNIZCA kutu işaretlenirse
```

---

## 4. Önyükleme

`app/bootstrap.php` web ve CLI için **ortaktır**. Sırasıyla:

1. **Autoloader** — `App\Core\Database` → `app/Core/Database.php`.
   `realpath()` ile dosyanın gerçekten kendi klasöründe olduğu
   doğrulanır (path traversal koruması).
2. **Env** — `.env` okunur.
3. **Config** — `config/` klasörü (ya da önbellek) yüklenir.
4. **Saat dilimi**.
5. **ErrorHandler** — bu satırdan sonra hiçbir hata sessizce kaybolmaz.
6. **helpers.php**.
7. **routes/events.php** — dinleyiciler kaydedilir.
8. CLI ise **routes/schedule.php**.

`index.php` bunun üstüne web katmanını ekler: oturum, güvenlik
başlıkları, ayarlar, modüller, yönlendirme.

---

## 5. Yapılandırma ve ortam

### Ayarlar ekranı bölüm bölümdür

```
panel/ayarlar            gruplara genel bakış (durum özetleriyle)
panel/ayarlar/genel      yalnızca "genel" grubu
panel/ayarlar/eposta     yalnızca "eposta" grubu
```

Her sayfa **yalnızca kendi grubunu kaydeder**. E-posta sayfasını
kaydetmek SEO ayarlarına dokunmaz.

> **"dahili" grubu ekranda görünmez.** Sistemin kendi tuttuğu
> değerler (açık modül listesi gibi) oraya yazılır ve
> `duzenlenebilir=0` işaretlenir. Bu kural bir hatadan doğdu:
> `aktif_moduller` bir zamanlar "genel" grubunda düzenlenebilir bir
> metin alanıydı ve Genel ayarlarını kaydetmek **tüm modülleri
> kapatıyordu**. `Setting::grouped()` artık yalnızca
> `groupLabels()` içinde etiketi olan grupları döndürür.

### İki ayar kaynağı vardır — karıştırmayın

| | `config()` | `setting()` |
|---|---|---|
| Nerede | `config/*.php` dosyaları | `ayarlar` veritabanı tablosu |
| Kim değiştirir | Geliştirici (dağıtımla) | Yönetici (panelden) |
| Örnek | `config('db.host')` | `setting('site_adi')` |

### `.env` ayrıştırıcısı

```bash
KEY=deger
export KEY=deger              # kabuktan kopyala-yapıştır çalışır
KEY="tırnaklı  deger"         # \n ve \" kaçışları çözülür
KEY='ham ${DEGER}'            # tek tırnakta hiçbir şey çözülmez
KEY=deger   # satır sonu notu  (yalnızca TIRNAKSIZ değerlerde)
SIFRE=Parola#123              # boşluk yoksa "#" yorum SAYILMAZ
URL=${HOST}/yol               # önceki değişkene atıf
```

Arama sırası: `.env` → `$_ENV` → `$_SERVER` → `getenv()` → varsayılan.
Docker/Heroku gibi ortamlarda `.env` olmadan da çalışır.

> `putenv()` **kullanılmaz**: putenv ile yazılan değerler alt süreçlere
> miras kalır ve veritabanı parolanız çağırdığınız her komuta sızar.

### Ortam ayrımı

```bash
APP_ENV=production
APP_DEBUG=false
```

İkisi çelişirse (production + debug) her istekte **güvenlik kanalına
uyarı** düşer. Sessizce düzeltilmez — geliştirici bilerek açmış
olabilir — ama iz bırakmadan da geçilmez.

### Yapılandırma önbelleği

```bash
php cy config:cache    # config/ → storage/cache/config.php
php cy config:clear
```

İki koruma: **`APP_DEBUG=true` iken asla kullanılmaz** (dosyayı
değiştirip "neden olmuyor" diye aramayasınız) ve **proje kökü parmak
izi** gömülür (klasör taşınırsa bayat mutlak yollar kullanılmaz).

### `config/config.local.php`

Varsa `config/`'ten sonra okunur ve **derin birleştirilir**. `.gitignore`
içindedir: kendi makinenizde bir ayarı değiştirmek için takım
arkadaşlarınızın dosyalarına dokunmazsınız.

---

## 6. Adresler ve yönlendirme

### Temiz adresler

Varsayılan **açıktır**:

```
/cy-php-starter/panel/ayarlar          ✔
/cy-php-starter/index.php?r=panel/…    (APP_PRETTY_URLS=false)
```

`mod_rewrite` yoksa `.env` içinde `APP_PRETTY_URLS=false` yapmak
yeterlidir; kodda hiçbir şey değişmez.

**Tüm adresler köke görelidir** (`/cy-php-starter/...`). Bu şart:
`/panel/ayarlar` sayfasındayken göreli `assets/css/site.css` adresi
tarayıcı tarafından `/panel/assets/css/site.css` olarak çözülür ve
sayfa stilsiz kalır. `App\Core\Url` taban yolunu `SCRIPT_NAME`'den
bulur; uygulama sunucu kökünde de alt klasörde de çalışır.

### Rota tanımlama

```php
$router->get('panel/urunler', UrunController::class, 'index',
    ['installed', 'auth', 'can:urun.view']);

// Parametreli — değer metoda argüman olarak geçer
$router->get('api/v1/urunler/{id}', UrunApi::class, 'show', ['api']);
// public function show(Request $request, string $id): void

// Grup: ortak önek + ortak ara katman
$router->group('api/v1', ['api'], function (Router $r) {
    $r->get('urunler', UrunApi::class, 'index');
    $r->post('urunler', UrunApi::class, 'store');
});
```

Yöntemler: `get` `post` `put` `patch` `delete` `any`.
HTML formları yalnızca GET/POST gönderebildiği için `_method` alanıyla
**yöntem sahteleme** desteklenir.

**Performans:** parametresiz rotalar dizi anahtarında O(1) bulunur;
yalnızca parametreli olanlar düzenli ifadeyle denenir.

**İlk kayıt kazanır:** modül rotaları çekirdekten sonra yüklenir, bu
yüzden bir modül `giris` gibi temel bir adresi ele geçiremez.

### Ara katmanlar

| Kural | İşi |
|---|---|
| `installed` | `.env` yoksa kurulum sihirbazına yönlendir |
| `guest` | Yalnızca giriş yapmamış ziyaretçi |
| `auth` | Giriş zorunlu (AJAX'ta 401 döner) |
| `csrf` | Sahte istek koruması — veri değiştiren her POST |
| `can:users.delete` | Yetki zorunlu (`\|` ile VEYA) |
| `role:admin` | Rol zorunlu |
| `api` | Bearer anahtarı + hız sınırı |
| `api.can:urun.view` | API yetkisi |
| `api.guest` | Anahtarsız ama hız sınırlı |

---

## 7. Kimlik ve yetkilendirme

**Oturumda yalnızca kullanıcı numarası tutulur.** Rol ve durum her
istekte veritabanından taze okunur; aksi halde pasife alınan bir
kullanıcı oturumu açık kaldığı sürece yetkilerini kullanmaya devam
ederdi.

### Giriş güvenliği

- **Kaba kuvvet koruması** — 5 hatalı denemeden sonra 15 dakika kilit
  (`login_attempts` tablosu, kimlik+IP başına).
- **Zamanlama saldırısı önlemi** — kullanıcı bulunamasa bile bir kez
  `password_verify()` çalıştırılır, böylece yanıt süresinden "bu
  e-posta kayıtlı mı" anlaşılamaz.
- **Oturum sabitleme önlemi** — girişte `session_regenerate_id(true)`.
- **Otomatik rehash** — `password_needs_rehash()` ile parola özeti
  güncel algoritmaya taşınır.
- Oturum: `httponly` + `samesite=Lax` + HTTPS'te `secure`, tarayıcı
  parmak izi kontrolü, 30 dk hareketsizlik zaman aşımı, 15 dk'da bir
  kimlik yenileme.

### Yetkiler

Kodda **rol adı değil yetki adı** kullanılır:

```php
Auth::can('users.delete')      // ✔ doğru
Auth::user()->rol === 'admin'  // ✘ kırılgan
```

Roller ve yetkileri `app/Models/Role.php` içindedir.

> **Yönetici her şeyi yapabilir** — liste kontrol edilmez. Sebebi
> modüllerdir: `modules/Stok` kendi `stok.view` yetkisini tanımlar ama
> çekirdeğin listesini değiştiremez. Yönetici için beyaz liste şart
> koşsaydık, kurulan her modül için `Role.php`'yi elle düzenlemek
> gerekirdi. Diğer roller için liste bağlayıcıdır.

---

## 8. Hata yönetimi ve günlükler

### Üç hata kaynağı da yakalanır

| Kaynak | Nasıl |
|---|---|
| Yakalanmamış istisna | `set_exception_handler` |
| PHP uyarısı / notice | `set_error_handler` |
| **Ölümcül hata** | `register_shutdown_function` |

Üçüncüsü kritiktir: "Allowed memory size exhausted" ya da bir yazım
hatası istisna **fırlatmaz**; onsuz kullanıcı beyaz sayfa görür ve
log'da hiçbir iz kalmaz.

### Ortama göre davranış

|  | Geliştirme | Yayın |
|---|---|---|
| Uyarı/notice | İstisnaya çevrilir (fark edilsin) | Loglanır, sayfa çalışmaya devam eder |
| 500 ekranı | Kaynak kodu + yığın izi | Nötr mesaj |
| JSON yanıt | `debug` bloğu eklenir | Yalnızca mesaj |
| CLI | Yığın izi basılır | Tek satır |

### HttpException

```php
throw HttpException::notFound($path);
throw HttpException::forbidden(ability: 'users.delete');
throw HttpException::pageExpired();      // 419 CSRF
throw HttpException::tooManyRequests(60);
```

Karar tek yerde toplanır: JSON mu HTML mi, hangi görünüm, hangi log
kanalı — hepsini `ErrorHandler` verir.

### Günlük kanalları

```
storage/logs/app-2026-08-10.log        genel + 404'ler (INFO)
storage/logs/error-2026-08-10.log      istisnalar, ölümcül hatalar
storage/logs/security-2026-08-10.log   401/403/419/429, başarısız giriş, CSRF
storage/logs/auth-2026-08-10.log       giriş / çıkış
storage/logs/mail-2026-08-10.log       gönderilemeyen mektuplar
storage/logs/queue-2026-08-10.log      kuyruk işleri
```

Satır biçimi tek başına anlamlıdır:

```
[2026-08-10 05:58:47] WARNING  419: Güvenlik doğrulaması başarısız… | {"yontem":"POST"} | ip=127.0.0.1 yol=giris uid=5
```

**Parola maskeleme:** `sifre`, `parola`, `token`, `secret`, `api_key`
gibi anahtarlar `***` olarak yazılır — iç içe dizilerde de.

**Logger asla istisna fırlatmaz.** Disk dolduğu için site çökmemelidir.

```bash
php cy log:purge --list
php cy log:purge --days=7
```

---

## 9. Veritabanı, migration, seeder

### İlk kurulum vs. migration

- **İlk kurulum** `kurulum/database.sql` ile yapılır — tek adım, hızlı.
  Temel tablolar buradadır: `ayarlar`, `kullanicilar`, `mesajlar`,
  `mail_kayitlari`, `login_attempts`.
- **Migration'lar ondan sonraki** değişiklikler içindir. Şablonla
  birlikte gelen üç tanesi `onbellek`, `isler` ve `api_anahtarlari`
  tablolarını kurar.

İkisi çakışmaz: `migrasyonlar` tablosu yalnızca migrator'ın
çalıştırdıklarını bilir.

**Sihirbaz migration'ları da çalıştırır.** Kurulumun son adımı `.env`
dosyasını yazdıktan sonra uygulamanın kendi `Migrator`'ını çağırır
(bkz. `kurulum/index.php` → `run_migrations`). Yani tarayıcıdan yapılan
bir kurulum **eksiksizdir**; komut satırı olmayan bir paylaşımlı
hostingde de önbelleğin veritabanı sürücüsü, iş kuyruğu ve API
anahtarları hazır gelir.

> **Neden bu tabloların SQL'i `database.sql` içine kopyalanmadı?**
> Bir tablo tek bir yerde tanımlanmalıdır. İki kopya tutulsaydı biri
> güncellenip diğeri unutulduğunda, taze kurulumla mevcut kurulum
> sessizce farklı şemalara sahip olurdu. Sihirbaz kopya tutmak yerine
> migrator'ı çağırır; kayıt tablosu da doğru dolar, sonradan
> çalıştırılan `php cy migrate` bunları tekrar denemez.

### Örnek veri

`kurulum/demo.sql` dört demo kullanıcı ve iki iletişim mesajı ekler.
Sihirbazın son adımındaki **"Örnek verileri de yükle"** kutusuyla
seçilir ve **varsayılan olarak kapalıdır**: gerçek bir projeye temiz
bir veritabanıyla başlarsınız, şablonu denerken de listeleri dolu
görürsünüz. Demo kullanıcıların parolası `Demo1234!`.

### Migration yazmak

```bash
php cy make:migration "urunlere aciklama ekle"
```

```php
return new class extends App\Core\Database\Migration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `urunler` ADD `aciklama` TEXT NULL");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `urunler` DROP COLUMN `aciklama`");
    }
};
```

Yardımcılar: `execute()`, `tableExists()`, `columnExists()`,
`useTransaction()`.

> **Şema kurucu (schema builder) bilerek yoktur.** Zincirleme bir API
> güzel görünür ama arkasında binlerce satırlık SQL üreteci vardır ve
> yine de her MySQL özelliğini karşılamaz. Ham SQL hem daha küçük hem
> daha dürüsttür: yazdığınız şey aynen çalışır.

> `useTransaction()` varsayılan `false`: MySQL'de DDL örtük commit
> yapar, işlem başlatmak yanıltıcı güven verir. Yalnızca veri taşıyan
> migration'larda `true` döndürün.

### Parti (batch) mantığı

Tek `php cy migrate` çağrısındaki tüm dosyalar aynı parti numarasını
alır; `migrate:rollback` üç dosyalık bir dağıtımı tek komutla geri sarar.

```bash
php cy migrate                 php cy migrate --pretend
php cy migrate:status          php cy migrate:rollback --step=3
php cy migrate:fresh --seed
```

### Seeder — iki tür tohumlama

| | Nereye |
|---|---|
| **Zorunlu veri** (roller, varsayılan ayarlar) | **Migration** — her ortamda kesinlikle bulunmalı |
| **Örnek veri** (demo kullanıcı, sahte kayıt) | **Seeder** |

Bu yüzden `db:seed` yayında `--force` ister.

### Repository deseni

SQL **yalnızca** `app/Repositories/` içinde yazılır. Her sorgu
hazırlıklıdır (prepared); sıralama sütunu **beyaz listeden** seçilir.

```php
private const SORTABLE = [0 => 'alici_eposta', 1 => 'konu', …];
```

Modeller ORM değildir: veritabanına gitmeyen, `readonly` entity'lerdir.

---

## 10. Depolama ve dosya yükleme

### İki disk

| Disk | Kök | Web erişimi | Ne için |
|---|---|---|---|
| `public` | `upload/` | ✔ `url()` verir | Avatar, logo, ürün görseli |
| `private` | `storage/files/` | ✘ kapalı | Fatura, sözleşme, dışa aktarım |

> Şüphedeyseniz `private` seçin. Bir dosyayı sonradan herkese açmak
> kolaydır; yanlışlıkla açılmış bir belgeyi geri almak imkânsızdır.

```php
Storage::disk('private')->put('fatura/2026-03.pdf', $icerik);
Storage::disk('public')->url($yol);
Storage::disk('private')->move($eski, $yeni);
```

### Yol güvenliği

`Disk::relative()` yolu **parçalarına ayırıp her parçayı tek tek**
denetler. Tek bir `str_replace('..','')` yetmez — `....//` onu atlatır.
Reddedilenler:

```
../../../etc/passwd    ..\..\windows\win.ini    /etc/passwd
C:/Windows/win.ini     alt/../../ust.txt        a/./b.txt
dosya\0.txt            a//b.txt                 ....//gizli.txt
```

Var olan dosyalarda ayrıca `realpath()` ile kökün içinde kalındığı
doğrulanır (sembolik bağlantı hilelerine karşı).

### Yükleme

```php
// Görsel — GD ile yeniden üretilir
$yol = Uploader::avatar($request->file('avatar'));   // kare kırpar
$yol = Uploader::logo($request->file('logo'));       // oranı korur

// Genel dosya
$dosya = Uploader::store($request->file('belge'), [
    'disk' => 'private', 'dir' => 'fatura/2026', 'group' => 'belge',
]);
$dosya->yol;   // diskteki rastgele ad
$dosya->ad;    // kullanıcının gördüğü ad
```

**Dokuz savunma katmanı:**

1. PHP yükleme hata kodu
2. `is_uploaded_file()` — dosya gerçekten bu isteğe mi ait?
3. Boyut sınırı
4. **Gerçek MIME** (`finfo`/`getimagesize`) — uzantıya asla güvenilmez
5. MIME beyaz listesi **+** uzantı kara listesi (`php`, `phar`, `htaccess`…)
6. Rastgele dosya adı — kullanıcının verdiği ad diske yazılmaz
7. Görseller GD ile yeniden üretilir (EXIF ve gömülü kod elenir)
8. `upload/.htaccess` PHP çalıştırmayı kapatır
9. Gizli dosyalar web kökünün dışına yazılır

> **SVG dikkat:** `gorsel` grubunda SVG vardır ve içine `<script>`
> gömülebilir; GD onu yeniden üretemez. SVG kabul edecekseniz
> `private` diske yazın.

### Private dosya indirtmek

```php
$router->get('fatura/indir/{id}', FaturaController::class, 'indir',
    ['installed', 'auth', 'can:fatura.view']);

// Kaydın sahibi doğrulandıktan SONRA:
Response::download(Storage::disk('private'), $kayit->yol, $kayit->ad);
```

---

## 11. Önbellek

```php
$rapor = Cache::remember('rapor:satis:2026-03', 900, fn () => $this->agirHesaplama());

Cache::put('doviz', $kurlar, 3600);
Cache::forever('ulke-listesi', $ulkeler);
Cache::pull('tek-kullanimlik');
```

| Sürücü | Nerede | Ne zaman |
|---|---|---|
| `dosya` | `storage/cache/data/` | **Varsayılan**, tek sunucuda en hızlısı |
| `veritabani` | `onbellek` tablosu | Birden fazla web sunucusu varsa |
| `kapali` | hiçbir yerde | Hatanın önbellekten mi geldiğini test etmek için |

**Sözleşmenin kuralları** (üç sürücü de aynı testi geçer):

1. `get()` bulamazsa `null`. "null saklamak" ile "yok" ayrımı yapılmaz.
2. Süresi geçmiş kayıt yok sayılır ve okunurken silinir (tembel temizlik).
3. `$seconds = 0` → süresiz.
4. **Hiçbir metot istisna fırlatmaz.**

> `remember()` geri çağırımı `null` döndürürse sonuç saklanmaz ve her
> istekte yeniden çalışır. Pahalı bir işlem `null` dönebiliyorsa
> `false` veya `[]` döndürün.

**Anahtar seçimi:** kullanıcıya özel veriyi önbelleğe alıyorsanız
anahtara kullanıcı numarasını koyun (`sepet:kullanici-7`), yoksa ilk
kullanıcının verisi herkese gider.

---

## 12. Olaylar

```php
// Yayın (çekirdek)
Events::dispatch(new UserRegistered($user));

// Dinleme (routes/events.php)
Events::listen(UserRegistered::class, HosgeldinMailiGonder::class);
Events::listen(UserRegistered::class, fn ($e) => /* … */);
Events::listen('*', fn ($e) => Logger::debug($e->shortName()));
```

**Hazır olaylar:** `UserRegistered`, `UserLoggedIn`, `UserLoggedOut`,
`PasswordChanged`, `FileUploaded`, `ContactMessageReceived`.

### En önemli tasarım kararı

> **Bir dinleyicinin hatası, olayı yayınlayan işlemi bozmaz.**

ERP modülünün dinleyicisi patlarsa kullanıcının kaydı geri alınmaz —
o iş zaten bitti. Hata loglanır, diğer dinleyiciler çalışmaya devam
eder. Bu olmasaydı yüklediğiniz her modül, çekirdeğin en temel
akışlarını çökertme gücüne sahip olurdu.

Geliştirmede farklıdır: `config/events.php` → `strict` (varsayılan
`APP_DEBUG`) açıkken hata **fırlatılır**, kendi dinleyicinizdeki yazım
hatasını ekranda görürsünüz.

### Kurallar

- Olay adı **geçmiş zamandır**: `UserRegistered`, `SendWelcomeMail` değil.
- Olaylar **değişmezdir** (`readonly`).
- Olay **iptal edilemez** — yayınlandığında iş bitmiştir. Bu bilinçli
  bir sınırdır: aksi halde bir modül çekirdeğin davranışını sessizce
  bozabilirdi.
- Hassas veri (parola, token) olaya **konmaz**.

### Test desteği

```php
Events::fake();
// … denetleyiciyi çalıştır …
Events::dispatched(UserRegistered::class);   // true
Events::recorded(UserRegistered::class);     // [$event]
```

---

## 13. Kuyruk ve zamanlanmış görevler

### Kuyruk

```php
Queue::push(new RaporUret(3));
Queue::later(new RaporUret(3), 600);   // 10 dakika sonra
```

```php
final class RaporUret extends Job
{
    public function __construct(private int $ay) {}

    public function handle(): void { /* … */ }
    public function toPayload(): array { return ['ay' => $this->ay]; }
    public static function fromPayload(array $v): static { return new static((int) $v['ay']); }
    public function failed(Throwable $e): void { /* … */ }
}
```

> **Neden `serialize()` değil `toPayload()`?** PHP nesnesini serialize
> etmek cazip görünür ama sınıfı sonradan değiştirdiğinizde kuyruktaki
> eski kayıtlar açılamaz hale gelir. Açık bir dizi sözleşmesi hem
> okunabilir hem sürüm dostudur.

> **İşler tekrar çalışabilir olmalıdır.** Yarıda kalan iş yeniden
> denenir; iki kez çalıştığında iki fatura kesmemelidir.

**Atomik ayırma:** iki işçi aynı işi almasın diye önce `UPDATE` ile
kilitlenir; `rowCount()` 1 dönen işçi işi kapmıştır. Çöken bir işçinin
elinde kalan işler `queue.timeout` sonunda serbest bırakılır.

**Yeniden deneme:** katlanarak artan bekleme (60, 120, 240…),
`tries()` kadar. Tükenince `durum='basarisiz'` ve `failed()` çağrılır.

```bash
php cy queue:work --max=30
php cy queue:status --retry
```

### Zamanlayıcı

Sunucuya **tek bir cron satırı** yazılır:

```cron
* * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
```

Gerisi `routes/schedule.php` içinde PHP olarak tanımlanır:

```php
Schedule::call('gunluk-rapor', fn () => Queue::push(new GunlukRapor()))
        ->daily('08:00')->describe('Yöneticilere günlük özet');
```

Sıklık: `everyMinute()` `everyMinutes(5)` `hourly()` `daily('04:00')`
`weekly(1,'03:30')` `monthly(1,'02:00')`.

- Son çalışma `storage/cache/schedule.json` içinde tutulur — bilerek
  uygulama önbelleğinde değil: `cache:clear` günlük görevleri aynı gün
  ikinci kez çalıştırmamalı.
- **Görevler üst üste binmez** (kilit dosyası; 1 saatten eski kilit
  çökmüş süreçten kalmıştır, yok sayılır).
- Bir görevin hatası diğerlerini engellemez.

---

## 14. E-posta

```php
Mailer::send(
    Mailable::make()
        ->to($user->eposta, $user->fullName())
        ->subject('Hoş geldiniz')
        ->view('emails/hosgeldin', ['ad' => $user->ad])
);
```

**Sürücüler** (Panel → Site Ayarları → E-posta):

| | |
|---|---|
| `kayit` | Mektuplar `storage/mail/*.eml` olarak diske yazılır — **geliştirme için** |
| `smtp` | Gerçek SMTP (TLS/SSL) |
| `php` | `mail()` — XAMPP'ta genelde çalışmaz |

**Gönderim asla sayfayı çökertmez:** `send()` istisna fırlatmaz, `false`
döner ve sonucu `mail_kayitlari` tablosuna yazar. Ziyaretçi, posta
sunucusu kapalı olsa bile hata görmez.

**Toplu gönderim iki adımdır:** mektuplar önce kuyruğa yazılır (hızlı),
sonra parti parti gönderilir. 500 kişiye tek istekte göndermek PHP'nin
zaman aşımına takılır.

**Bildirimler** `Notifier` içinde toplanır; denetleyiciler mektup
kurmaz, olay yayınlar.

```bash
php cy mail:test ali@ornek.com
php cy mail:test --baglanti
php cy mail:work --limit=20
```

---

## 15. REST API

### Yanıt biçimi

```json
{ "success": true,  "data": { … }, "meta": { … } }
{ "success": false, "error": { "code": "gecersiz_veri",
                               "message": "…",
                               "fields": { "eposta": "…" } } }
```

> Panelin AJAX zarfı (`{success, type, description}`) **insan** içindir
> — ekranda bildirim gösterir. API başka bir **program** tarafından
> tüketilir: makine okunur hata kodu ve alan bazlı hata listesi ister.
> İkisini tek biçime zorlamak ikisini de bozardı.

```php
ApiResponse::success($veri);
ApiResponse::created($veri);
ApiResponse::paginated($items, $total, $page, $perPage);
ApiResponse::validationFailed(['eposta' => 'Geçersiz.']);
ApiResponse::notFound();
```

### Kimlik

```
Authorization: Bearer cy_3f9a…
```

Anahtar **düz metin saklanmaz** — SHA-256 özeti tutulur. Açık hali
yalnızca üretildiği anda bir kez gösterilir. Hızlı arama için kısa bir
ön ek ayrıca saklanır (hash'lenmiş sütunda `WHERE` yapılamaz).

Anahtar, sahibi olan kullanıcıdan **fazla yetkiye asla sahip olamaz**;
kullanıcı pasife alınırsa anahtar da geçersizdir.

```php
$sonuc = ApiToken::create($userId, 'Mobil uygulama', daysValid: 90);
echo $sonuc['token'];   // yalnızca burada!
```

### Hız sınırı

Anahtar (ya da anonim istekte IP) başına, pencere başına istek sayısı.
Sayaç **önbellekte** tutulur — veritabanına yazmak, korumaya
çalıştığınız yükün ta kendisini üretirdi.

Yanıt başlıkları: `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
aşımda `Retry-After`.

Geçersiz anahtar denemeleri de sayılır; aksi halde anahtar deneme
saldırısı bedava olurdu.

---

## 16. Modül sistemi

```
modules/Stok/
├── module.json          künye + menü tanımı
├── routes.php           kendi rotaları
├── events.php           kendi dinleyicileri (opsiyonel)
├── migrations/          kendi tabloları
├── src/                 Modules\Stok\… sınıfları
└── views/               kendi ekranları
```

```bash
php cy make:module Stok        # çalışır durumda CRUD iskeleti
php cy module --enable=Stok
php cy migrate                 # modülün tablolarını kurar
php cy module                  # listele
php cy module --disable=Stok
```

**Kapalı modül hiç yüklenmez:** rotaları tanımlanmaz, sınıfları
yüklenmez, olayları dinlenmez, migration'ları çalışmaz. "Kapalı ama
hâlâ çalışıyor" diye bir ara durum yoktur.

Açık modül listesi `ayarlar` tablosunda `aktif_moduller` anahtarında
JSON dizi olarak tutulur — ayrı tablo açılmadı çünkü liste küçüktür,
zaten her istekte belleğe alınır ve çok sunuculu kurulumlarda ortaktır.

**Hata yalıtımı:** bir modülün `routes.php` dosyası patlarsa tüm site
çökmez; o modül atlanır ve hata loglanır.

**Görünümler:** `$this->view('Stok::index', …)` → `modules/Stok/views/index.php`.
Modül, çekirdeğin `views/` klasörünü kirletmez.

**Menü:** `module.json` içindeki `menu` bloğu sol menüde otomatik
görünür; `"menu": false` derseniz görünmez.

**Migration adlandırma:** modül migration'ları `Stok/2026_…` biçiminde
kaydedilir; iki modül aynı dosya adını kullansa bile çakışmaz.
Sıralama modül önekine değil **zaman damgasına** göre yapılır.

---

## 17. PWA

Panel → Site Ayarları → Sistem → **Uygulama Modu (PWA)** ile açılır
(`pwa_aktif` ayarı, **varsayılan kapalı**).

Kapalıyken tek bir etiket bile basılmaz: manifest istenmez, servis
çalışanı kaydedilmez. Özelliği kullanmayan bir proje varlığını hiç
hissetmez. Künye etiketleri iki düzende de aynıydı; tek yerde
toplandılar: `views/partials/pwa-head.php`.

- `manifest.webmanifest` **dinamiktir** (PHP üretir): site adı, tema
  rengi ve logo panelden değişince künye de değişir.
- `sw.js` **kökte statik** bir dosyadır: bir servis çalışanı yalnızca
  bulunduğu klasörü ve altını kontrol edebilir.

**Önbellek stratejisi:**

| İçerik | Strateji |
|---|---|
| CSS/JS/görsel | Önce önbellek, yoksa ağ |
| HTML sayfalar | Önce ağ, olmazsa önbellek |
| POST / API / kurulum | **Hiç dokunulmaz** |

> HTML'de neden önce ağ? Panelde bayat veri göstermek, biraz yavaş
> açılmaktan çok daha kötüdür. Kullanıcı silinmiş bir kaydı ya da eski
> bir bakiyeyi görmemelidir.

Yeni sürüm hazır olduğunda kullanıcıya "Sayfayı yenileyin" bildirimi
gösterilir.

---

## 18. Konsol (`php cy`)

```bash
php cy                      # tüm komutlar
php cy yardim migrate       # ayrıntılı yardım
```

| Grup | Komutlar |
|---|---|
| migrate | `migrate` · `migrate:rollback` · `migrate:status` · `migrate:fresh` |
| db | `db:seed` |
| make | `make:migration` · `make:seeder` · `make:controller` · `make:model` · `make:module` |
| module | `module` (`--enable=` / `--disable=`) |
| cache | `cache:clear` (`--expired`) |
| config | `config:cache` · `config:clear` |
| log | `log:purge` (`--list`, `--days=`) |
| queue | `queue:work` · `queue:status` |
| schedule | `schedule:run` (`--list`, `--force`) |
| mail | `mail:work` · `mail:test` |

**Çıkış kodları anlamlıdır:** 0 başarı, ≠0 hata. Cron ve CI buna bakar.

**Yıkıcı komutlar** (`migrate:fresh`, `migrate:rollback`, `db:seed`)
`APP_ENV=production` iken onay sormaz — `--force` ister ve aksi halde
sıfırdan farklı kod döner. Bir sunucuda yanlışlıkla "e" tuşuna basmak
veritabanını silmeye yetmemelidir.

`cy` dosyası tarayıcıdan erişilemez: `.htaccess` engeller **ve** betik
`PHP_SAPI` kontrolü yapar.

### Yeni komut yazmak

```php
final class TemizleCommand extends Command
{
    public function name(): string        { return 'ornek:temizle'; }
    public function description(): string { return 'Örnekleri temizler.'; }

    public function handle(): int
    {
        $this->out->success('Tamam.');
        return self::BASARILI;
    }
}
```

Sonra `Kernel::COMMANDS` listesine ekleyin.

---

## 19. Arayüz ve tasarım

- **Ön yüz tamamen ayarlardan beslenir.** Ana sayfadaki hiçbir metin
  koda gömülü değildir; bir ayar boşsa o bölüm hiç basılmaz (yarım
  görünen bir sayfa, olmayan bir bölümden kötüdür).
- **SEO:** her sayfada `canonical`, Open Graph ve Twitter Card
  etiketleri; `sitemap.xml` ve `robots.txt` dinamiktir — "SEO →
  İndeksleme" kapatıldığında ikisi de arama motorlarına "taramayın"
  der.
- **CDN yok.** Bootstrap, jQuery, DataTables ve tüm ikonlar yereldir;
  internetsiz ya da CDN engelli bir sunucuda panel eksiksiz açılır.
- **İkonlar satır içi SVG** (`icon('users')`), `currentColor` kullanır.
- **Tema** açık/koyu; tercih çereze **ve** giriş yapmışsa hesaba
  yazılır, böylece başka cihazda da aynı tema gelir. Sunucu sayfayı
  doğrudan doğru temayla üretir — "yanlış temada bir an görünme"
  sorunu yoktur.
- **Satır içi JavaScript yoktur** (CSP `script-src 'self'`).
  Onay gerektiren formlar `data-confirm="…"` kullanır.

### Marka rengi — tek ayar, tüm arayüz

Ayarlar → Sistem → **Tema Rengi** seçicisi panelin ve sitenin ana
rengini belirler. Tasarım kalıbı tek bir marka renginden türetilmiş
token'lar üzerine kurulu olduğu için, `App\Core\Theme` seçilen renkten
tonların tamamını üretir ve sayfaya ~400 baytlık bir `<style>` bloğu
olarak basar:

```php
Theme::brand();      // '#7c3aed'  (doğrulanmış, güvenli)
Theme::palette();    // [800 => '#381a6b', … 50 => '#faf7fe']
Theme::styleTag();   // <style>:root{--cy-brand-600:#7c3aed; …}</style>
```

Butonlar, bağlantılar, aktif menü, odak halkası ve gradyan aynı anda
değişir. **Varsayılan renk seçiliyse tek bayt bile basılmaz** —
CSS dosyasındaki değerler zaten geçerlidir.

`Theme::normalize()` aynı zamanda bir güvenlik sınırıdır: paneldeki
metin kutusuna ne yazılırsa yazılsın, CSS'e giden değer her zaman tam
yedi karakterlik bir `#rrggbb` dizisidir.

### Panel tabloları tek yerden kurulur

Kullanıcılar, Mesajlar ve E-posta ekranları aynı sunucu taraflı
DataTables kurulumunu paylaşır. Ortak kısım iki yerde toplanmıştır:

| Katman | Nerede | Ne yapar |
|---|---|---|
| JavaScript | `CY.table()` (app.js) | CSRF, Türkçe metinler, sayfa boyutu, kayıt sayacı |
| PHP | `Controller::tableQuery()` / `tableJson()` | Arama, sıralama, sayfalama, yanıt zarfı |

Bir ekran yalnızca kendine özgü olanı yazar:

```php
$result = $this->users()->paginate($this->tableQuery($request) + [
    'role' => $request->input('filter_role'),
]);

$this->tableJson($request, $result, $rows);
```

Sayfa başına kayıt sayısı **Ayarlar → Sistem → Sayfa Başına Kayıt**
değerinden gelir; sunucu bunu `<meta name="cy-page-length">` ile
bildirir, `CY.table()` okur.

### Mobil kuralları

- **Sayfa asla yatay kaymaz.** Taşan içerik (tablo, kod, uzun adres)
  kendi kutusunda kayar (`.cy-table-wrap`).
- Sekme çubukları dar ekranda yatay kaydırılır, alt satıra kırılmaz.
- Dokunmatik hedefler en az 38px.
- Form alanları `575.98px` altında **16px** yazı boyutu kullanır —
  daha küçüğünde iOS odaklanınca sayfayı yakınlaştırır.
- Sol menü `<992px` altında çekmece, üstünde daraltılabilir sütun;
  tercih çereze yazılır.
- Ayarlar sayfasında kaydet çubuğu **yapışkandır**.

### Yeni sayfa eklemek

```php
// 1. Denetleyici
final class UrunController extends Controller {
    public function index(Request $request): void {
        $this->view('urunler/index', ['title' => 'Ürünler']);
    }
}

// 2. Rota
$router->get('panel/urunler', UrunController::class, 'index',
    ['installed', 'auth', 'can:urun.view']);

// 3. views/urunler/index.php
// 4. views/partials/sidebar.php → $menu dizisine bir satır
```

---

## 20. Güvenlik

| Tehdit | Önlem |
|---|---|
| SQL Injection | Hazırlıklı sorgular; sıralama sütunu beyaz listeden |
| XSS | `e()` ile kaçışlama; CSP `script-src 'self'` |
| CSRF | Her POST'ta token (form alanı **veya** `X-CSRF-Token` başlığı) |
| Oturum çalma | `httponly` + `samesite` + `secure`, parmak izi, periyodik yenileme |
| Oturum sabitleme | Girişte `session_regenerate_id(true)` |
| Kaba kuvvet | Hız sınırı + kilit (kimlik+IP) |
| Kullanıcı sayımı | Sabit süreli yanıt, genel hata mesajı |
| Path traversal | Segment bazlı doğrulama + `realpath()` |
| Kötücül yükleme | 9 katmanlı savunma (bkz. §10) |
| Dosya ifşası | `app/`, `config/`, `storage/`, `views/`, `.env`, `cy` web'e kapalı |
| Clickjacking | `X-Frame-Options: DENY` |
| MIME sniffing | `X-Content-Type-Options: nosniff` |
| Bilgi sızması | Yayında yığın izi/dosya yolu gösterilmez |
| Spam | Bal küpü + zaman kontrolü + oturum hız sınırı |
| API kötüye kullanımı | Bearer + hash'lenmiş anahtar + hız sınırı |

### İçerik Güvenliği Politikası ve dış servisler

Varsayılan politika katıdır: `script-src 'self'` — satır içi betik
çalışmaz, dış sunucudan betik yüklenmez. Bir harita, ödeme çerçevesi
ya da sohbet penceresi engellendiğinde **politikayı kapatmayın**;
`config/security.php` üzerinden yalnızca o servise izin verin:

```dotenv
CSP_SCRIPT_SRC="https://js.stripe.com"
CSP_FRAME_SRC="https://js.stripe.com"
```

**Analytics kodu için bir şey yapmanız gerekmez.** Ayarlar → SEO →
Analytics Kodu alanına yapıştırdığınız HTML'i `Response::csp()`
inceler ve tam olarak gerekeni açar:

- `<script src="https://…">` → o adresin **kaynağı** (origin) eklenir
- `<script>…</script>` → o metnin **sha256 özeti** eklenir

```
script-src 'self' https://www.googletagmanager.com 'sha256-o3XbuMEM…'
```

Bir boşluk bile değişse özet tutmaz; bu bilinçlidir — `'unsafe-inline'`
açmaktan kıyaslanamayacak kadar dardır. Bu otomatik davranış olmasaydı
yönetici kodu yapıştırır, hiçbir hata görmez ve kodun sessizce
engellendiğini haftalarca fark etmezdi.

### Bal küpü hakkında bir ders

İletişim formunun gizli alanı bir zamanlar `website` adını taşıyordu.
Tarayıcıların **otomatik doldurma** özelliği bu alanı doldurdu ve
gerçek ziyaretçilerin mesajları bot sanılıp sessizce çöpe atıldı —
üstelik hiçbir yerde iz kalmadığı için uzun süre fark edilmedi.

İki ders, ikisi de koda işlendi:

1. Bal küpü alanının adı **hiçbir otomatik doldurma sezgisine
   uymamalıdır** (`cy_kontrol`).
2. **Sessiz ≠ izsiz.** Her eleme artık `security` kanalına yazılır.

---

## 21. Yeni projeye başlarken

```bash
# 1. Kopyala
git clone https://github.com/CilginYazilim/cy-php-starter yeni-proje
cd yeni-proje && rm -rf .git

# 2. Tarayıcıda kurulum/ adresini aç, sihirbazı tamamla
#    .env üretilir · tablolar kurulur · migration'lar çalışır ·
#    yönetici hesabı açılır. "Örnek verileri de yükle" kutusunu
#    GERÇEK bir projede işaretlemeyin.
#    Son adımdaki düğme kurulum/ klasörünü siler.

# 3. Örnek modülü kaldırın (isteğe bağlı)
rm -rf modules/Ornek
rm app/Jobs/OrnekIs.php

# 4. Kendi modülünü üret
php cy make:module Stok
php cy module --enable=Stok
php cy migrate
```

Sonra:

- `app/Models/Role.php` → yeni yetkileri ilgili rollere ekleyin
- `views/partials/sidebar.php` → menüyü düzenleyin
- Panel → Site Ayarları → site adı, logo, **tema rengi**, SMTP
- `config/*.php` → gerekirse varsayılanları değiştirin

> **Komut satırınız yoksa** 4. adımı atlayın: sihirbaz tabloların
> tamamını zaten kurdu, panel eksiksiz çalışır.

---

## 22. Canlıya çıkış listesi

```bash
# .env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=info

php cy config:cache
php cy migrate
```

- [ ] `kurulum/` klasörünü **silin** (sihirbazın son adımındaki düğme
      bunu tek tıkla yapar)
- [ ] Örnek veri yüklediyseniz temizleyin:
      `DELETE FROM kullanicilar WHERE eposta LIKE '%@ornek.com';`
- [ ] SSL sertifikası (HTTPS) aktif
- [ ] `storage/` ve `upload/` yazılabilir, web'e kapalı
- [ ] SMTP bilgileri girildi, `php cy mail:test` başarılı
- [ ] Cron kuruldu:
  ```cron
  * * * * * cd /yol/site && php cy schedule:run >> /dev/null 2>&1
  ```
- [ ] Yedekleme planı var
- [ ] **Panel → Sistem Bilgisi** sayfasındaki tüm denetimler yeşil

Sistem Bilgisi sayfası bu listenin canlı hâlidir: her denetim, sorunun
nasıl çözüleceğini de yazar.

---

<div align="center">

**Çılgın Yazılım** · [cilginyazilim.com](https://cilginyazilim.com)

</div>
