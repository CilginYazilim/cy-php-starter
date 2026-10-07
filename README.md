<div align="center">

# PHP Başlangıç Şablonu (CY PHP Starter)

### Sıfır bağımlılıklı PHP 8 başlangıç şablonu — her yeni projeye buradan başlayın

**Kurulum sihirbazı · rol tabanlı yönetim paneli · parola sıfırlama · mobil uygulama API'si · konsol · migration · kuyruk · olay · modül sistemi · PWA — hepsi hazır.**

[![Sürüm](https://img.shields.io/badge/Sürüm-1.6.1-0b5cb5?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/releases/latest)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
![Bağımlılık: sıfır](https://img.shields.io/badge/Bağımlılık-Sıfır-16a34a?style=flat-square)
[![Testler](https://img.shields.io/badge/Birim_testi-314-16a34a?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/actions)
[![CI](https://github.com/CilginYazilim/cy-php-starter/actions/workflows/ci.yml/badge.svg)](https://github.com/CilginYazilim/cy-php-starter/actions/workflows/ci.yml)
[![Lisans](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](https://github.com/CilginYazilim/cy-php-starter/blob/main/LICENSE)

[**▶ Canlı Demo**](https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/) · [PHP Başlangıç Şablonu sayfası](https://cilginyazilim.com/kutuphane/php-baslangic-sablonu) · [Çılgın Yazılım](https://cilginyazilim.com)

**[📖 Sistem Kılavuzu](https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md)**
· **[💡 Kod Kütüphanesi](https://cilginyazilim.com/kutuphane)**
· **[📝 Değişiklik Günlüğü](https://github.com/CilginYazilim/cy-php-starter/blob/main/CHANGELOG.md)**

<sub>Zero-dependency PHP 8 starter template (boilerplate) with a setup wizard, role-based admin panel, password reset, mobile-ready REST API with session tokens, migrations, queue, events, modules and PWA. No Composer, no framework, no CDN.</sub>

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
  <img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/01-anasayfa.png" alt="PHP başlangıç şablonu ana sayfası: panelden yönetilen karşılama bölümü ve yönetim paneli görüntüsü" width="860">
</a>

<sub>▲ Görsele tıklayarak demoyu açabilirsiniz</sub>

</div>

> **`kurulum/` adresini açın; iki ekranda çalışan bir site ve yönetim paneli elde edin.**

---

## Ekran Görüntüleri

Aşağıdaki kareler bu depodaki kodun **kurulduktan sonraki** hâlidir; hepsi
`kurulum/` sihirbazı tamamlandıktan sonra, örnek veriyle çekilmiştir.

### Kurulum sihirbazı

Şablonun ayırt edici yanı burada başlar: `.env` dosyasını elle yazmaz,
veritabanını elle açmazsınız. İlk ekranda sunucu tek satırda denetlenir ve
veritabanı bağlantısı **"Bağlantıyı dene"** ile anında sınanır; ikinci
ekranda site ve yönetici bilgileri girilir. Bitişte her adım ✓ ile
raporlanır, örnek veriyle kurduysanız demo hesaplar listelenir.

<table>
<tr>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/02-kurulum-veritabani.png" alt="PHP kurulum sihirbazı: sunucu denetimi ve MySQL veritabanı bağlantısı"></td>
<td width="50%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/03-kurulum-tamam.png" alt="PHP kurulum sihirbazı: adım adım kurulum raporu ve demo hesaplar"></td>
</tr>
<tr>
<td align="center"><sub><b>1. ekran</b> — sunucu denetimi ve veritabanı</sub></td>
<td align="center"><sub><b>Bitiş</b> — kurulum raporu ve demo hesaplar</sub></td>
</tr>
</table>

### Panel

<img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/05-panel-ozet.png" alt="PHP yönetim paneli: özet kartları, 14 günlük kayıt grafiği ve son mesajlar" width="100%">

<sub>Kontrol paneli. Kartlar role göre (editör kullanıcı sayısını görmez), 14 günlük kayıt grafiği, onay bekleyen kayıtlar — hepsi veritabanından.</sub>

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
<td align="center"><sub>Ayarlar — ana sayfanın her bölümü panelden</sub></td>
<td align="center"><sub>Sistem bilgisi — güvenlik denetim listesi</sub></td>
</tr>
</table>

### Ön yüz, koyu tema ve mobil

<table>
<tr>
<td width="40%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/13-roller.png" alt="Ana sayfadaki Rolleri deneyin bölümü: yönetici, editör ve üye kartları"></td>
<td width="40%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/11-koyu-tema.png" alt="Yönetim panelinin koyu tema görünümü"></td>
<td width="20%"><img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/12-mobil.png" alt="Yönetim panelinin mobil telefon görünümü"></td>
</tr>
<tr>
<td align="center"><sub>"Rolleri deneyin" — tek tıkla demo girişi</sub></td>
<td align="center"><sub>Koyu tema — tek CSS değişken seti</sub></td>
<td align="center"><sub>Mobil — tablolar karta döner</sub></td>
</tr>
</table>

<img src="https://raw.githubusercontent.com/CilginYazilim/cy-php-starter/main/docs/screenshots/14-ornek-modul.png" alt="Örnek Modül: onay akışı, rol tabanlı düğmeler ve yetki matrisi" width="100%">

<sub>Örnek Modül — üye yazar, editör onaylar; düğmeler ve yetki matrisi kodun o anki cevabıdır.</sub>

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
| **Kurulum** | **İki ekranlı sihirbaz** · "Bağlantıyı dene" · anlaşılır veritabanı hataları · `.env` üretimi · tabloları **ve migration'ları** kurar · yönetici hesabı · tek tıkla örnek veri · adım adım rapor · canlı sunucuda kurulum anahtarı |
| **Kimlik** | Giriş/kayıt/çıkış · **kayıtta e-posta doğrulaması** · **parolamı unuttum** (tek kullanımlık bağlantı) · "parolanız değişti" ve **eski adrese "e-posta adresiniz değişti"** e-postaları · **beni hatırla** · güvenilen cihaz · rol-yetki · kaba kuvvet koruması · sertleştirilmiş oturum |
| **KVKK** | Kayıt ve iletişim formunda aydınlatma onayı · IP/tarayıcı saklama süresi ve otomatik silme · **hesabımı sil** (7 gün bekleme, girişle iptal) · hazır "Gizlilik ve KVKK" sayfası |
| **Yönlendirme** | Temiz SEO adresleri · `{parametre}` · GET/POST/PUT/PATCH/DELETE · gruplar |
| **Hata yönetimi** | Merkezi işleyici · ölümcül hata yakalama · geliştirici ekranı · güvenli 404/403/419/500 |
| **Günlük** | Kanal bazlı (`app` `error` `security` `auth` `mail` `queue`) · parola maskeleme · rotasyon |
| **Veritabanı** | Migration + rollback + parti · seeder · Repository deseni |
| **Depolama** | `public` / `private` disk · 9 katmanlı yükleme güvenliği · güvenli indirme |
| **Önbellek** | Dosya / veritabanı / kapalı sürücüleri · `remember()` |
| **Olaylar** | Yayıncı-dinleyici · hata yalıtımı · test için `fake()` |
| **Kuyruk** | Veritabanı kuyruğu · atomik ayırma · katlanan yeniden deneme · kilitlenmeye dayanıklı |
| **Zamanlayıcı** | Tek cron satırı · üst üste binme koruması |
| **E-posta** | SMTP / mail() / diske yazma · toplu gönderim · kuyruk · panel arayüzü · yeni üye bildirimi · yöneticinin açtığı hesaba **"parolanızı belirleyin"** bağlantısı (parola mektuba yazılmaz) · üye duyuruları Hesabım'dan ya da **imzalı tek tıkla** kapatır |
| **REST API** | Standart yanıt zarfı · süreli Bearer anahtarı (yalnızca SHA-256 özeti saklanır) · **yalnız okuma / okuma+yazma kapsamı** · hız sınırı · sayfalama · JSON gövde |
| **Mobil uygulama** | **Kullanıcı adı/parolayla giriş → token** · çıkış · açık cihazlar ve tek tek kapatma · örnek dosya listesi/indirme · [rehber](https://github.com/CilginYazilim/cy-php-starter/blob/main/docs/MOBIL-API.md) |
| **Modüller** | **Panelden tek tıkla aç/kapa** · kendi rotaları, tabloları, görünümleri, menüsü, **rollere dağıttığı yetkileri** ve örnek verisi · CRUD, onay akışı ve RBAC'ı gösteren **Örnek Modül** kurulumda açık gelir |
| **Demo modu** | Herkese açık deneme sitesi için: giriş ekranında Yönetici / Editör / Üye ile **tek tıkla giriş** · örnek hesaplara parola, kullanıcı, ayar ve e-posta kilidi · **3 saatte bir kendini sıfırlar** |
| **Örnek veri** | Tek kaynak (`DemoData`): 6 hesap, sayfalar, mesajlar, e-postalar, markalı vitrin · panelden ya da `php cy demo:temizle` ile tek tıkla kaldırılır |
| **PWA** | Panelden yönetilen künye (ad, simge, açılış adresi, görüntüleme modu, renk) · servis çalışanı · çevrimdışı sayfa |
| **İçerik** | **Sayfa yönetimi** — zengin metin editörü · editörden görsel yükleme · kapak görseli · **30 dakikalık imzalı önizleme** · adres (slug) üretimi · taslak/yayın · menü · sayfa bazlı SEO |
| **Ana sayfa** | Bütün bölümler ve metinler panelden (Ayarlar → Ana Sayfa): karşılama, özellikler, adımlar, rolleri deneyin, kod örneği, SSS, son bant — koda gömülü metin yok |
| **SEO** | Temiz adresler · canonical · Open Graph · JSON-LD (Organization, WebSite, SoftwareApplication, WebPage, BreadcrumbList) · dinamik sitemap.xml & robots.txt (panelden ek kural) |
| **Ayarlar** | Bölüm bölüm sayfalar · durum özetli genel bakış · kapsamlı kaydetme · logo **ve favicon** yükleme |
| **İletişim** | Form + spam koruması · **WhatsApp düğmesi** (hazır mesajla) · sosyal medya bağlantıları |
| **Tema** | Tek renk seçin, panelin ve sitenin tamamı yeniden renklensin |
| **Mobil** | Ön yüz ve panelin tamamı mobil öncelikli · tablolar telefonda karta döner · 44px dokunma hedefleri · yapışkan menü · iOS yakınlaştırma ve çentik payı çözülmüş |
| **Konsol** | `php cy` — 24 komut, üreteçler ve XAMPP'siz geliştirme sunucusu (`php cy serve`) dahil |
| **Testler** | 314 birim testi (rol matrisi, XSS, politika — veritabanı gerektirmez) · 54 duman testi · GitHub Actions'ta PHP 8.1–8.4 ve **MySQL 8 ile gerçek kurulum** |

---

## Demo hesaplar

Örnek veriyle kurulumda (ve [canlı demoda](https://cilginyazilim.com/kutuphane/uygulama/cy-php-starter/))
şu hesaplar gelir. Ortak parola yalnızca demo modunda ya da geliştirme
ortamında **giriş ekranında** görünür; satıra tıklamak yeterlidir.

| Rol | Kullanıcı adı | Ne görür, ne yapar |
|---|---|---|
| Yönetici | `ali.yonetici` | Her ekran. Demo modunda parola, kullanıcı, ayar ve e-posta işlemleri kilitli |
| Editör | `elif.editor` | Sayfaları yazar, mesajları yanıtlar, Örnek Modül'de onay bekleyenleri yayınlar |
| Üye | `mehmet.uye` | Kendi profili ve Örnek Modül'de kendi kayıtları (yazar, onaya gönderir) |
| Pasif üye | `ayse.pasif` | Giriş yapamaz — durum denetimini gösterir |
| Askıdaki üye | `can.askida` | Giriş yapamaz |
| Onay bekleyen | `zeynep.onay` | E-posta doğrulanmadan giriş yapamaz |

Ana sayfadaki **"Rolleri deneyin"** kartları (`/giris?demo=editor`) giriş
ekranında o hesabı vurgular; parola adreste asla taşınmaz. Gerçek bir
sitede örnek veriyi **Panel → Sistem → Örnek veriyi kaldır** ile tek
tıkla silin.

## Rol ve yetki matrisi

| Ekran / işlem | Yönetici | Editör | Üye | Ziyaretçi |
|---|:-:|:-:|:-:|:-:|
| Kontrol paneli, Hesabım, Örnek Modül | ✅ | ✅ | ✅ | girişe yönlenir |
| Mesajlar, E-posta geçmişi, Sayfalar | ✅ | ✅ | ❌ 403 | girişe yönlenir |
| Kullanıcılar, Ayarlar, Sistem | ✅ | ❌ 403 | ❌ 403 | girişe yönlenir |
| API anahtarı üretmek | ✅ | ✅ | ❌ | — |
| `GET /api/v1/ben`, mobil oturum | ✅ | ✅ | ✅ | 401 |
| `GET /api/v1/kullanicilar` | ✅ | ❌ 403 | ❌ 403 | 401 |
| `GET /api/v1/sayfalar` | ✅ | ✅ | ✅ | ✅ |
| Örnek Modül: başkasının kaydını düzenle/sil | ✅ | ❌ | ❌ | — |
| Örnek Modül: onay bekleyeni yayınla | ✅ | ✅ | ❌ | — |

Bu tablo kodda da yazılıdır: [`tests/rol-matrisi.php`](https://github.com/CilginYazilim/cy-php-starter/blob/main/tests/rol-matrisi.php)
96 rotanın her biri için kimin erişebildiğini tutar; bir rotanın yetkisi
değişirse birim testi kırılır. Duman testi aynı matrisi demo hesaplarla,
**demo modu açık ve kapalı** olarak gerçek HTTP isteğiyle sınar. Demo
kilidi yetkiden SONRA çalışır: yetkisi olmayan "yetkiniz yok" görür.

---

## Kurulum

### Gereksinimler

- PHP **8.1+** (`pdo_mysql`, `mbstring`, `json`; `gd` ve `fileinfo` önerilir)
- MySQL 5.7+ / MariaDB 10.3+
- Apache (`mod_rewrite`) ya da Nginx

### Adımlar

**1. İndirin.** [ZIP olarak indirin](https://github.com/CilginYazilim/cy-php-starter/archive/refs/heads/main.zip) ya da:

```bash
git clone https://github.com/CilginYazilim/cy-php-starter
```

Klasörü sunucunuzun web klasörüne koyun (XAMPP'te `htdocs/`) ve boş bir
MySQL veritabanı açın (karakter seti `utf8mb4`).

**2. Sihirbazı açın:** **`http://siteniz/kurulum/`**

- *1. ekran:* sunucu tek satırda denetlenir; veritabanı bilgilerini yazıp
  **Bağlantıyı dene**'ye basın.
- *2. ekran:* site adı ve adresi, yönetici hesabı, seçenekler
  (**Örnek veriyle kur** — localhost'ta varsayılan açık; **Demo modu**;
  **Geliştirme modu**; **PWA**; açılacak modüller). **Kur**'a basın.

**3. Panele girin.** Bitiş ekranı her adımı ✓ ile raporlar; **Panele git**
ile girin ve **Kurulum klasörünü sil** düğmesine basın (sonradan da
Panel → Sistem'den silinebilir).

**Komut satırı gerekmez** — sihirbaz `.env` dosyasını yazar, tabloları
kurar, migration'ları çalıştırır ve yönetici hesabınızı açar. Paylaşımlı
hostingde de eksiksiz kurulur.

> **XAMPP yok mu?** PHP yüklüyse proje klasöründe `php cy serve` yazın ve
> `http://127.0.0.1:8000/kurulum/` adresini açın (yalnızca geliştirme için).

> **Canlı sunucuda kurulum anahtarı istenir.** Sihirbaz yerel olmayan bir
> adresten açılırsa `storage/kurulum-anahtari.txt` dosyasındaki anahtarı
> sorar: sunucuya dosya yükleyebilen kişi siz olduğunuz için kurulumu
> başkası yapamaz.

> **Sihirbaz kendini kilitler.** Kurulum bitince `.env` ve
> `storage/installed.lock` yazılır; bu dosyalardan biri varken sihirbaz
> hiçbir adımı çalıştırmaz (veritabanı erişilemese bile). Yeniden kurmak
> için ikisini de **sunucudan elle** silin — tarayıcıdan yeniden kurulum
> bilerek mümkün değildir.

> **Varsayılan ortam yayındır** (`APP_ENV=production`, `APP_DEBUG=false`).
> Localhost'ta sihirbaz **Geliştirme modu**nu açık getirir; canlı sunucuda
> kapalıdır.

> **Örnek veri:** "Örnek veriyle kur" 6 demo hesap (yukarıdaki
> [Demo hesaplar](#demo-hesaplar) tablosu), örnek sayfalar, mesajlar,
> e-posta kayıtları ve CY PHP Starter tanıtım vitrini kurar. Gerçek bir
> projeye başlıyorsanız **kapatın**; kurduysanız Panel → Sistem →
> **Örnek veriyi kaldır** tek tıkla temizler.

> **Demo modu (herkese açık deneme sitesi):** Sihirbazdaki **Demo modu**
> kutusu (ya da `.env` → `APP_DEMO=true`) giriş ekranına bir "Demo
> hesaplar · tek tıkla giriş" listesi koyar: Yönetici, Editör ve Üye —
> ziyaretçi parola yazmadan, satıra tıklayarak girer. Örnek hesaplarla hesap bilgileri, kullanıcılar, site
> ayarları ve e-posta gönderimi kilitlidir; kurulumda açtığınız yönetici
> hesabı kısıtlanmaz; demo 3 saatte bir kendini sıfırlar. **Gerçek bir sitede işaretlemeyin.**

> **Örnek Modül kurulumda açık gelir.** Sihirbazın ikinci ekranındaki
> **Modüller** bölümünde işaretlidir; kurulum modülü açar ve tablosunu
> kurar (örnek veri seçildiyse her rolden, her durumda kayıtla). Panelde
> **Örnek Modül** ekranı rol tabanlı yetkiyi (RBAC) canlı gösterir:
> yönetici her kaydı yönetir, editör yalnızca kendi kaydını, üye
> yalnızca yayındakileri görür; ekrandaki yetki matrisi kodun o anki
> cevabıdır. İstemiyorsanız kutuyu boşaltın.

> **Kayıt formu e-posta doğrulamasıyla gelir.** Yeni hesap, e-postadaki
> bağlantıya tıklanana kadar giriş yapamaz; form, adresin zaten kayıtlı
> olup olmadığını da belli etmez. E-posta ayarları (Panel → Ayarlar →
> E-posta) yapılmadıysa kayıt formu ve **"Parolamı unuttum"** bağlantısı
> kendiliğinden kapalı kalır. Doğrulamayı **Ayarlar → Sistem → Kayıtta
> E-posta Doğrulaması** ile kapatabilirsiniz.

> **`mod_rewrite` yoksa:** `.env` içinde `APP_PRETTY_URLS=false` yapın.
> Uygulama `index.php?r=…` biçimine döner, başka hiçbir şey değişmez.

### SQL dosyasıyla kurulum (sihirbazsız)

Sihirbazı çalıştıramıyorsanız ya da örnek veriyi incelemek istiyorsanız
hazır veritabanını içe aktarın:
[`database/ornek-veritabani.sql`](https://github.com/CilginYazilim/cy-php-starter/blob/main/database/ornek-veritabani.sql).
Sihirbazın **Örnek veriyle kur** seçeneğiyle kurduğu veritabanının
aynısıdır: bütün tablolar, ayarlar, sayfalar, 6 örnek hesap, mesajlar ve
Örnek Modül kayıtları.

1. Boş bir veritabanı açın (`utf8mb4`) ve dosyayı içe aktarın: phpMyAdmin →
   **İçe Aktar**, ya da `mysql -u KULLANICI -p VERITABANI < database/ornek-veritabani.sql`.
2. `.env.example`'ı `.env` adıyla kopyalayın; `DB_HOST`, `DB_NAME`,
   `DB_USER`, `DB_PASS` ve `APP_URL`'i doldurun. `APP_KEY` için
   `php -r "echo bin2hex(random_bytes(32));"` çıktısını yazın.
3. `kurulum/` klasörünü silin ve **ali.yonetici / Demo1234!** ile girin.

> **Örnek hesapların parolası herkesçe bilinir.** Yayına almadan önce
> kendinize bir yönetici hesabı açın, ardından Panel → Sistem → **Örnek
> veriyi kaldır**. Size ait etkin bir yönetici yokken bu düğme bilerek
> çalışmaz; yoksa panele girecek kimse kalmazdı.

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
│   └── Support/            helpers.php · gelistirme-sunucusu.php (php cy serve)
│
├── config/                 app db session log cache queue api storage
│                           upload security validation events
├── routes/                 web.php · events.php · schedule.php
├── database/               migrations/ · seeders/ · ornek-veritabani.sql
├── modules/                Eklenebilir modüller (Ornek/ = çalışan örnek)
├── views/                  layouts · partials · emails · errors · sayfalar
├── assets/                 css · js · images (CDN yok)
├── storage/                logs · cache · files · mail (web'e kapalı)
├── upload/                 Yüklenen görseller (PHP çalıştırma kapalı)
├── docs/                   MOBIL-API.md · ekran görüntüleri
├── tests/                  unit · smoke · kurulum · sema · rol-matrisi · ornek-sql (web'e kapalı)
└── kurulum/                Sihirbaz + database.sql (sonra SİLİN)
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

# Örnek veri ve demo
php cy db:seed                     php cy demo:reset    php cy demo:temizle

# XAMPP'siz geliştirme sunucusu → http://127.0.0.1:8000
php cy serve                       php cy serve --port=8080

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
| `POST api/v1/oturum` | anahtarsız (kaba kuvvet korumalı) | **Mobil giriş:** `kullanici`, `parola`, `cihaz` → `token` |
| `DELETE api/v1/oturum` | anahtar | Bu cihazdan çıkış |
| `GET api/v1/oturumlar` · `DELETE api/v1/oturumlar/{id}` | anahtar | Açık cihazlar; başka cihazı kapatma |
| `GET api/v1/ben` | anahtar | Giriş yapan kullanıcı, token kapsamı ve türü |
| `GET api/v1/dosyalar` · `api/v1/dosyalar/{ad}` | anahtar | Örnek dosya listesi ve indirme |
| `GET api/v1/kullanicilar?sayfa=1&boyut=25&ara=…` | anahtar + `users.view` | Sayfalanmış kullanıcı listesi |
| `GET api/v1/sayfalar` | anahtarsız, hız sınırlı | Yayındaki içerik sayfaları |
| `GET api/v1/sayfalar/{slug}` | anahtarsız, hız sınırlı | Tek sayfanın içeriği |

### Mobil uygulama için oturum

Mobil uygulama anahtarı elle almaz; kullanıcı adı ve parolayla giriş yapar,
dönen token'ı güvenli depoda saklar:

```bash
curl -X POST https://siteniz.com/api/v1/oturum \
     -H "Content-Type: application/json" \
     -d '{"kullanici":"mehmet.uye","parola":"…","cihaz":"Pixel 8"}'
# → { "success": true, "data": { "token": "cy_…", "son_gecerlilik": "…", "kullanici": { … } } }

curl -H "Authorization: Bearer cy_…" https://siteniz.com/api/v1/dosyalar
curl -X DELETE -H "Authorization: Bearer cy_…" https://siteniz.com/api/v1/oturum
```

Giriş, panel girişiyle **aynı kaba kuvvet korumasından** geçer. Token
30 gün geçerlidir (`API_SESSION_DAYS`); kullanıcı açık cihazlarını
**Panel → Hesabım → Bağlı cihazlar**'da görür ve tek tek kapatır.
Akış, hata kodları, JavaScript ve Flutter örnekleri:
**[Mobil API rehberi](https://github.com/CilginYazilim/cy-php-starter/blob/main/docs/MOBIL-API.md)**.

### Anahtarlar

- Anahtarla gelen istekler **durumsuzdur**: oturum açılmaz, çerez dönmez.
  Anahtar iptal edildiği an erişim biter.
- Anahtar, sahibinin yetkilerinden fazlasına erişemez; sahibi pasife
  alınırsa anahtarı da çalışmaz.
- Panelden anahtar üretmek **mevcut parolayı** ister; süre 30/90/180/365
  gün, kapsam **yalnız okuma** (varsayılan) ya da **okuma + yazma** seçilir.
  Yalnız okuma anahtarı `GET` dışında `403 kapsam_yetersiz` alır. Parola değişince kullanıcının **bütün anahtarları iptal
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
php tests/smoke.php http://localhost/proje --kullanici=admin --parola=…
php tests/kurulum.php http://127.0.0.1:8000 --db-adi=bos_db --ornek-veri --demo   # sihirbazı uçtan uca
php tests/sema.php --eski=eski-database.sql          # yükseltilen şema = yeni kurulum mu?
php tests/ornek-sql.php --db-adi=ornek_db             # örnek veritabanı SQL dosyasını üretir
```

`unit.php` 314 testi veritabanı olmadan çalıştırır; aralarında **rol
matrisi** (96 rotanın her biri için kimin erişebildiği), 18 XSS vektörü,
Örnek Modül politikası ve kurulum şeması tutarlılığı vardır. `smoke.php`
siteyi değiştirmez: kurulum kilidi, gizli dosyalar (`.env`, `.git/`), açık
yönlendirme, kaba kuvvet kilidi, kullanıcı tespiti, oturum çerezi, mobil
API akışı ve — örnek veriyle kurulmuş sitede — demo hesaplarla **rol
matrisini** (GET ve CSRF'li POST) dışarıdan sınar. Kısa sürede arka arkaya
çalıştırırsanız IP kilidi devreye girer; ilgili testler "atlandı" görünür
(koruma çalışıyor demektir).

GitHub Actions her itmede iki iş çalıştırır (`.github/workflows/ci.yml`):
PHP 8.1–8.4'te sözdizimi + birim testleri; ardından **MySQL 8 ile gerçek
kurulum** — depo yerleşik PHP sunucusunda açılır, sihirbaz HTTP üzerinden
örnek veriyle çalıştırılır, duman testi **demo modu açık ve kapalı** iki
kez koşar ve v1.2.0'dan bu yana her sürümden yükseltilen şemanın yeni
kurulumla birebir aynı olduğu denetlenir.

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
| Mobil uygulama bağlantısı | [Mobil API rehberi (docs/MOBIL-API.md)](https://github.com/CilginYazilim/cy-php-starter/blob/main/docs/MOBIL-API.md) |
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
| Yerleşim | Hero eylemleri tek sütun; iletişimde **form önce** gelir | Özet kartlar 2 sütun; tablolar 768px altında etiketli kartlara döner, yatay kaydırma yok |
| Modallar | — | Alttan açılan sayfa görünümü, gövde kendi içinde kayar |
| Uzun formlar | — | Sayfa düzenleyicide Kaydet/Önizle altta yapışık çubukta |
| Çentikli ekran | `env(safe-area-inset-*)` payları | Aynı |

Kırılma noktaları iki yüzde de aynıdır: `992px` (tablet) ve `768px`
(telefon); `360px` altı için ayrıca sadeleştirme vardır.

---

## Güvenlik

Şablon iki ayrı güvenlik incelemesinden (1.3.0 ve 1.4.0) ve bağımsız bir
rol/yetki test raporundan (1.6.0) geçti. Her bulgu yerel bir kurulumda
yeniden üretildi, düzeltildi ve testle sınandı.

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
| Herkese açık demo | Demo modunda örnek hesaplar parolayı, kullanıcıları, ayarları değiştiremez, e-posta gönderemez; kilit yetki denetiminden SONRA çalışır; anahtar `.env`'de (panelden kapatılamaz); demo kapalıyken unutulan örnek hesaplar için panel uyarır |
| Parola sıfırlama | Tek kullanımlık, 60 dakikalık bağlantı; yalnızca özet saklanır; her durumda aynı yanıt; IP ve adres başına sınır; başarıda bütün oturumlar ve anahtarlar kapanır; sahibine "parolanız değişti" e-postası |
| Yönetici hesapları | Yalnızca yönetici değiştirir/siler/pasife alır; yönetici rolünü yalnız yönetici verir; e-posta/parola değişirken işlemi yapanın parolası istenir; son yönetici kuralı atomik |
| İçerikte dış görsel | Sayfa içeriğindeki görsel yalnızca kendi alan adınızdan (ya da `CSP_IMG_SRC`) gelebilir |
| Yetki gerilemesi | Rol matrisi birim testi + CI'da MySQL 8 ile demo açık/kapalı duman testi |

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
- [ ] Demo sitesi değilse `APP_DEMO=false` ve örnek veri kaldırıldı
      (Panel → Sistem → **Örnek veriyi kaldır** ya da `php cy demo:temizle`)
- [ ] E-posta ayarları yapıldı (kayıt formu ve "Parolamı unuttum" ancak o zaman açılır)
- [ ] Ayarlar → Sistem → KVKK sayfası ve IP saklama süresi kontrol edildi
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
# ("Örnek veriyle kur" ve "Demo modu" kapalı,
#  Modüller bölümünde "Örnek Modül" işaretsiz)

rm -rf modules/Ornek app/Jobs/OrnekIs.php

php cy make:module KendiModulun
```

Ardından Panel → **Site Ayarları**'ndan site adını, marka adını, logoyu
ve tema rengini, **Ana Sayfa** grubundan da ana sayfanın bölümlerini ve
metinlerini kendinize göre ayarlayın; arayüz anında yeni renginizi alır.

---

## Sürüm

Güncel kararlı sürüm **1.6.1** (7 Ekim 2026). Sürüm numarası **kodda, tek yerde**
durur:

```php
// config/app.php
'version' => '1.6.1',
```

`Panel → Sistem Bilgisi` sayfası bu değeri okur. Şablonu güncellediğinizde
numara kendiliğinden gelir; veritabanında ayrıca tutulmaz.

### Sürüm geçmişi

| Sürüm | Tarih | Öne çıkanlar |
|---|---|---|
| [1.6.1](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.6.1) | 2026-10-07 | Form yardım metinleri ve karakter sayacı; e-posta değişikliği, hesap silme, yeni üye ve hesap açılışı bildirimleri; duyuru tercihi; koyu temada WCAG AA kontrast; ana sayfa menüyle aynı hizada; demo parolaları yalnızca demo modunda; SQL dosyasıyla kurulum (`database/ornek-veritabani.sql`) |
| [1.6.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.6.0) | 2026-10-07 | Parola sıfırlama; mobil uygulama oturum API'si; KVKK araçları ve hesap silme; iki ekranlı kurulum; panelden yönetilen ana sayfa; tablolar mobilde kart; CI'da MySQL 8 ile rol matrisi |
| [1.5.1](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.5.1) | 2026-10-07 | Veritabanı performansı: sık çalışan sorgular için eksik indeksler; giriş denemeleri temizliğinin kilitlenmesi giderildi |
| [1.5.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.5.0) | 2026-10-07 | Demo modu (tek tıkla giriş, örnek hesaplara kilit); panelden modül aç/kapa; modüllerin rollere yetki dağıtması ve onay akışlı RBAC örnek modülü; sade panel tasarımı |
| [1.4.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.4.0) | 2026-10-06 | İkinci güvenlik incelemesi: boşluklu/Türkçe klasörde ve Redis oturumunda giriş, kuyrukta kilitlenme, kayıtta e-posta doğrulaması, parola değişince düşen API anahtarları, hizmet engellemeye dayanıklı IP kilidi |
| [1.3.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.3.0) | 2026-10-06 | Kapsamlı güvenlik incelemesi: kurulum sihirbazı ele geçirme, açık yönlendirme, kaba kuvvet kilidi, oturum sürümü; REST API anahtarları, birim ve duman testleri, GitHub Actions |
| [1.2.1](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.2.1) | 2026-09-05 | Takma ad (vitrin) adresinden servis edilen kurulumda 404 ve yönlendirme döngüsü düzeltildi |
| [1.2.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.2.0) | 2026-09-04 | 12 ekran görüntüsü ve Canlı Demo bölümü |
| [1.1.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.1.0) | 2026-08-30 | Mobil düzen baştan elden geçirildi, sürüm numarası tek kaynağa indi |
| [1.0.0](https://github.com/CilginYazilim/cy-php-starter/releases/tag/v1.0.0) | 2026-08-18 | İlk kararlı sürüm |

Her sürümün ayrıntısı:
[CHANGELOG.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/CHANGELOG.md).

### Güncelleme (1.6.0 → 1.6.1)

1. Kodu çekin, `php cy migrate` çalıştırın (SSH yoksa panelde **"Şimdi çalıştır"**).
   İki migration: ayar açıklamaları ve bildirimler ("Yeni Üye Bildirimi"
   ayarı, `kullanicilar.bildirim_tercihleri` sütunu). Değerlerinize dokunmaz.
2. Geliştirme modunda (`APP_DEBUG=true`) giriş ekranı artık demo parolalarını
   listelemez; hızlı giriş yalnızca `APP_DEMO=true` iken görünür.
3. "Örnek veriyi kaldır" artık örnek marka ayarlarını da (slogan, sosyal
   hesaplar…) nötre döndürür; sizin değiştirdiklerinize dokunmaz.

### Güncelleme (1.5.x → 1.6.0)

1. Yedek alın (veritabanı + `.env`), kodu çekin.
2. `php cy migrate` — SSH yoksa panelde çıkan **"Şimdi çalıştır"**. Tek
   migration: yeni ayarlar (Ana Sayfa grubu, KVKK, parola sıfırlama, hesap
   silme), parola sıfırlama tablosu, API anahtarı kapsam sütunları ve
   "Gizlilik ve KVKK" sayfası. Var olan ayarlarınıza dokunmaz.
3. Ana sayfanız eskisi gibi kalır; yeni bölümleri **Panel → Ayarlar → Ana
   Sayfa**'dan açın. Örnek vitrini görmek isterseniz: `php cy db:seed`.
4. Var olan API anahtarları "okuma + yazma" kapsamında çalışmaya devam eder.

### Güncelleme (1.5.0 → 1.5.1)

Kodu çektikten sonra `php cy migrate` çalıştırın (SSH yoksa panelde
çıkan **"1 migration bekliyor → Şimdi çalıştır"**). Yalnızca indeks
ekler, veriye dokunmaz.

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
