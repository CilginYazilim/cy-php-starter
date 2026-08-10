<?php
/**
 * =====================================================================
 *  CacheStore – Önbellek sürücülerinin sözleşmesi
 * ---------------------------------------------------------------------
 *  NEDEN BURADA ARAYÜZ VAR DA Storage'DA YOKTU?
 *  Storage'ın tek bir uygulaması vardı; tek uygulaması olan arayüz
 *  ölü soyutlamadır. Burada İLK GÜNDEN üç uygulama var (dosya,
 *  veritabanı, kapalı) ve dördüncüsü (Redis) somut bir ihtimal.
 *  Arayüz burada gerçek bir iş yapıyor: sürücüyü değiştirmek tek
 *  satırlık bir yapılandırma değişikliği.
 *
 *  SÖZLEŞMENİN KURALLARI
 *
 *  1. get() bulamazsa null döner. "null saklamak" ile "yok" ayrımı
 *     yapılmaz — bilerek. Bu ayrımı desteklemek her sürücüye ek
 *     karmaşıklık getirir ve pratikte neredeyse hiç gerekmez.
 *
 *  2. Süresi geçmiş bir kayıt YOK sayılır. Sürücü onu okurken
 *     silebilir (tembel temizlik) ama zorunlu değildir.
 *
 *  3. $seconds = 0 → süresiz saklama.
 *
 *  4. HİÇBİR METOT İSTİSNA FIRLATMAMALIDIR. Önbellek bir hızlandırma
 *     katmanıdır; disk dolduğu ya da Redis düştüğü için uygulamanın
 *     çökmesi kabul edilemez. Hata durumunda put() false döner,
 *     get() null döner — çağıran taraf veriyi kaynağından üretir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Cache;

interface CacheStore
{
    /** Sürücünün adı (tanılama ekranlarında görünür). */
    public function name(): string;

    /** Bulunamazsa ya da süresi geçmişse null. */
    public function get(string $key): mixed;

    public function has(string $key): bool;

    /** @param int $seconds 0 → süresiz */
    public function put(string $key, mixed $value, int $seconds = 0): bool;

    public function forget(string $key): bool;

    /** Bu önbelleğin TAMAMINI temizler. */
    public function flush(): bool;

    /**
     * Süresi geçmiş kayıtları siler.
     *
     * @return int Silinen kayıt sayısı
     */
    public function purgeExpired(): int;
}
