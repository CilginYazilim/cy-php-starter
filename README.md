<div align="center">

# Çılgın Yazılım – PHP Başlangıç Şablonu

### Her yeni örnek projeye buradan başlayın

**Tasarım kalıbı + güvenlik iskeleti + AJAX altyapısı hazır. Siz sadece işin özüne odaklanın.**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.2-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](LICENSE)

[cilginyazilim.com](https://cilginyazilim.com)

</div>

---

## Bu Şablon Nedir?

Her yeni PHP örneği yazarken aynı şeyleri tekrar kurmak zorunda kalmayın diye hazırlandı. İçinde şunlar **çalışır halde** gelir:

| Hazır gelen | Açıklama |
|-------------|----------|
| 🎨 **Tasarım kalıbı** | `cilginyazilim.css` — marka renkleri, kart, buton, tablo, modal, toast, koyu tema |
| 🔒 **CSRF koruması** | Token üretimi ve `hash_equals` ile sabit zamanlı doğrulama |
| 🛡️ **Güvenli PDO kurulumu** | `EMULATE_PREPARES=false`, exception modu, utf8mb4 |
| 📤 **Güvenli dosya yükleme** | Tür içerikten doğrulanır, dosya adı sunucuda üretilir, `.htaccess` korumalı klasör |
| 🔀 **AJAX yönlendirici** | Tek uç nokta, `action` tabanlı, tek noktada hata yakalama |
| 🧩 **Hazır CRUD örneği** | `list`/`add`/`edit`/`fetch`/`delete` — yorum satırından çıkarıp tablo adını değiştirmeniz yeterli |
| 🧙 **Kurulum sihirbazı** | `install.php` — veritabanını oluşturur, şemayı içe aktarır, `.env` dosyasını yazar; `config.php`'yi elle düzenlemeye gerek kalmaz |
| ✅ **Doğrulama fonksiyonları** | Metin, ad, e-posta, ID |
| 📦 **Yerel kütüphaneler** | jQuery, Bootstrap 5, DataTables — CDN yok, çevrimdışı çalışır |
| 📄 **Belge taslakları** | README taslağı, `.gitignore`, `.env.example`, MIT lisansı |

> **Bağımlılık yok.** Composer yok, npm yok. Klonla, çalıştır.

---

## Ekran Görüntüleri

### Şablon açılış ekranı

Kurulumdan hemen sonra karşınıza çıkan sayfa: bağlantı testi butonu ve tasarım kalıbındaki hazır bileşenlerin galerisi. Yeni projeye başlarken galeriyi silip yerine kendi içeriğinizi yazarsınız.

![Şablon açılış ekranı](docs/screenshots/01-sablon.png)

### Kurulum sihirbazı

`install.php` — sistem kontrolleri, uygulama adı ve veritabanı bilgileri için tek bir form. "Kurulumu Başlat" butonuna basınca veritabanını oluşturur, `database.sql`'i içe aktarır ve `.env` dosyasını yazar.

![Kurulum sihirbazı](docs/screenshots/02-kurulum.png)

---

## Hızlı Başlangıç

```bash
# 1) Şablondan yeni proje oluştur (GitHub'da "Use this template" butonu)
git clone https://github.com/CilginYazilim/cy-php-starter.git yeni-projem
cd yeni-projem

# 2) Şablonun git geçmişini temizle, kendi geçmişini başlat
rm -rf .git
git init

# 3) Çalıştır
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/` adresini açın. Veritabanı henüz yoksa
otomatik olarak kurulum sihirbazına yönlendirilirsiniz — orada
**"Kurulumu Başlat"** butonuna basmanız yeterli, veritabanı ve şema
sizin için oluşturulur.

> **Elle kurulum tercih ederseniz:** `install.php`'yi hiç açmadan
> `mysql -u root -p < database.sql` ile veritabanını kendiniz
> oluşturabilir, `.env.example` dosyasını `.env` olarak kopyalayıp
> elle doldurabilirsiniz. İkisi de aynı sonuca gider.

---

## Yeni Proje Kontrol Listesi

Şablonu kopyaladıktan sonra sırayla:

- [ ] **Kurulum sihirbazını çalıştırın** (`install.php`) veya `.env.example`'ı `.env` olarak kopyalayıp elle doldurun — `APP_NAME` ve `DB_NAME` en az değişmesi gerekenler
- [ ] **`database.sql`** → `items` tablosunu kendi yapınızla değiştirin (dosyadaki veritabanı adı önemli değil, sihirbaz zaten görmezden gelir)
- [ ] **`index.php`** → "BİLEŞEN GALERİSİ" bölümünü silin, kendi içeriğinizi yazın
- [ ] **`system/ajax.php`** → tam CRUD lazımsa dosyanın alt kısmındaki yorumlu `handle_list/save/fetch/delete` bloğunu açıp `items` yerine kendi tablonuzu yazın; farklı bir şey lazımsa kendi `case`'lerinizi ekleyin. `ping`'i sonunda silin
- [ ] **`system/function.php`** → CRUD bloğunu açtıysanız oradaki `find_item()` örneğini de yorumdan çıkarın; farklı sorgular için "PROJEYE ÖZEL" bölümüne yazın
- [ ] **`assets/css/style.css`** → projeye özel stiller (⚠️ `cilginyazilim.css`'e dokunmayın)
- [ ] **`README.md`** → `docs/README-taslak.md` dosyasını buraya kopyalayıp doldurun
- [ ] **`docs/screenshots/`** → ekran görüntülerini ekleyin
- [ ] **`.gitignore`** → örnek görselleri paylaşacaksanız `upload/*` satırını yorumlayın
- [ ] **Canlıya çıkmadan önce `install.php`'yi silin** (bkz. [Canlıya Alırken](#canlıya-alırken))

---

## Dosya Yapısı

```
.
├── index.php                  # Başlangıç sayfası + bileşen galerisi
├── install.php                # Kurulum sihirbazı (canlıya çıkmadan önce silin)
├── database.sql               # Veritabanı şablonu
├── .env.example                # Elle kurulum için ortam değişkeni şablonu
├── README.md                  # Bu dosya (yeni projede değiştirin)
├── LICENSE                    # MIT
├── .gitignore                 # .env dahil, hassas dosyaları hariç tutar
│
├── docs/
│   ├── README-taslak.md       # Yeni proje için README taslağı
│   └── screenshots/           # Ekran görüntüleri
│
├── system/
│   ├── config.php             # .env'i okur, PDO bağlantısını kurar
│   ├── function.php           # Yardımcı fonksiyonlar
│   └── ajax.php               # AJAX yönlendirici
│
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   ├── dataTables.bootstrap5.min.css
│   │   ├── cilginyazilim.css  # ⚠️ MARKA KALIBI — değiştirmeyin
│   │   └── style.css          # ◄ Projeye özel stiller buraya
│   ├── js/
│   │   ├── jquery-3.7.0.js
│   │   ├── bootstrap.bundle.js
│   │   ├── jquery.dataTables.min.js
│   │   └── dataTables.bootstrap5.min.js
│   └── images/
│       └── logo.png
│
└── upload/
    └── .htaccess              # Klasörde kod çalıştırmayı engeller
```

**Yükleme sırası önemlidir:**

```
CSS:  bootstrap → dataTables → cilginyazilim → style
JS:   jQuery → bootstrap.bundle → dataTables → dataTables.bootstrap5
```

---

## Tasarım Kalıbı Bileşenleri

`<body class="cy-app">` yazdıktan sonra kullanabileceğiniz sınıflar:

| Sınıf | Ne işe yarar |
|-------|--------------|
| `.cy-card` / `__header` / `__body` / `__footer` | Gradyan başlıklı ana kart |
| `.cy-brand` / `.cy-brand__mark` | Logo + başlık bloğu |
| `.cy-btn` + `--primary` \| `--onbrand` \| `--glass` | Marka butonları |
| `.cy-btn-icon` + `--view` \| `--edit` \| `--delete` | Tablo içi ikon butonları |
| `.cy-actions` | İkon butonlarını yan yana dizer |
| `.cy-table` | Marka görünümlü tablo |
| `.cy-avatar` / `--initial` / `--lg` | Profil görseli ve baş harf rozeti |
| `.cy-badge` + `--glass` \| `--soft` | Rozetler |
| `.cy-modal` | Gradyan başlıklı modal |
| `.cy-detail` | Etiket/değer listesi |
| `.cy-toast` + `--success` \| `--danger` \| `--info` | Bildirim balonları |
| `.cy-muted` / `.cy-nowrap` / `.cy-footer-note` | Yardımcı sınıflar |

### Renk değiştirmek

Tüm bileşenler CSS değişkenlerinden beslenir. `style.css` içine yazmanız yeterli:

```css
:root {
    --cy-brand-600: #0b5cb5;   /* Ana marka rengi */
    --cy-accent:    #0ea5e9;   /* Vurgu rengi     */
}
```

### Koyu tema

Ziyaretçinin işletim sistemi koyu temadaysa **otomatik** devreye girer.
Zorlamak için: `<html data-cy-theme="dark">` (veya `"light"`).

---

## Altyapıyı Kullanma

### Hazır CRUD örneğini açmak (en hızlı yol)

`system/ajax.php` dosyasının alt kısmında, `handle_ping()`'den sonra tek büyük
yorum bloğu içinde **tam çalışan** bir CRUD örneği durur: `handle_list()`
(DataTables server-side listeleme), `handle_save()` (ekle+düzenle ortak),
`handle_fetch()` (tek kayıt) ve `handle_delete()`. Bu blok
[php-not-listesi-ornegi](https://github.com/CilginYazilim/php-not-listesi-ornegi)
ve [PHP PDO MySQL Ajax CRUD](https://github.com/CilginYazilim/PHP-PDO-MySQL-Ajax-CRUD-DataTables-Bootstrap-5-Modals)
projelerindeki güvenlik mantığıyla birebirdir; sadece jenerik bir `items`
tablosu üzerinden yazılmıştır.

Açmak için üç adım:

1. `system/ajax.php` içindeki `/* ... */` yorum işaretlerini kaldırın
   (blok, `handle_list()`'ten `handle_delete()`'in kapanışına kadar sürer)
2. `system/function.php`'nin en altındaki `find_item()` örneğini de
   yorumdan çıkarın — CRUD bloğu bu fonksiyonu çağırır
3. `switch` bloğundaki hazır case'lerin yorumunu kaldırın:
   ```php
   case 'list':   handle_list($db);   break;
   case 'add':
   case 'edit':   handle_save($db, $action); break;
   case 'fetch':  handle_fetch($db);  break;
   case 'delete': handle_delete($db); break;
   ```

Sonra `items` tablo adını ve `title`/`description` sütunlarını kendi
şemanıza göre değiştirin (`database.sql`'de de aynı isimleri kullanın).
Görsel yükleme kullanmıyorsanız `handle_save()` içindeki `$hasNewImage`
bloğunu silin.

> Her iki blok da (CRUD ve `find_item()`) tek bir yorum içinde yazıldığı
> için içlerine **iç içe** `/* */` eklemeyin — C-stili yorumlar iç içe
> geçemez, ilk `*/` tüm bloğu erken kapatır ve dosya bozulur.

### Yeni bir AJAX işlemi eklemek

**1.** `system/ajax.php` içindeki `switch` bloğuna ekleyin:

```php
case 'kaydet':
    handle_kaydet($db);
    break;
```

**2.** Fonksiyonu yazın:

```php
function handle_kaydet(PDO $db): void
{
    require_csrf();   // ◄ Veri değiştiren her işlemde İLK SATIR

    [$baslik, $hata] = validate_text($_POST['baslik'] ?? '', 'Başlık');

    if ($hata !== null) {
        json_error('Lütfen formdaki hataları düzeltin.', 422, [
            'errors' => ['baslik' => $hata],
        ]);
    }

    $stmt = $db->prepare('INSERT INTO items (title) VALUES (:title)');
    $stmt->execute([':title' => $baslik]);

    json_success('Kayıt eklendi.', ['id' => (int) $db->lastInsertId()]);
}
```

**3.** JavaScript'ten çağırın:

```js
$.ajax({
    url: 'system/ajax.php',
    method: 'POST',
    dataType: 'json',
    data: { action: 'kaydet', baslik: 'Deneme', csrf_token: CSRF_TOKEN }
})
.done(function (res) { notify(res.description, 'success'); })
.fail(function (xhr) { notify((xhr.responseJSON || {}).description, 'danger'); });
```

### HTTP durum kodları

| Kod | Anlamı |
|-----|--------|
| `200` | Başarılı |
| `400` | Geçersiz parametre |
| `404` | Bulunamadı |
| `405` | POST dışı istek |
| `419` | CSRF geçersiz / oturum düştü |
| `422` | Doğrulama hatası (`errors` alanı döner) |
| `500` | Sunucu hatası |

---

## Altın Kurallar

Bu şablonla çalışırken asla atlamayın:

1. **Veri değiştiren her işlemin ilk satırı `require_csrf()` olsun.**
2. **Veritabanından gelen her değeri `e()` ile kaçışlayın.** HTML'e ham basmayın.
3. **Sorgulara değer yapıştırmayın.** Her zaman prepared statement kullanın.
4. **Sütun/tablo adı bind edilemez.** Sıralama için beyaz liste yazın.
5. **`cilginyazilim.css`'i değiştirmeyin.** Özelleştirmeleri `style.css`'e yazın.
6. **Canlıya çıkarken `APP_DEBUG = false` yapın.**
7. **Şifreyi koda yazmayın.** Ortam değişkeni kullanın.

---

## Canlıya Alırken

- [ ] **`install.php` dosyasını silin** (veya sunucu düzeyinde erişimini kısıtlayın) — çalışır durumda kalırsa herkes veritabanınızı yeniden yapılandırabilir
- [ ] `APP_DEBUG` → `false` (`.env` içinde `APP_DEBUG=false`)
- [ ] `root` yerine sınırlı yetkili veritabanı kullanıcısı
- [ ] Kimlik bilgileri ortam değişkeninden
- [ ] HTTPS + `session.cookie_secure = 1`, `session.cookie_httponly = 1`
- [ ] Nginx kullanıyorsanız `.htaccess` çalışmaz:
  ```nginx
  location ^~ /upload/ {
      location ~ \.php$ { deny all; }
  }
  ```

---

## Örnek Projeler

Bu şablonla üretilen örnekler:

- [PHP PDO MySQL Ajax CRUD](https://github.com/CilginYazilim/PHP-PDO-MySQL-Ajax-CRUD-DataTables-Bootstrap-5-Modals) — DataTables, modallar, dosya yükleme
- [Basit Not Listesi](https://github.com/CilginYazilim/php-not-listesi-ornegi) — küçük ölçekli, öğretici bir liste+ekleme örneği

---

## Lisans

[MIT](LICENSE) — ticari kullanım dahil serbesttir.

<div align="center">

**[cilginyazilim.com](https://cilginyazilim.com)**

</div>
