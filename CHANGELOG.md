# Değişiklik Günlüğü

Bu dosyanın biçimi [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/)
kalıbını izler ve proje [Semantic Versioning](https://semver.org/lang/tr/)
kurallarına uyar.

---

## [1.2.1] — 2026-09-05

### Düzeltildi

- **Vitrin adresinden açıldığında 404.** Uygulama, cilginyazilim.com
  kütüphanesinde `/kutuphane/uygulama/cy-php-starter/` adresinden de servis
  ediliyor; sunucu bu adresi gerçek klasöre içeriden bağlıyor. Böyle bir
  istekte `SCRIPT_NAME` gerçek klasörü, `REQUEST_URI` ise ziyaretçinin
  gördüğü adresi gösterir. Taban yolu yalnızca `SCRIPT_NAME`'den
  türetildiği için ikisi tutmuyor, rota `kutuphane/uygulama/cy-php-starter`
  diye okunuyor ve uygulama kendi "Sayfa bulunamadı" sayfasını basıyordu.

  `Url::base()` artık istek gerçek klasörün altından gelmiyorsa tabanı
  isteğin kendisinden türetiyor (`Url::aliasTaban()`). Rota doğru çözülüyor
  ve üretilen bütün adresler — `assets/`, form `action`'ları,
  yönlendirmeler — ziyaretçinin bulunduğu adreste kalıyor. İstekten gelen
  yol, `Url::current()` ile aynı dar karakter kümesine indiriliyor ve `..`
  içeren yol koşulsuz reddediliyor.

  `Url::absolute()` de buna uyduruldu: `APP_URL`'in yol kısmı aktif tabanla
  uyuşmuyorsa yalnızca alan adı kullanılıyor. Aksi hâlde iki yol üst üste
  binip `/demos/cy-php-starter/kutuphane/uygulama/...` gibi adresler
  çıkıyordu.

- **Vitrin adresinde eğik çizgi kırpılması.** `.htaccess`'teki "sondaki
  bölü işaretini temizle" kuralı vitrin üzerinden gelen isteklerde artık
  çalışmıyor. Site kökündeki kural o adrese eğik çizgiyi eklerken buradaki
  kural siliyor, ikisi karşılıklı 301 üretiyordu.

---

## [1.2.0] — 2026-09-04

### Eklendi

- **Ekran görüntüleri geri geldi — 12 kare.** `docs/screenshots/` klasörü
  boştu ve kendi README'sinde şu not duruyordu: *"Eski görseller şablonun
  prosedürel sürümüne aitti; artık ürünü doğru göstermiyorlardı."* Doğru
  bir karardı — yanlış bir ekran görüntüsü, hiç ekran görüntüsü
  olmamasından kötüdür. Ama bir başlangıç şablonunun en çok sorulan
  sorusu "kurulunca ne çıkıyor?" olduğu için o boşluk pahalıydı.

  Kareler, o README'nin kendi koyduğu kurallara göre çekildi: kurulum
  sihirbazı tamamlandıktan sonra, sihirbazın yüklediği örnek veriyle,
  1360 px genişlikte (mobil 390 px), hepsi 500 KB altında ve dosya adları
  klasörün belirlediği listeye birebir uygun. Kurulum ekranlarında gerçek
  şifre veya veritabanı bilgisi yoktur.

- **"Canlı Demo" bölümü eklendi.** README başlığının altına çalışan
  demoya / kaynak kütüphanesine / ZIP indirmeye giden üç düğme ve demoya
  bağlanan tıklanabilir bir panel önizlemesi kondu.

- **"Ekran Görüntüleri" bölümü eklendi.** Kurulum sihirbazının iki adımı,
  panelin dört ekranı, ön yüz, koyu tema ve mobil görünüm — her biri ne
  gösterdiğini anlatan bir alt yazıyla.

### Değiştirildi

- `docs/screenshots/README.md` artık klasörün boş olduğunu söylemiyor;
  hangi karenin ne gösterdiğini listeliyor. Görsellerin ne zaman
  yenileneceğine dair kural korundu.

---

> Bu sürümden öncesi ayrı bir günlükte tutulmuyordu. Daha eski
> değişiklikler için depo geçmişine ve `SISTEM.md` dosyasına
> bakabilirsiniz.
