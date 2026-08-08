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
| ✅ **Doğrulama fonksiyonları** | Metin, ad, e-posta, ID |
| 📦 **Yerel kütüphaneler** | jQuery, Bootstrap 5, DataTables — CDN yok, çevrimdışı çalışır |
| 📄 **Belge taslakları** | README taslağı, `.gitignore`, MIT lisansı |

> **Bağımlılık yok.** Composer yok, npm yok. Klonla, çalıştır.

---

## Şablon Açılış Ekranı

Kurulumdan hemen sonra karşınıza çıkan sayfa: bağlantı testi butonu ve tasarım kalıbındaki hazır bileşenlerin galerisi. Yeni projeye başlarken galeriyi silip yerine kendi içeriğinizi yazarsınız.

![Şablon açılış ekranı](docs/screenshots/01-sablon.png)

---

## Hızlı Başlangıç

```bash
# 1) Şablondan yeni proje oluştur (GitHub'da "Use this template" butonu)
git clone https://github.com/CilginYazilim/cy-php-starter.git yeni-projem
cd yeni-projem

# 2) Şablonun git geçmişini temizle, kendi geçmişini başlat
rm -rf .git
git init

# 3) Veritabanını oluştur
mysql -u root -p < database.sql

# 4) Çalıştır
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/` adresini açın ve **"Bağlantıyı Test Et"** butonuna basın.
Yeşil bildirim görüyorsanız her şey hazır: AJAX, CSRF ve veritabanı çalışıyor.

---

## Yeni Proje Kontrol Listesi

Şablonu kopyaladıktan sonra sırayla:

- [ ] **`system/config.php`** → `APP_NAME`, `APP_DESCRIPTION` ve `DB_NAME` değerlerini değiştirin
- [ ] **`database.sql`** → `yeni_proje` adını ve `items` tablosunu kendi yapınızla değiştirin
- [ ] **`index.php`** → "BİLEŞEN GALERİSİ" bölümünü silin, kendi içeriğinizi yazın
- [ ] **`system/ajax.php`** → `switch` bloğuna kendi `case`'lerinizi ekleyin, `ping`'i silin
- [ ] **`system/function.php`** → en alttaki "PROJEYE ÖZEL" bölümüne sorgularınızı yazın
- [ ] **`assets/css/style.css`** → projeye özel stiller (⚠️ `cilginyazilim.css`'e dokunmayın)
- [ ] **`README.md`** → `docs/README-taslak.md` dosyasını buraya kopyalayıp doldurun
- [ ] **`docs/screenshots/`** → ekran görüntülerini ekleyin
- [ ] **`.gitignore`** → örnek görselleri paylaşacaksanız `upload/*` satırını yorumlayın

---

## Dosya Yapısı

```
.
├── index.php                  # Başlangıç sayfası + bileşen galerisi
├── database.sql               # Veritabanı şablonu
├── README.md                  # Bu dosya (yeni projede değiştirin)
├── LICENSE                    # MIT
├── .gitignore
│
├── docs/
│   ├── README-taslak.md       # Yeni proje için README taslağı
│   └── screenshots/           # Ekran görüntüleri
│
├── system/
│   ├── config.php             # ◄ İLK BURAYI DEĞİŞTİRİN
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

- [ ] `APP_DEBUG` → `false`
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

---

## Lisans

[MIT](LICENSE) — ticari kullanım dahil serbesttir.

<div align="center">

**[cilginyazilim.com](https://cilginyazilim.com)**

</div>
