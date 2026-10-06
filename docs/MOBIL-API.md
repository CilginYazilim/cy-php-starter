# Mobil uygulama için REST API

CY PHP Starter, bir mobil uygulamanın ilk gün ihtiyaç duyduğu uçlarla gelir:
kullanıcı adı ve parolayla giriş, oturumu saklama, çıkış, açık cihazları
görme ve sunucudaki dosyaları listeleyip açma. Bütün uçlar `/api/v1`
altındadır ve aynı yanıt zarfını kullanır.

```json
{ "success": true,  "data": { … }, "meta": { … } }
{ "success": false, "error": { "code": "giris_basarisiz", "message": "…", "fields": { … } } }
```

Tarihler ISO 8601 biçimindedir (`2026-10-07T14:05:00+03:00`). Gövde form
(`application/x-www-form-urlencoded`) ya da JSON (`application/json`) olabilir.

## Akış

1. Uygulama `POST /api/v1/oturum` ile giriş yapar ve dönen `token` değerini
   cihazın güvenli deposunda saklar (Android Keystore, iOS Keychain).
2. Sonraki her istekte `Authorization: Bearer <token>` başlığını gönderir.
3. Kullanıcı çıkış yaptığında `DELETE /api/v1/oturum` çağrılır ve token silinir.
4. `401` yanıtı gelirse token geçersizdir (süresi doldu, parola değişti ya da
   kullanıcı cihazı panelden kapattı): uygulama giriş ekranına döner.

## Uçlar

| Yöntem | Adres | Kimlik | Açıklama |
|---|---|---|---|
| `POST` | `/api/v1/oturum` | yok | Giriş: `kullanici`, `parola`, `cihaz` → `token` |
| `DELETE` | `/api/v1/oturum` | Bearer | Bu cihazdan çıkış (token iptal edilir) |
| `GET` | `/api/v1/ben` | Bearer | Giriş yapan kullanıcı, token kapsamı ve türü |
| `GET` | `/api/v1/oturumlar` | Bearer | Açık mobil oturumlar; bu cihaz `bu_cihaz: true` |
| `DELETE` | `/api/v1/oturumlar/{id}` | Bearer | Başka bir cihazdaki oturumu kapatır |
| `GET` | `/api/v1/dosyalar` | Bearer | Örnek dosyaların listesi |
| `GET` | `/api/v1/dosyalar/{ad}` | Bearer | Dosyanın kendisi (`?indir=1` ile ek olarak) |
| `GET` | `/api/v1/sayfalar` | yok | Yayındaki içerik sayfaları |
| `GET` | `/api/v1/sayfalar/{slug}` | yok | Tek sayfanın içeriği (HTML) |
| `GET` | `/api/v1/kullanicilar` | Bearer + `users.view` | Kullanıcı listesi (sayfalı) |

### Giriş

```bash
curl -X POST https://site.com/api/v1/oturum \
     -H "Content-Type: application/json" \
     -d '{"kullanici":"mehmet.uye","parola":"Demo1234!","cihaz":"Pixel 8"}'
```

```json
{
  "success": true,
  "data": {
    "token": "cy_3f9a…",
    "token_turu": "Bearer",
    "oturum_id": 12,
    "son_gecerlilik": "2026-11-06T14:05:00+03:00",
    "silme_iptal": false,
    "kullanici": {
      "id": 3, "ad": "Mehmet", "soyad": "Kaya", "ad_soyad": "Mehmet Kaya",
      "kullanici_adi": "mehmet.uye", "eposta": "mehmet.demo@ornek.com",
      "rol": "uye", "rol_adi": "Üye", "avatar": ""
    }
  }
}
```

Hatalı bilgi `401 giris_basarisiz`, eksik alan `422 gecersiz_veri` döner.
Çok sayıda hatalı deneme hesabı ve IP'yi tarayıcı girişindeki gibi geçici
olarak kilitler: mobil giriş, panel girişiyle **aynı** kaba kuvvet
korumasından geçer (`Auth::verifyCredentials`).

`silme_iptal: true` ise kullanıcının bekleyen "hesabımı sil" isteği bu girişle
iptal edilmiştir; uygulama bunu bir bildirimle gösterebilir.

### Dosyalar

