# Sıfırdan Kurulum Test Raporu

**Tarih:** 10 Ağustos 2026
**Ortam:** Windows 10 · XAMPP · Apache 2.4.58 · PHP 8.2.12 · MariaDB
**Yöntem:** Veritabanı silindi, proje klasörü tamamen boşaltıldı, depodan
yeniden klonlandı ve kurulum sihirbazı **gerçek bir kullanıcı gibi**
adım adım tarayıcı istekleriyle tamamlandı.

---

## 1. Özet

| | |
|---|---|
| Kurulum sonucu | **Başarılı** — 21 SQL ifadesi + 7 migration, hatasız |
| Kurulum süresi | 4 adım, hiçbir adımda takılma yok |
| Test edilen sayfa | 22 (ön yüz + panel) |
| PHP uyarısı / hatası | **0** |
| Bulunan hata | 5 (hepsi düzeltildi) |
| Açık kalan konu | 3 (aşağıda "İleriye dönük" başlığında) |

Kurulum akışı **pürüzsüz**: gereksinim ekranı sekiz maddeyi de yeşil
işaretledi, veritabanı adımı bağlantıyı önceden sınadı, site adresi
otomatik dolduruldu ve son adımda sihirbaz kendini sildi.

---

## 2. Kurulum deneyimi — adım adım

### Adım 1 · Gereksinimler
Sekiz kontrolün tamamı geçti (PHP 8.2.12, `pdo_mysql`, `mbstring`, yazma
izinleri, `kurulum/` silinebilirliği). **Yorum:** Bu ekranın en değerli
tarafı, "yazma izni" ve "klasör silinebilir mi" gibi kurulumun *sonunda*
patlayacak sorunları *başında* göstermesi.

### Adım 2 · Veritabanı
Form gönderilmeden önce sunucuya gerçekten bağlanıyor. Yanlış parola
girildiğinde MySQL'in kendi hata metni gösteriliyor — "bağlanılamadı"
gibi kör bir mesaj yerine sebebi söylemesi doğru tercih.

### Adım 3 · Site ayarları
Site adresi `http://localhost/personel-yonetimi` olarak **otomatik
algılandı**. Kullanıcının elle yazması gereken tek şey site adı.

### Adım 4 · Yönetici + kurulum
Parola politikası zorlandı, hesap oluşturuldu, `.env` yazıldı ve
migration'lar komut satırına hiç ihtiyaç duymadan çalıştı. Örnek veri
kutusu işaretlendi; 4 demo kullanıcı ve 2 demo mesaj yüklendi.

### Adım 5 · Temizlik
"Kurulum Klasörünü Sil ve Bitir" düğmesi klasörü gerçekten sildi ve
panelin **Güvenlik Denetimi** listesi bunu anında "kurulum klasörü
kaldırıldı ✓" olarak gösterdi.

---

## 3. Bulunan hatalar ve düzeltmeleri

### 3.1 · Kritik — 419 durum kodu Apache'de 500'e dönüşüyordu

