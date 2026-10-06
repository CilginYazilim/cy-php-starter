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
 *  KİŞİSEL SAYFALAR ÖNBELLEĞE GİRMEZ. Üç kilit var:
 *    1. Yol kontrolü: panel/, giris, kayit, cikis hiç önbelleğe alınmaz.
 *    2. Sunucu işareti: giriş yapmış kullanıcıya üretilen HER yanıt
 *       "X-CY-Onbellek: hayir" başlığı taşır (bkz. index.php); bu
 *       başlığı gören yanıt da önbelleğe yazılmaz.
 *    3. Kurulum anında önceden alınan sayfalar ÇEREZSİZ istenir. Eskiden
 *       ana sayfa kullanıcının çereziyle alınıyordu: servis çalışanı
 *       giriş yapılmışken kurulduysa önbelleğe GİRİŞLİ ana sayfa
 *       yazılıyor, çıkıştan sonra çevrimdışıyken o görünüyordu.
 *    Ayrıca çıkış isteği (POST cikis) geçerken sayfa önbelleği silinir.
 *
 *  ÖNBELLEK ADLARI KURULUMA ÖZELDİR. Ad, servis çalışanının kapsamını
 *  (uygulamanın yolu) içerir: aynı alan adındaki /demo1 ve /demo2
 *  birbirinin önbelleğini okumaz, sürüm değişince yalnızca KENDİ eski
 *  önbelleğini siler.
 *
 *  GET DIŞINDAKİ İSTEKLERE ASLA DOKUNMAYIZ: bir formu ya da API
 *  çağrısını önbellekten yanıtlamak veri kaybına yol açar.
 * ================================================================== */

const SURUM = 'cy-v3';

/* Servis çalışanının kapsamı (uygulamanın taban yolu, sonu "/"). */
const KAPSAM = new URL('./', self.location).pathname;

/* "cy-v3|/proje/|statik" — sürüm + kapsam + tür. */
const ON_EK  = SURUM + '|' + KAPSAM + '|';
const STATIK = ON_EK + 'statik';
const SAYFA  = ON_EK + 'sayfa';

/* Kurulumda önbelleğe alınacak asgari sayfalar. Her biri AYRI eklenir:
 * tek bir dosya 404 verdiğinde hepsi reddedilmesin. */
const ONCEDEN = ['./', './cevrimdisi'];

/* Önbelleğe ASLA girmeyecek rota önekleri (kapsama göre). */
const OZEL = ['panel', 'giris', 'kayit', 'cikis'];

/** Bu önbellek adı BU kurulumun eski bir sürümüne mi ait? */
function eskiSurumumuz(ad) {
    if (ad.startsWith(ON_EK)) {
        return false;
    }

    // Yeni biçim: "cy-vN|<kapsam>|tür" — yalnızca kendi kapsamımız.
    if (/^cy-v\d+\|/.test(ad)) {
        return ad.split('|')[1] === KAPSAM;
    }

    // 1.3 ve öncesinin kapsamsız adları ("cy-v2-sayfa"): bir kez temizlenir.
    return /^cy-v[12]-/.test(ad);
}

/** Yanıt kişisel mi? (giriş yapmış kullanıcıya üretilmiş) */
function kisiselMi(yanit) {
    return yanit.headers.get('X-CY-Onbellek') === 'hayir';
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIK)
            .then((cache) => Promise.all(ONCEDEN.map((adres) =>
                fetch(new Request(adres, { credentials: 'omit' }))
                    .then((yanit) => {
                        if (yanit.ok && !kisiselMi(yanit)) {
                            return cache.put(adres, yanit);
                        }

                        return undefined;
                    })
                    .catch(() => undefined)
            )))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((adlar) => Promise.all(
                adlar.filter(eskiSurumumuz).map((ad) => caches.delete(ad))
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
    return caches.open(STATIK)
        .then((cache) => cache.match('./cevrimdisi').then((sayfa) => sayfa || cache.match('./')));
}

self.addEventListener('fetch', (event) => {
    const istek = event.request;
    const url   = new URL(istek.url);

    // Başka bir alan adına giden istekleri karıştırmayız.
    if (url.origin !== self.location.origin) { return; }

    // Yalnızca GET; POST/PUT/DELETE asla önbelleğe girmez.
    if (istek.method !== 'GET') {
        /* Çıkışta bu cihazdaki sayfa önbelleği silinir: ortak bir
         * bilgisayarda sonraki kişi çevrimdışı modda önceki oturumdan
         * kalan hiçbir sayfayı görmesin. İsteğin kendisine dokunmayız. */
        if (rotaYolu(url) === 'cikis') {
            event.waitUntil(caches.delete(SAYFA));
        }

        return;
    }

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
            caches.open(STATIK).then((cache) => cache.match(istek).then((bulunan) => bulunan || fetch(istek).then((yanit) => {
                if (yanit && yanit.status === 200) {
                    cache.put(istek, yanit.clone());
                }

                return yanit;
            })))
        );

        return;
    }

    // ÖNCE AĞ: sayfa içeriği güncel olmalı.
    event.respondWith(
        fetch(istek)
            .then((yanit) => {
                if (yanit && yanit.status === 200 && yanit.type === 'basic' && !kisiselMi(yanit)) {
                    const kopya = yanit.clone();
                    caches.open(SAYFA).then((cache) => cache.put(istek, kopya));
                }

                return yanit;
            })
            .catch(() => caches.open(SAYFA)
                .then((cache) => cache.match(istek))
                .then((bulunan) => bulunan || cevrimdisiSayfa()))
    );
});