```bash
curl -H "Authorization: Bearer cy_3f9a…" https://site.com/api/v1/dosyalar
```

```json
{
  "success": true,
  "data": [
    { "ad": "ornek-gorsel.png", "tur": "image/png", "boyut": 24811,
      "degisti": "2026-10-07T02:29:10+03:00",
      "adres": "https://site.com/api/v1/dosyalar/ornek-gorsel.png" }
  ],
  "meta": { "toplam": 3 }
}
```

Dosyalar `storage/files/ornek/` klasöründedir; web'den doğrudan erişilemez,
yalnızca token ile bu uçtan verilir. Örnek veriyle kurulumda üç dosya gelir
(metin, Markdown, PNG). Kendi uygulamanızda klasörü ya da sahiplik kuralını
`App\Http\Controllers\Api\MobileController` içinde değiştirin.

## Güvenlik

- **Token veritabanında düz metin durmaz**, yalnızca SHA-256 özeti saklanır.
  Açık hâli yalnızca giriş yanıtında bir kez döner.
- **Ömür:** `.env` içindeki `API_SESSION_DAYS` (varsayılan 30 gün). Bir
  kullanıcı aynı anda en fazla 20 mobil oturum açabilir; daha fazlasında en eski
  kapanır.
- **Kapatılma:** kullanıcı Panel → Hesabım → *Bağlı cihazlar* bölümünden tek
  tek kapatabilir. Parola değişince, hesap pasife alınınca ya da "Diğer
  cihazlardan çıkış" (API anahtarlarıyla birlikte) seçilince hepsi kapanır.
- **Kapsam:** mobil oturum `yazma` kapsamındadır, yani kullanıcının rolünün
  izin verdiği her şeyi yapabilir; rolünden fazlasını asla. Panelden üretilen
  "yalnız okuma" anahtarları `GET` dışındaki isteklerde `403 kapsam_yetersiz` alır.
- **Hız sınırı:** anahtar başına dakikada 120 istek (`API_RATE_LIMIT`).
  Yanıtta `X-RateLimit-Limit` ve `X-RateLimit-Remaining` başlıkları gelir.
- **Bakım modu:** API de `503 bakim` döner; bakımı aşma yetkisi olan
  kullanıcının token'ı çalışmaya devam eder.

## İstemci örnekleri

### JavaScript (fetch)

```js
const API = 'https://site.com/api/v1';

async function girisYap(kullanici, parola) {
  const yanit = await fetch(`${API}/oturum`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ kullanici, parola, cihaz: navigator.userAgent.slice(0, 100) }),
  });
  const json = await yanit.json();
  if (!json.success) throw new Error(json.error.message);
  return json.data.token;            // güvenli depoya yazın
}

async function dosyalar(token) {
  const yanit = await fetch(`${API}/dosyalar`, { headers: { Authorization: `Bearer ${token}` } });
  if (yanit.status === 401) { /* giriş ekranına dön */ }
  return (await yanit.json()).data;
}
```

### Flutter (http paketi)

```dart
final yanit = await http.post(
  Uri.parse('https://site.com/api/v1/oturum'),
  headers: {'Content-Type': 'application/json'},
  body: jsonEncode({'kullanici': kullanici, 'parola': parola, 'cihaz': 'Flutter'}),
);
final veri = jsonDecode(yanit.body);
final token = veri['data']['token'];   // flutter_secure_storage ile saklayın

final liste = await http.get(
  Uri.parse('https://site.com/api/v1/dosyalar'),
  headers: {'Authorization': 'Bearer $token'},
);
```

## Kendi ucunuzu eklemek

`routes/web.php` içindeki `api/v1` grubuna bir satır ekleyin; yetki gerekiyorsa
`api.can:` ara katmanını kullanın:

```php
$r->get('siparisler', SiparisApi::class, 'index', ['api', 'api.can:siparis.view']);
```

Denetleyicide `Auth::user()` token'ın sahibini verir; yanıtı
`ApiResponse::success($veri)` ile döndürün. Örnek: `app/Http/Controllers/Api/V1Controller.php`.

---

[CY PHP Starter](https://github.com/CilginYazilim/cy-php-starter) · [ÇILGIN Yazılım](https://cilginyazilim.com/kutuphane/php-baslangic-sablonu)
