/* =====================================================================
 *  SERVİS ÇALIŞANI (Service Worker)
 * ---------------------------------------------------------------------
 *  Bu dosya PROJE KÖKÜNDE durmak ZORUNDADIR. Bir servis çalışanı
 *  yalnızca bulunduğu klasörü ve altını kontrol edebilir ("scope");
 *  assets/js/ altına koysaydık paneli kapsayamazdı.
 *
 *  STRATEJİ
 *    Statik dosyalar (css/js/görsel) → önce ÖNBELLEK, yoksa ağ
 *    HTML sayfaları                  → önce AĞ, olmazsa önbellek
 *    POST ve API istekleri           → HİÇ dokunulmaz
 *
 *  HTML'de neden önce ağ? Panelde bayat veri göstermek, biraz daha
 *  yavaş açılmaktan çok daha kötüdür. Kullanıcı silinmiş bir kaydı
 *  ya da eski bir bakiyeyi görmemelidir.
 *
 *  GET DIŞINDAKİ İSTEKLERE ASLA DOKUNMAYIZ: bir formu ya da API
 *  çağrısını önbellekten yanıtlamak veri kaybına yol açar.
 * ================================================================== */

const SURUM   = 'cy-v1';
const STATIK  = SURUM + '-statik';
const SAYFA   = SURUM + '-sayfa';

/* Kurulumda önbelleğe alınacak asgari dosyalar. Liste kısa tutulur:
 * uzun bir liste, tek bir dosya 404 verdiğinde kurulumun tamamını
 * başarısız kılar. */
const ONCEDEN = ['./'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIK)
            .then((cache) => cache.addAll(ONCEDEN))
            .catch(() => undefined)      // biri düşerse kurulum yine tamamlansın
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((adlar) => Promise.all(
                adlar
                    .filter((ad) => !ad.startsWith(SURUM))
                    .map((ad) => caches.delete(ad))   // eski sürümleri temizle
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const istek = event.request;

    // Yalnızca GET; POST/PUT/DELETE asla önbelleğe girmez.
    if (istek.method !== 'GET') { return; }

    const url = new URL(istek.url);

    // Başka bir alan adına giden istekleri karıştırmayız.
    if (url.origin !== self.location.origin) { return; }

    // API ve kurulum sihirbazı her zaman ağdan gelir.
    if (url.pathname.includes('/api/') || url.pathname.includes('/kurulum')) { return; }

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
                if (yanit && yanit.status === 200 && yanit.type === 'basic') {
                    const kopya = yanit.clone();
                    caches.open(SAYFA).then((cache) => cache.put(istek, kopya));
                }

                return yanit;
            })
            .catch(() => caches.match(istek).then((bulunan) => bulunan || caches.match('./')))
    );
});