**Belirti:** CSRF anahtarı geçersiz bir form gönderildiğinde tarayıcı
**500 Sunucu Hatası** alıyordu. Sayfanın *gövdesi* doğruydu ("Oturum
Süresi Doldu") ama durum satırı 500 diyordu.

**Kök neden:** 419 ("Page Expired") **IANA'da kayıtlı bir HTTP kodu
değildir** — Laravel'in icadıdır. Apache tanımadığı bir durum kodu
gördüğünde yanıtı 500'e çevirir. Doğrulandı:

```
istenen=403 -> gelen=403      istenen=419 -> gelen=500
istenen=429 -> gelen=429      istenen=440 -> gelen=500
```

**Asıl zarar arayüzdeydi:** panelin AJAX katmanı 401/419 gördüğünde
"oturumun düşmüş, sayfayı tazeliyorum" davranışını tetikliyordu. 500
görünce bunu yapamıyor, kullanıcıya "Bir hata oluştu" deyip bırakıyordu.
Yani oturumu düşen bir yönetici, panelin neden çalışmadığını anlayamıyor
ve elle F5'e basmayı akıl etmesi gerekiyordu.

**Düzeltme:** Uygulama içi kod ile *kablodaki* kod ayrıldı.
`HttpException::wireStatus()` 419'u standart karşılığı olan **403**'e
indirger; başlık, metin ve görünüm hâlâ "Oturum Süresi Doldu" der. JSON
yanıtına `expired: true` bayrağı eklendi ve `app.js` artık durum kodu
yerine bu bayrağa bakıyor.

### 3.2 · Sol menüdeki logo hiçbir zaman değişmiyordu

**Belirti:** Panelden yeni logo yükleniyor, ön yüzde değişiyor, sol
menüde değişmiyordu.

**Kök neden:** `admin.css` içinde
`.cy-sidebar__logo img { filter: brightness(0) invert(1); }`. Bu kural
hangi görsel yüklenirse yüklensin onu **bembeyaz bir siliyete** çeviriyor,
arkasındaki marka gradyanı da her logoyu aynı gösteriyordu.

**Düzeltme:** Süzgeç ve gradyan kaldırıldı; logo olduğu gibi, yumuşak bir
zemin üzerinde gösteriliyor. Yükleme sonrası doğrulandı — sol menü artık
`upload/img/logo/…` adresini kullanıyor.

### 3.3 · Ön yüzün marka renkleri hiç tanımlı değildi

**Belirti:** Ana sayfa "soluk" görünüyordu.

**Kök neden:** `site-sections.css` altı ayrı yerde `var(--cy-brand)` ve
`var(--cy-brand-soft)` kullanıyordu ama **bu iki token hiçbir yerde
tanımlı değildi**. Hero'nun arka plan lekesi, özellik ikonlarının zemini,
iç sayfa başlığı ve "alt" bölümler sessizce renksiz kalıyordu.

**Düzeltme:** `cilginyazilim.css` içinde `--cy-brand: var(--cy-brand-600)`
ve `--cy-brand-soft: var(--cy-brand-50)` takma adları tanımlandı. `var()`
zinciri sayesinde koyu tema da kendiliğinden doğru çalışıyor.

### 3.4 · Sosyal medya ikonlarının markaya özel çizimleri yoktu

**Belirti:** Alt bilgide GitHub dışındaki tüm platformlar aynı "dünya"
ikonuyla görünüyordu.

**Kök neden:** `icon()` yardımcısında yalnızca `github` tanımlıydı;
diğerleri `globe`'a düşüyordu.

**Düzeltme:** Facebook, X, Instagram, LinkedIn, YouTube ve WhatsApp için
dolu (fill) marka yolları eklendi. Marka ikonları ayrı bir listede tutulur
çünkü arayüz ikonlarından farklı çizilirler (dolu vs. çizgi).

### 3.5 · Sayfa güncelleme PDO hatasıyla çöküyordu

**Belirti:** `PageRepository::update()` → *"Invalid parameter number"*.

**Kök neden:** Ortak `bind()` metodu `:yazar_id` parametresini de
üretiyordu ama UPDATE sorgusunda o sütun yoktu.

**Düzeltme:** `update()` artık bu parametreyi ayıklıyor — yazar, sayfayı
ilk oluşturan kişi olarak kalıyor (her düzenlemede değişmesi de zaten
istenmez).

### 3.6 · Kurulum sonrası hiçbir onay mesajı yoktu

**Belirti:** "Kurulum Klasörünü Sil ve Bitir" düğmesine basıldığında
sıradan bir ana sayfaya düşülüyordu; klasörün silinip silinmediği belirsizdi.

**Düzeltme:** `index.php` artık `?kurulum=temizlendi` adresini tanıyor ve
tek seferlik bir bildirim gösteriyor. Klasör bir sebeple silinememişse
mesaj bunu da söylüyor ("lütfen elle silin").

---

## 4. Doğrulanan davranışlar

Aşağıdakilerin tamamı gerçek HTTP istekleriyle sınandı:

| Konu | Sonuç |
|---|---|
| Kayıt olma kurulumdan itibaren **açık** | ✔ `/kayit` → 200, menüde "Kayıt Ol" görünüyor |
| Yeni üye kaydı ve otomatik giriş | ✔ üye yalnızca "Kontrol Paneli" ve "Profilim" görüyor |
| **Beni hatırla** | ✔ yalnızca `cy_remember` çerezi ile `/panel` açıldı |
| Çıkışta jetonun iptali | ✔ çıkıştan sonra aynı çerez `/giris`'e yönlendi, sütun `NULL` |
| Oturum açıkken `/giris` ve `/kayit` | ✔ ikisi de `/panel`'e yönlendiriyor |
| İletişim formu | ✔ mesaj kaydedildi, 2 `.eml` üretildi (bildirim + otomatik yanıt) |
| Zengin metin süzgeci | ✔ `<script>` silindi, `javascript:` bağlantısı elendi, Türkçe karakterler korundu |
| Sayfa oluştur / düzenle / sil | ✔ üçü de çalışıyor; slug Türkçe başlıktan doğru üretildi |
| Korumalı sayfa silme denemesi | ✔ engellendi, sayfa yerinde kaldı |
| Menü / alt bilgi / sitemap | ✔ yeni sayfa üçünde de kendiliğinden belirdi |
| Logo yükleme | ✔ ön yüz **ve** sol menü güncellendi |
| Favicon yükleme | ✔ kare kırpıldı (200×200), her iki düzende de kullanılıyor |
| WhatsApp düğmesi | ✔ `wa.me/905415090583?text=…` hazır mesajla |
| Bakım modu | ✔ ziyaretçi 503, iletişim ucu kapalı, yönetici siteyi görüyor |
| Hata sayfaları | ✔ 404, 403, 419(→403) doğru başlık ve metinle |
| Yetki denetimi | ✔ üye `/panel/kullanicilar` → 403 |
| Yapılandırma denetimi | ✔ 7 maddeden 2'si eksik olarak işaretlendi (SMTP + logo/favicon) |

**Panelde ve ön yüzde toplam 22 sayfa tarandı; tek bir PHP uyarısı,
"Undefined" hatası ya da bozuk bağlantı çıkmadı.**

---

## 5. Talep edilen düzenlemelerin durumu

| İstek | Durum |
|---|---|
| Kayıt ilk kurulumda açık gelsin | ✔ |
| Girişte "Beni hatırla" + oturum yönlendirmesi | ✔ |
| Ana sayfa tasarımı iyileştirilsin | ✔ marka renkleri düzeltildi, hero kartı, üst etiketler, WhatsApp çağrısı |
| Alt bilgideki sosyal ikonlar bozuk | ✔ her platform kendi ikonu, hover'da kendi rengi |
| WhatsApp düğmesi + numara + hazır mesaj | ✔ alt bilgi, iletişim sayfası ve sağ alt köşede yüzen düğme |
| Hakkımızda sayfası oluşturulsun | ✔ **sayfa yönetim sistemi** olarak (tek sayfa değil, sınırsız sayfa) |
| İletişim formu SMTP uyarısı | ✔ form üstünde (yalnızca yöneticiye), Mesajlar ekranında ve Sistem denetiminde |
| Kullanıcılar: üstteki sayaç yazısı kalksın, kart gelsin | ✔ 4 istatistik kartı |
| "Yeni Kullanıcı" Filtreler'in yanına | ✔ |
| Mesajlar: aynı düzen + toplu düğmeler tablo başlığına | ✔ |
| Mesaj modalı geliştirilsin | ✔ gönderen şeridi, konu bloğu, okunur mesaj gövdesi |
| "Posta Programım" çalışmıyor | ✔ mailto artık konu + alıntı taşıyor; yanına "Adresi Kopyala" eklendi |
| E-posta/Sistem/Ayarlar sayfa başlıkları kalksın | ✔ tüm panel sayfalarında (üst çubukta zaten yazıyor) |
| Ayarlar bölüm düğmeleri kalksın | ✔ (sol menüde zaten var) |
| Ayarlar genel bakış geliştirilsin | ✔ 4 durum kartı + eksik bölümlerin adı |
| Hakkımızda Metni alanı kalksın | ✔ yerine sayfa sistemi |
| Site Adresi alanı kalksın | ✔ artık yalnızca `.env` → `APP_URL` |
| Logo Dosyası alanı kalksın | ✔ "dahili" gruba alındı, yükleme kartından yönetiliyor |
| Sol menü logosu değişmiyor | ✔ düzeltildi (bkz. 3.2) |
| Favicon alanı eklensin, varsayılanı gelsin | ✔ logonun 32px hâli kurulumla geliyor |
| İletişim ayarları geliştirilsin | ✔ WhatsApp, hazır mesaj, harita bağlantısı + iki bilgi kartı |
| SEO geliştirilsin, robots.txt yönetimi | ✔ başlık şablonu, Google doğrulama, paylaşım görseli, sitemap anahtarı, robots ek kuralları |
| Sistem sayfasında eksik ayarlar gösterilsin | ✔ "Yapılandırma Denetimi" — her madde çözüm bağlantılı |
| Dil alanı şimdilik kalsın | ✔ dokunulmadı |

---

## 6. İleriye dönük öneriler

Aşağıdakiler **hata değil**, bir sonraki turda konuşulacak fırsatlar.

### 6.1 · İletişim sayfası taslağa alınırsa kırık bağlantı kalır
Ana sayfadaki "Bize Ulaşın" düğmesi `/iletisim` adresine sabit bağlanıyor.
Sayfa taslağa alınırsa düğme 404'e gider. **Öneri:** düğmeleri de menü
gibi veritabanından beslemek ya da korumalı sayfaların taslağa alınmasını
engellemek.

### 6.2 · Zengin metin editörüne görsel yükleme
Şu anda görsel **adres yazarak** ekleniyor. Kullanıcının bilgisayarından
sürükle-bırak ile yükleme, `Uploader` altyapısı hazır olduğu için küçük
bir iş: bir AJAX ucu + editöre bir düğme.

### 6.3 · Sayfa sürüm geçmişi
İçerik sayfaları üzerine yazılıyor; yanlışlıkla silinen bir metin geri
alınamıyor. `sayfa_surumleri` gibi basit bir tablo, her kaydetmede eski
içeriği saklayarak bunu çözer.

### 6.4 · Dil paketleri
`site_dil` ayarı duruyor ama şu an yalnızca `<html lang>` özniteliğini
etkiliyor. Arayüz metinlerini bir dizi dosyasına taşımak (`lang/tr.php`)
ileride çok dilliliğin kapısını açar. **Bu turda bilerek yapılmadı.**

### 6.5 · E-posta ve Sistem ayarları
Kapsamlı düzenlemeleri bir sonraki tura bırakıldı (talebiniz üzerine).
Bu turda yalnızca eksik SMTP'nin **görünür** hale gelmesi sağlandı.

### 6.6 · Canlıya çıkmadan önce
Test ortamında bilinçli olarak açık bırakılanlar:

- `APP_DEBUG=true` → canlıda **false** yapın
- E-posta yöntemi hâlâ **"kayıt"** modunda → SMTP girin
- Demo kullanıcılar duruyor → `DELETE FROM kullanicilar WHERE eposta LIKE '%@ornek.com';`
- HTTPS yok (yerel) → canlıda SSL şart

Bu dördünü de panelin **Sistem Bilgisi** ekranı kendiliğinden uyarıyor.

---

## 7. Kurulum sonrası ortamın son hâli

```
Veritabanı : Personel_Yonetimi
Tablolar   : ayarlar(48) · kullanicilar(6) · sayfalar(3) · mesajlar(3)
             mail_kayitlari · isler · onbellek · api_anahtarlari
             login_attempts · migrasyonlar(7)
Yönetici   : admin · sreklamci@gmail.com
Sayfalar   : Hakkımızda · İletişim · Sıkça Sorulan Sorular (test amaçlı)
kurulum/   : silindi (depoda duruyor, yalnızca sunucudan kaldırıldı)
```

> **Not:** Test sırasında oluşturulan "Sıkça Sorulan Sorular" sayfası,
> yüklenen test logosu/faviconu ve test mesajı veritabanında duruyor.
> İstemezseniz panelden silebilirsiniz; hiçbiri depoya gönderilmedi.
