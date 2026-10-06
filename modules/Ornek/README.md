# Örnek Modül — kendi modülünüzü yazmanın çalışan şablonu

Bu klasör, CY PHP Starter'a yeni bir özellik (Stok, Randevu, Blog,
Destek Talebi…) eklerken **kopyalayacağınız** eksiksiz bir örnektir.
Kurulumda açık gelir; panelde **Modüller → Örnek Modül** altında
çalışır hâlde görürsünüz.

Tek klasörde şunları gösterir:

- **CRUD:** listeleme, ekleme, düzenleme, durum değiştirme ve silme
- **Onay akışı:** üye yazar, editör onaylar, sonra yayına çıkar
- **İki katmanlı RBAC:** rol yetkisi (`module.json`) ve kayıt düzeyi kural (`OrnekPolicy`)
- **Migration ve seeder:** tablo, sütun ekleme ve kurulumda örnek veri
- **Repository:** SQL'in tek yeri; her sorgu hazırlıklı (prepared)
- Çekirdeğe **hiç dokunmadan** menü, rota, yetki ve görünüm ekleme

---

## Dosya yapısı

```
modules/Ornek/
├─ module.json                  künye, menü, rol yetkileri, kurulumda açık mı
├─ routes.php                   adresler + can:… yetki kapıları
├─ migrations/
│  ├─ 2026_08_10_062032_ornek_tablosu.php        CREATE TABLE
│  └─ 2026_10_06_100000_ornek_sahip_ve_durum.php  sütun ekleme (idempotent)
├─ seeders/OrnekIcerik.php      kurulumda 12 rastgele kayıt
├─ src/
│  ├─ OrnekPolicy.php           "bu kullanıcı BU kayda ne yapabilir?"
│  ├─ Repositories/OrnekRepository.php   SQL burada, başka yerde değil
│  └─ Controllers/OrnekController.php    liste, ekle, düzenle, durum, sil
└─ views/index.php              ekran ($this->view('Ornek::index'))
```

`src/` altındaki sınıflar `Modules\Ornek\…` ad alanındadır ve modül
açıkken kendiliğinden yüklenir; `composer` gerekmez.

---

## Bir isteğin yolculuğu

```
POST /panel/ornek/durum/15   (durum=yayinda)
  │
  ├─ routes.php      → 'csrf' ve 'can:ornek.update.own|ornek.publish|ornek.manage'
  │                     Rolün bu işe HİÇ yetkisi yoksa burada 403.
  ├─ OrnekController::status()
  │    ├─ visibleRecord()     kaydı göremiyorsa 404 (varlığı da gizlenir)
  │    ├─ OrnekPolicy::canMoveTo($kayit, 'yayinda')
  │    │                       bu KAYDI bu duruma taşıyamıyorsa 403
  │    └─ OrnekRepository::setStatus()
  └─ Flash mesajı + yönlendirme
```

Düğmeyi gizlemek güvenlik değildir: görünüm de denetleyici de **aynı**
`OrnekPolicy` sınıfına sorar. Adres çubuğundan elle gönderilen istek
de aynı kapıdan geçer.

---

## Roller ve yetkiler

Yönetici her yetkiye sahiptir. Editör ve üyenin yetkileri
`module.json` dosyasındadır; çekirdeğin `app/Models/Role.php`
dosyasına dokunulmaz:

```json
"yetkiler": {
    "editor": ["ornek.view", "ornek.create", "ornek.update.own", "ornek.publish"],
    "uye":    ["ornek.view", "ornek.create", "ornek.update.own"]
}
```

| Yetki | Anlamı | Yönetici | Editör | Üye |
|---|---|:-:|:-:|:-:|
| `ornek.view` | Yayındaki kayıtları görmek | ✓ | ✓ | ✓ |
| `ornek.create` | Kayıt eklemek | ✓ | ✓ | ✓ |
| `ornek.update.own` | Kendi kaydını düzenlemek, silmek, onaya göndermek | ✓ | ✓ | ✓ |
| `ornek.publish` | Onay bekleyenleri görmek ve yayınlamak | ✓ | ✓ | — |
| `ornek.manage` | Herkesin kaydını düzenlemek ve silmek, örnek üretmek | ✓ | — | — |

