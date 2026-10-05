/* =====================================================================
 *  SERVİS ÇALIŞANI (Service Worker)
 * ---------------------------------------------------------------------
 *  Bu dosya PROJE KÖKÜNDE durmak ZORUNDADIR. Bir servis çalışanı
 *  yalnızca bulunduğu klasörü ve altını kontrol edebilir ("scope");
 *  assets/js/ altına koysaydık paneli kapsayamazdı.
 *
 *  STRATEJİ
 *    Statik dosyalar (css/js/görsel) → önce ÖNBELLEK, yoksa ağ
 *    Herkese açık HTML sayfaları     → önce AĞ, olmazsa önbellek
 *    Panel, giriş, kayıt, çıkış      → YALNIZCA AĞ (asla önbelleğe girmez)
 *    POST ve API istekleri           → HİÇ dokunulmaz
 *
 *  HTML'de neden önce ağ? Panelde bayat veri göstermek, biraz daha
 *  yavaş açılmaktan çok daha kötüdür. Kullanıcı silinmiş bir kaydı
 *  ya da eski bir bakiyeyi görmemelidir.
 *
 *  KİŞİSEL SAYFALAR ÖNBELLEĞE GİRMEZ. Eski sürüm panel sayfalarını da
 *  önbelleğe yazıyordu: kullanıcı çıkış yaptıktan sonra aynı cihazı
 *  kullanan biri, ağ yokken /panel adresini açıp yönetici ekranının
 *  HTML'ini görebiliyordu. Artık iki kilit var:
 *    1. Yol kontrolü: panel/, giris, kayit, cikis hiç önbelleğe alınmaz.
 *    2. Sunucu işareti: giriş yapmış kullanıcıya üretilen HER yanıt
 *       "X-CY-Onbellek: hayir" başlığı taşır (bkz. index.php); bu
 *       başlığı gören yanıt da önbelleğe yazılmaz.
 *
 *  SÜRÜM DEĞİŞİNCE ESKİ ÖNBELLEKLER SİLİNİR: "cy-v2"ye geçiş, eski
 *  sürümün önbelleğe yazmış olabileceği panel sayfalarını da temizler.
 *
 *  GET DIŞINDAKİ İSTEKLERE ASLA DOKUNMAYIZ: bir formu ya da API
 *  çağrısını önbellekten yanıtlamak veri kaybına yol açar.
 * ================================================================== */

const SURUM   = 'cy-v2';
const STATIK  = SURUM + '-statik';
const SAYFA   = SURUM + '-sayfa';

/* Servis çalışanının kapsamı (uygulamanın taban yolu, sonu "/"). */
const KAPSAM = new URL('./', self.location).pathname;

/* Kurulumda önbelleğe alınacak asgari sayfalar. Her biri AYRI eklenir:
 * cache.addAll() tek bir dosya 404 verdiğinde hepsini reddederdi. */
const ONCEDEN = ['./', './cevrimdisi'];

/* Önbelleğe ASLA girmeyecek rota önekleri (kapsama göre). */
const OZEL = ['panel', 'giris', 'kayit', 'cikis'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIK)
            .then((cache) => Promise.all(
                ONCEDEN.map((adres) => cache.add(adres).catch(() => undefined))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((adlar) => Promise.all(
                adlar
                    .filter((ad) => ad.startsWith('cy-') && !ad.startsWith(SURUM))
                    .map((ad) => caches.delete(ad))   // eski sürümleri temizle
            ))
            .then(() => self.clients.claim())
    );
});

/**
 * İsteğin rota yolu: temiz adreste kapsamdan sonraki kısım, eski
 * biçimde (index.php?r=...) "r" parametresi.
 */
function rotaYolu(url) {
    const r = url.searchParams.get('r');

    if (r !== null) {
        return r.replace(/^\/+/, '');
    }

    const yol = url.pathname.startsWith(KAPSAM) ? url.pathname.slice(KAPSAM.length) : url.pathname;

    return yol.replace(/^index\.php\/?/, '').replace(/^\/+/, '');
}

function ozelMi(url) {
    const yol = rotaYolu(url);

    return OZEL.some((onek) => yol === onek || yol.startsWith(onek + '/'));
}

function cevrimdisiSayfa() {
    return caches.match('./cevrimdisi').then((sayfa) => sayfa || caches.match('./'));
}

self.addEventListener('fetch', (event) => {
    const istek = event.request;

    // Yalnızca GET; POST/PUT/DELETE asla önbelleğe girmez.
    if (istek.method !== 'GET') { return; }

    const url = new URL(istek.url);

    // Başka bir alan adına giden istekleri karıştırmayız.
    if (url.origin !== self.location.origin) { return; }

    // API ve kurulum sihirbazı her zaman ağdan gelir.
    if (url.pathname.includes('/api/') || url.pathname.includes('/kurulum')) { return; }

    // Kişisel sayfalar: YALNIZCA AĞ. Ağ yoksa çevrimdışı sayfası.
    if (ozelMi(url)) {
        if (istek.mode === 'navigate') {
            event.respondWith(fetch(istek).catch(cevrimdisiSayfa));
        }

        return;
    }

    const statikMi = /\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?)$/i.test(url.pathname);

    if (statikMi) {
        // ÖNCE ÖNBELLEK: sürüm damgalı adresler (?v=…) zaten
        // değiştiğinde yeni bir adres üretir, bayatlama olmaz.
        event.respondWith(
            caches.match(istek).then((bulunan) => bulunan || fetch(istek).then((yanit) => {
                if (yanit && yanit.status === 200) {
                    const kopya = yanit.clone();
                    caches.open(STATIK).then((cache) => cache.put(istek, kopya));
                }

                return yanit;
            }))
        );

        return;
    }

    // ÖNCE AĞ: sayfa içeriği güncel olmalı.
    event.respondWith(
        fetch(istek)
            .then((yanit) => {
                const kisisel = yanit.headers.get('X-CY-Onbellek') === 'hayir';

                if (yanit && yanit.status === 200 && yanit.type === 'basic' && !kisisel) {
                    const kopya = yanit.clone();
                    caches.open(SAYFA).then((cache) => cache.put(istek, kopya));
                }

                return yanit;
            })
            .catch(() => caches.match(istek).then((bulunan) => bulunan || cevrimdisiSayfa()))
    );
});
