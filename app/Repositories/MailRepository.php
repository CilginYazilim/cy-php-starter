<?php
/**
 * =====================================================================
 *  MailRepository – "mail_kayitlari" tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  Tablo iki işi birden görür:
 *
 *    1) GEÇMİŞ  – gönderilen her mektup kaydedilir. "Kullanıcı parola
 *       sıfırlama maili almadım" diyorsa panelden bakıp görebilirsiniz.
 *
 *    2) KUYRUK  – "kuyrukta" durumundaki satırlar henüz gönderilmemiş
 *       mektuplardır. Toplu duyurular buraya yazılır, sonra parti parti
 *       gönderilir. Böylece 500 kişilik bir duyuru tarayıcıyı
 *       kilitlemez, PHP zaman aşımına uğramaz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Mail\Mailable;
use App\Models\MailLog;
use PDO;

final class MailRepository
{
    /**
     * DataTables sütun sırası → SQL sütunu.
     * Anahtarlar views/mail/index.php'deki <th> sırasıyla eşleşir:
     * 0:Alıcı, 1:Konu, 2:Tür, 3:Durum, 4:Tarih, 5:İşlemler (sıralanamaz).
     *
     * @var array<int,string>
     */
    private const SORTABLE = [
        0 => 'alici_eposta',
        1 => 'konu',
        2 => 'tur',
        3 => 'durum',
        4 => 'created_at',
    ];

    public function __construct(private PDO $db)
    {
    }

    /* =================================================================
     *  YAZMA
     * ============================================================== */

    /**
     * Mektubu tabloya yazar ve kayıt numarasını döndürür.
     *
     * Kuyruk kayıtları TEK alıcı taşır: toplu gönderimde her alıcı
     * için ayrı satır açılır. Böylece biri başarısız olduğunda
     * yalnızca o satır "basarisiz" olur, diğerleri yoluna devam eder.
     */
    public function record(Mailable $mail, string $status = MailLog::DURUM_KUYRUKTA, string $batchId = ''): int
    {
        $ayrildi = $status === MailLog::DURUM_GONDERILIYOR;

        $recipient = $mail->recipients()[0] ?? ['', ''];
        $reply     = $mail->replyAddress();

        $stmt = $this->db->prepare(
            'INSERT INTO mail_kayitlari
                (alici_eposta, alici_ad, konu, govde, yanit_eposta, yanit_ad,
                 sablon, tur, durum, kullanici_id, gonderen_id, toplu_id, ayrildi_at)
             VALUES
                (:alici_eposta, :alici_ad, :konu, :govde, :yanit_eposta, :yanit_ad,
                 :sablon, :tur, :durum, :kullanici_id, :gonderen_id, :toplu_id, '
                 . ($ayrildi ? 'NOW()' : 'NULL') . ')'
        );

        $stmt->execute([
            ':alici_eposta' => mb_strtolower((string) $recipient[0]),
            ':alici_ad'     => $recipient[1] ?? '',
            ':konu'         => $mail->getSubject(),
            ':govde'        => $mail->getHtml(),
            ':yanit_eposta' => $reply[0] ?? '',
            ':yanit_ad'     => $reply[1] ?? '',
            ':sablon'       => $mail->getTemplate(),
            ':tur'          => $mail->getType(),
            ':durum'        => $status,
            ':kullanici_id' => $mail->getUserId(),
            ':gonderen_id'  => \App\Core\Auth::id(),
            ':toplu_id'     => $batchId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markSent(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE mail_kayitlari
                SET durum = 'gonderildi', hata = '', deneme = deneme + 1, gonderildi_at = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->db->prepare(
            "UPDATE mail_kayitlari
                SET durum = 'basarisiz', hata = :hata, deneme = deneme + 1
              WHERE id = :id"
        );

        // Hata metni sütuna sığmalı; SMTP yanıtları uzun olabiliyor.
        $stmt->execute([':hata' => mb_substr($error, 0, 250, 'UTF-8'), ':id' => $id]);
    }

    /** Başarısız bir kaydı yeniden kuyruğa alır. */
    public function requeue(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE mail_kayitlari SET durum = 'kuyrukta', hata = '', ayrildi_at = NULL
              WHERE id = :id AND durum NOT IN ('kuyrukta', 'gonderiliyor')"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM mail_kayitlari WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /** Belirtilen günden eski, GÖNDERİLMİŞ kayıtları siler. */
    public function purge(int $days = 90): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM mail_kayitlari
              WHERE durum = 'gonderildi'
                AND created_at < (NOW() - INTERVAL :gun DAY)"
        );
        $stmt->bindValue(':gun', max(1, $days), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /* =================================================================
     *  KUYRUK
     * ============================================================== */

    /**
     * Gönderilmeyi bekleyen kayıtlar (en eskiden yeniye).
     *
     * DİKKAT: Satırları KİLİTLEMEZ; yalnızca listelemek içindir.
     * Göndermek için claimPending() kullanın.
     *
     * @return array<int,array<string,mixed>>
     */
    public function pending(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $stmt = $this->db->query(
            "SELECT * FROM mail_kayitlari
              WHERE durum = 'kuyrukta'
              ORDER BY id ASC
              LIMIT " . $limit
        );

        return $stmt->fetchAll();
    }

    /**
     * Gönderilecek kayıtları ATOMİK olarak bu sürece ayırır.
     *
     * Her aday satır tek bir UPDATE ile "kuyrukta" → "gonderiliyor"
     * yapılır; rowCount() 1 dönmüyorsa satırı başka bir süreç (panel,
     * cron ya da mail:work) bizden önce almıştır ve atlanır. Eskiden
     * satırlar kilitlenmeden okunuyordu; üç süreç aynı anda çalışınca
     * bir duyuru aynı kişiye birkaç kez gidiyordu.
     *
     * @return array<int,array<string,mixed>> Yalnızca BU sürecin aldığı satırlar
     */
    public function claimPending(int $limit = 10): array
    {
        $this->releaseStuck();

        $claim = $this->db->prepare(
            "UPDATE mail_kayitlari
                SET durum = 'gonderiliyor', ayrildi_at = NOW()
              WHERE id = :id AND durum = 'kuyrukta'"
        );

        $read = $this->db->prepare('SELECT * FROM mail_kayitlari WHERE id = :id');

        $rows = [];

        foreach ($this->pending($limit) as $candidate) {
            $claim->execute([':id' => (int) $candidate['id']]);

            if ($claim->rowCount() !== 1) {
                continue;
            }

            $read->execute([':id' => (int) $candidate['id']]);
            $row = $read->fetch();

            if ($row !== false) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * "gonderiliyor"da takılı kalmış kayıtları (süreç çöktü) BAŞARISIZ
     * olarak işaretler.
     *
     * NEDEN KUYRUĞA GERİ DEĞİL? Süreç SMTP'ye mektubu teslim ettikten
     * hemen sonra, durumu yazamadan çökmüş olabilir. Satırı kuyruğa
     * döndürmek o kişiye ikinci bir kopya göndermek demektir. Başarısız
     * kayıt panelde görünür; yönetici gerekiyorsa tek tıkla yeniden
     * kuyruğa alır.
     */
    public function releaseStuck(int $seconds = 900): int
    {
        $stmt = $this->db->prepare(
            "UPDATE mail_kayitlari
                SET durum = 'basarisiz', hata = 'Gönderim yarıda kaldı (süreç zaman aşımına uğradı). Ulaşıp ulaşmadığı bilinmiyor.'
              WHERE durum = 'gonderiliyor'
                AND ayrildi_at IS NOT NULL
                AND ayrildi_at < (NOW() - INTERVAL :saniye SECOND)"
        );
        $stmt->bindValue(':saniye', max(60, $seconds), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Bu adrese son $hours saatte bu türde mektup gitti mi?
     *
     * Otomatik yanıtın aynı adrese tekrar tekrar gönderilmesini
     * (iletişim formunu spam rölesi olarak kullanma) engellemek için.
     */
    public function sentRecentlyTo(string $email, string $type, int $hours = 24): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM mail_kayitlari
              WHERE alici_eposta = :eposta AND tur = :tur
                AND created_at >= (NOW() - INTERVAL :saat HOUR)"
        );
        $stmt->bindValue(':eposta', mb_strtolower($email));
        $stmt->bindValue(':tur', $type);
        $stmt->bindValue(':saat', max(1, $hours), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function countPending(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM mail_kayitlari WHERE durum = 'kuyrukta'")->fetchColumn();
    }

    /**
     * Tablodaki satırı yeniden gönderilebilir bir Mailable'a çevirir.
     *
     * @param array<string,mixed> $row
     */
    public function toMailable(array $row): Mailable
    {
        $mail = Mailable::make()
            ->to((string) $row['alici_eposta'], (string) ($row['alici_ad'] ?? ''))
            ->subject((string) $row['konu'])
            ->html((string) ($row['govde'] ?? ''))
            ->template((string) ($row['sablon'] ?? 'genel'))
            ->type((string) ($row['tur'] ?? 'bildirim'))
            ->forUser(isset($row['kullanici_id']) ? (int) $row['kullanici_id'] : null);

        if (($row['yanit_eposta'] ?? '') !== '') {
            $mail->replyTo((string) $row['yanit_eposta'], (string) ($row['yanit_ad'] ?? ''));
        }

        return $mail;
    }

    /* =================================================================
     *  OKUMA
     * ============================================================== */

    public function find(int $id): ?MailLog
    {
        $stmt = $this->db->prepare('SELECT * FROM mail_kayitlari WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? MailLog::fromRow($row) : null;
    }

    /** Mektubun HTML gövdesi (önizleme penceresi için). */
    public function body(int $id): string
    {
        $stmt = $this->db->prepare('SELECT govde FROM mail_kayitlari WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    /**
     * @param array<string,mixed> $options
     * @return array{rows:array<int,MailLog>,total:int,filtered:int,bekleyen:int}
     */
    public function paginate(array $options): array
    {
        [$where, $params] = $this->buildFilter($options);

        $orderBy  = self::SORTABLE[(int) ($options['order_column'] ?? 4)] ?? 'created_at';
        $orderDir = strtolower((string) ($options['order_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $start  = max(0, (int) ($options['start'] ?? 0));
        $length = (int) ($options['length'] ?? 10);
        $limit  = $length <= 0 ? 500 : min($length, 500);

        $sql = 'SELECT id, alici_eposta, alici_ad, konu, sablon, tur, durum, hata, deneme,
                       kullanici_id, gonderen_id, toplu_id, gonderildi_at, created_at
                  FROM mail_kayitlari'
             . $where
             . sprintf(' ORDER BY `%s` %s, id DESC', $orderBy, $orderDir)
             . sprintf(' LIMIT %d OFFSET %d', $limit, $start);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'rows'     => array_map(static fn (array $row): MailLog => MailLog::fromRow($row), $stmt->fetchAll()),
            'total'    => $this->countAll(),
            'filtered' => $this->countFiltered($options),
            'bekleyen' => $this->countPending(),
        ];
    }

    /** @param array<string,mixed> $options */
    public function countFiltered(array $options): int
    {
        [$where, $params] = $this->buildFilter($options);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM mail_kayitlari' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM mail_kayitlari')->fetchColumn();
    }

    public function countFailed(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM mail_kayitlari WHERE durum = 'basarisiz'")->fetchColumn();
    }

    public function countSentToday(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_kayitlari WHERE durum = 'gonderildi' AND DATE(gonderildi_at) = CURDATE()"
        )->fetchColumn();
    }

    /** @return array{toplam:int,gonderildi:int,kuyrukta:int,basarisiz:int,bugun:int} */
    public function stats(): array
    {
        return [
            'toplam'     => $this->countAll(),
            'gonderildi' => (int) $this->db->query("SELECT COUNT(*) FROM mail_kayitlari WHERE durum = 'gonderildi'")->fetchColumn(),
            'kuyrukta'   => $this->countPending(),
            'basarisiz'  => $this->countFailed(),
            'bugun'      => $this->countSentToday(),
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildFilter(array $options): array
    {
        $conditions = [];
        $params     = [];

        $status = (string) ($options['durum'] ?? '');

        if (array_key_exists($status, MailLog::statusLabels())) {
            $conditions[]     = 'durum = :durum';
            $params[':durum'] = $status;
        }

        $type = (string) ($options['tur'] ?? '');

        if (array_key_exists($type, MailLog::typeLabels())) {
            $conditions[]   = 'tur = :tur';
            $params[':tur'] = $type;
        }

        $batch = (string) ($options['toplu_id'] ?? '');

        if ($batch !== '') {
            $conditions[]        = 'toplu_id = :toplu_id';
            $params[':toplu_id'] = $batch;
        }

        $search = trim((string) ($options['search'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(alici_eposta LIKE :s_eposta OR alici_ad LIKE :s_ad OR konu LIKE :s_konu)';
            $pattern      = '%' . $this->escapeLike($search) . '%';

            $params[':s_eposta'] = $pattern;
            $params[':s_ad']     = $pattern;
            $params[':s_konu']   = $pattern;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