Modül kapatılınca yetkileri de kimseye geçmez.

## Onay akışı

```
taslak ──(sahibi: onaya gönder)──▶ onay ──(editör: onayla)──▶ yayinda
   ▲                                 │                          │
   └────(sahibi: geri çek)───────────┘◀───(yayından kaldır)─────┘
```

- **Üye** yayına alamaz; formunda "Yayında" seçeneği hiç yoktur. Elle
  `durum=yayinda` gönderirse doğrulamadan geçemez.
- Üye **yayındaki** kaydını düzenlerse kayıt yeniden onaya düşer:
  onaylanmamış metin yayında kalmaz.
- **Editör** onay bekleyenleri ve kendi kayıtlarını görür, başkasının
  taslağını göremez. Kaldırdığı kayıt taslağa değil onay sırasına
  döner; böylece onu kaybetmez.
- Herkes yalnızca **kendi** kaydını düzenler ve siler; yönetici hariç.

Kurallar `src/OrnekPolicy.php` içindedir. Testleri `tests/unit.php` →
"Örnek modül: kayıt düzeyi kurallar" bölümündedir.

---

## Kendi modülünüzü yazmak — adım adım

Örnek: kullanıcıların randevu aldığı, yöneticinin onayladığı bir
**Randevu** modülü.

**1. İskeleti üretin**

```bash
php cy make:module Randevu
```

`modules/Randevu/` altında `module.json`, `routes.php`, bir migration,
denetleyici, repository ve görünüm oluşur.

**2. Yetkileri dağıtın** — `module.json`:

```json
"yetkiler": {
    "editor": ["randevu.view", "randevu.publish"],
    "uye":    ["randevu.view", "randevu.create", "randevu.update.own"]
},
"menu": { "route": "panel/randevu", "icon": "calendar", "label": "Randevular", "can": "randevu.view" }
```

Yetki adı küçük harfle ve noktalı yazılır (`randevu.update.own`).
Geçersiz adlar sessizce atlanır.

**3. Tabloyu yazın** — `migrations/` altındaki dosyada `up()` ve
`down()`. Sahiplik için bir `kullanici_id`, durum için bir `durum`
sütunu ekleyin. Kurulumu bozmamak için yabancı anahtar (FK) yerine
dizin (index) kullanın; Örnek Modülün ikinci migration'ı
idempotent sütun eklemeyi gösterir.

**4. Kuralları tek sınıfta toplayın** — `OrnekPolicy.php`'yi
`RandevuPolicy.php` olarak kopyalayın: `scope()` (kim neyi görür),
`canEdit()` (kim düzenler), `transitions()` (kim hangi duruma taşır).
Denetleyici ve görünüm yalnızca bu sınıfa sorsun.

**5. Rotalara kapı koyun** — her rotada `can:…` ara katmanı, her POST'ta
`csrf`. Birden fazla yetkiden biri yetiyorsa `can:a|b`.

**6. Açın**

- Panelde **Sistem Bilgisi → Modüller** bölümündeki düğmeyle açın.
  Tabloları da o anda kurulur.
- Ya da komut satırından: `php cy module --enable=Randevu`, ardından
  `php cy migrate`.

**7. (İsteğe bağlı) Örnek veri** — `seeders/` altına bir dosya koyun;
kurulum sihirbazında "Örnek verileri de yükle" seçilince ya da
`php cy db:seed` ile çalışır.

---

## Bu modülü kaldırmak

Örneğe ihtiyacınız kalmayınca **Sistem Bilgisi → Modüller**'den
kapatın. Kapatmak hiçbir veriyi silmez; menüsü, sayfaları ve yetkileri
devreden çıkar. Tamamen kaldırmak için:

```bash
php cy migrate:rollback   # yalnızca son parti Ornek'e aitse
# ya da elle: DROP TABLE ornek;
```

Ardından `modules/Ornek` klasörünü silin.

---

Ana belge: [README.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/README.md) ·
Ayrıntılı başvuru: [SISTEM.md](https://github.com/CilginYazilim/cy-php-starter/blob/main/SISTEM.md)
