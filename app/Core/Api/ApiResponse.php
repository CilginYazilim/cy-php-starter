<?php
/**
 * =====================================================================
 *  ApiResponse – REST yanıtlarının TEK biçimi
 * ---------------------------------------------------------------------
 *  Her yanıt aynı zarfa girer; istemci tarafında "bu uç nasıl cevap
 *  veriyordu?" diye bakmak gerekmez:
 *
 *      { "success": true,  "data": {...}, "meta": {...} }
 *      { "success": false, "error": { "code": "...", "message": "...",
 *                                     "fields": {...} } }
 *
 *  NEDEN PANELDEKİ ZARFTAN FARKLI?
 *  Panelin AJAX yanıtları ({success, type, description}) doğrudan
 *  ekranda bildirim göstermek içindir — insan okur. API ise başka bir
 *  PROGRAM tarafından tüketilir: makine okunur bir hata KODU ve alan
 *  bazlı hata listesi ister. İkisini tek biçime zorlamak, ikisini de
 *  bozardı.
 *
 *  HTTP DURUM KODU DA ANLAMLIDIR: gövdedeki "success" alanına ek
 *  olarak doğru kodu döndürürüz (200/201/401/403/404/422/429/500).
 *  Bazı istemciler yalnızca duruma bakar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core\Api;

final class ApiResponse
{
    /**
     * Başarılı yanıt.
     *
     * @param mixed                $data
     * @param array<string,mixed>  $meta Sayfalama, toplam sayı vb.
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): never
    {
        $payload = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        self::send($payload, $status);
    }

    public static function created(mixed $data = null): never
    {
        self::success($data, [], 201);
    }

    /** Gövdesiz başarı (silme işlemleri). */
    public static function noContent(): never
    {
        self::send(['success' => true, 'data' => null], 200);
    }

    /**
     * Hata yanıtı.
     *
     * @param string               $code    Makine okunur kod: "gecersiz_veri"
     * @param array<string,string> $fields  Alan bazlı doğrulama hataları
     */
    public static function error(
        string $message,
        int $status = 400,
        string $code = '',
        array $fields = [],
    ): never {
        $error = [
            'code'    => $code !== '' ? $code : self::defaultCode($status),
            'message' => $message,
        ];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        self::send(['success' => false, 'error' => $error], $status);
    }

    /** @param array<string,string> $fields */
    public static function validationFailed(array $fields, string $message = 'Gönderilen bilgiler geçerli değil.'): never
    {
        self::error($message, 422, 'gecersiz_veri', $fields);
    }

    public static function unauthorized(string $message = 'Geçerli bir erişim anahtarı gerekli.'): never
    {
        self::error($message, 401, 'yetkisiz');
    }

    public static function forbidden(string $message = 'Bu işlem için yetkiniz yok.'): never
    {
        self::error($message, 403, 'yasak');
    }

    public static function notFound(string $message = 'Kayıt bulunamadı.'): never
    {
        self::error($message, 404, 'bulunamadi');
    }

    /**
     * Sayfalanmış liste.
     *
     * @param array<int,mixed> $items
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): never
    {
        $lastPage = $perPage > 0 ? (int) max(1, ceil($total / $perPage)) : 1;

        self::success($items, [
            'toplam'      => $total,
            'sayfa'       => $page,
            'sayfa_boyut' => $perPage,
            'son_sayfa'   => $lastPage,
            'var_sonraki' => $page < $lastPage,
        ]);
    }

    /* =================================================================
     *  GÖNDERİM
     * ============================================================== */

    /** @param array<string,mixed> $payload */
    private static function send(array $payload, int $status): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function defaultCode(int $status): string
    {
        return match ($status) {
            400     => 'gecersiz_istek',
            401     => 'yetkisiz',
            403     => 'yasak',
            404     => 'bulunamadi',
            405     => 'gecersiz_yontem',
            422     => 'gecersiz_veri',
            429     => 'cok_fazla_istek',
            default => 'sunucu_hatasi',
        };
    }
}
