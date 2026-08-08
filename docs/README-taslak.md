<!--
  =====================================================================
   README TASLAĞI
   Bu dosyayı proje köküne README.md olarak kopyalayın ve
   {{ }} içindeki yerleri doldurun.

     cp docs/README-taslak.md README.md

   Not: Bu yorum bloğunu silmeyi unutmayın.
  =====================================================================
-->

<div align="center">

# {{PROJE ADI}}

### {{Kısa alt başlık — hangi teknolojiler? örn. PHP PDO · Bootstrap 5 · AJAX}}

**{{Tek cümlelik tanıtım: bu proje neyi gösteriyor?}}**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.2-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](LICENSE)

[cilginyazilim.com](https://cilginyazilim.com)

</div>

---

## Bu Proje Nedir?

{{2-3 cümle. Hangi problemi çözüyor, kimin işine yarar?}}

**Kimler için uygun?**

- {{Hedef kitle 1}}
- {{Hedef kitle 2}}

> **Klonla, `database.sql`'i içe aktar, çalıştır.** Composer yok, npm yok, internet bağlantısı bile gerekmiyor.

---

## İçindekiler

- [Ekran Görüntüleri](#ekran-görüntüleri)
- [Özellikler](#özellikler)
- [Kurulum](#kurulum)
- [Yapılandırma](#yapılandırma)
- [Dosya Yapısı](#dosya-yapısı)
- [Nasıl Çalışıyor?](#nasıl-çalışıyor)
- [Sorun Giderme](#sorun-giderme)
- [Katkı](#katkı)
- [Lisans](#lisans)

---

## Ekran Görüntüleri

### {{Ekran adı}}

{{Bu ekranda ne görülüyor, tek cümle.}}

![{{Ekran adı}}](docs/screenshots/01-{{ad}}.png)

---

## Özellikler

- {{Özellik 1}}
- {{Özellik 2}}
- {{Özellik 3}}

**Güvenlik önlemleri:** CSRF token'ı, prepared statement, çıktı kaçışlama (XSS), güvenli dosya yükleme, korumalı yükleme klasörü.

---

## Kurulum

### Gereksinimler

- PHP **8.0+** (`pdo_mysql` eklentisi)
- MySQL **5.7+** veya MariaDB **10.3+**
- Apache (XAMPP / WAMP / Laragon) veya PHP yerleşik sunucusu

### Adımlar

```bash
# 1) Projeyi indirin
git clone https://github.com/CilginYazilim/{{REPO-ADI}}.git
cd {{REPO-ADI}}

# 2) Veritabanını oluşturun (dosya veritabanını da kendisi oluşturur)
mysql -u root -p < database.sql

# 3) Çalıştırın
php -S 127.0.0.1:8000
```

Tarayıcıda `http://127.0.0.1:8000/` adresini açın.

XAMPP kullanıyorsanız projeyi `htdocs` altına koyup
`http://localhost/{{REPO-ADI}}/` adresine gidin.

> **Linux/macOS:** `upload/` klasörüne yazma izni gerekir → `chmod 755 upload`

---

## Yapılandırma

Tüm ayarlar [system/config.php](system/config.php) içindedir:

| Sabit | Varsayılan | Açıklama |
|-------|-----------|----------|
| `DB_HOST` | `127.0.0.1` | Veritabanı sunucusu |
| `DB_NAME` | `{{veritabani_adi}}` | Veritabanı adı |
| `DB_USER` | `root` | Kullanıcı adı |
| `DB_PASS` | *(boş)* | Parola |
| `APP_DEBUG` | `true` | **Canlıda `false` yapın** |

Şifreyi koda yazmamak için ortam değişkeni kullanabilirsiniz:

```bash
export DB_USER=uygulama DB_PASS='guclu-sifre'
```

---

## Dosya Yapısı

```
.
├── index.php              # Arayüz + JavaScript
├── database.sql           # Veritabanı şeması
├── system/
│   ├── config.php         # Ayarlar ve PDO bağlantısı
│   ├── function.php       # Yardımcı fonksiyonlar
│   └── ajax.php           # AJAX uç noktası
├── assets/                # CSS, JS, görseller
├── docs/screenshots/      # Ekran görüntüleri
└── upload/                # Yüklenen dosyalar
```

---

## Nasıl Çalışıyor?

{{Kısa akış açıklaması. Örnek:}}

```
Tarayıcı (index.php)
   │  AJAX ── action + csrf_token ──►  system/ajax.php
   │                                        │
   │                                        ├─ require_csrf()
   │                                        ├─ doğrulama
   │                                        └─ PDO prepared query ──► MySQL
   ◄────────────── JSON yanıt ──────────────┘
```

| Dosya | Görevi |
|-------|--------|
| [index.php](index.php) | Sunum katmanı. Veritabanına dokunmaz. |
| [system/ajax.php](system/ajax.php) | Yönlendirici + güvenlik kontrolleri. |
| [system/function.php](system/function.php) | Yardımcı fonksiyonlar. |
| [system/config.php](system/config.php) | Ayarlar ve PDO. |

---

## Sorun Giderme

| Belirti | Çözüm |
|---------|-------|
| **"Veritabanına bağlanılamadı"** | MySQL çalışmıyor veya `DB_*` bilgileri hatalı. |
| **HTTP 419 hatası** | Oturum düşmüş — sayfayı yenileyin. |
| **`$ is not defined`** | JavaScript yükleme sırası bozulmuş; jQuery en başta olmalı. |
| **Türkçe karakterler bozuk** | Veritabanı utf8mb4 değil. |

---

## Katkı

Katkılar memnuniyetle karşılanır.

📦 **Depo:** [github.com/CilginYazilim/{{REPO-ADI}}](https://github.com/CilginYazilim/{{REPO-ADI}})

| Nasıl katkı sağlarım? | Nereden |
|----------------------|---------|
| 🐛 Hata bildir | [Issues](https://github.com/CilginYazilim/{{REPO-ADI}}/issues) |
| 💡 Özellik öner | [Issues](https://github.com/CilginYazilim/{{REPO-ADI}}/issues) |
| 🔧 Kod gönder | [Pull Requests](https://github.com/CilginYazilim/{{REPO-ADI}}/pulls) |

```bash
git checkout -b ozellik/yeni-ozellik
git commit -m "Yeni özellik: ..."
git push origin ozellik/yeni-ozellik
```

**Katkı ölçütleri:** Kod açıklamalı olsun, güvenlik kontrollerini atlamayın, tasarım değişikliklerini `style.css` üzerinden yapın.

---

## Lisans

[MIT](LICENSE) — ticari kullanım dahil serbesttir.

<div align="center">

**[cilginyazilim.com](https://cilginyazilim.com)** tarafından ❤ ile geliştirildi

Faydalı bulduysanız ⭐ vermeyi unutmayın.

</div>
