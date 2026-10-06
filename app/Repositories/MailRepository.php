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

use App\Core\Database;
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

    /** "gonderiliyor"da bu kadar saniye kalan satır takılmış sayılır. */
    public const STUCK_SECONDS = 900;

    /** Takılan bir satır en fazla kaç kez kuyruğa geri döner. */
    public const MAX_ATTEMPTS = 3;

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
     * Göndermek için claimNext() kullanın.
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
     * Sıradaki TEK mektubu ATOMİK olarak bu sürece ayırır; satır
     * gönderimden HEMEN ÖNCE ayrılır.
     *
     * Aday satır tek bir UPDATE ile "kuyrukta" → "gonderiliyor" yapılır;
     * rowCount() 1 dönmüyorsa satırı başka bir süreç (panel, cron ya da
     * mail:work) bizden önce almıştır ve sıradaki adaya geçilir.
     *
     * NEDEN TEK TEK? Eskiden bir partinin tamamı (10 satır) baştan
     * ayrılıyordu. İşçi ilk mektupta çökerse, hiç denenmemiş dokuz satır
     * da "gonderiliyor"da kalıyor ve 15 dakika sonra BAŞARISIZ
     * sayılıyordu. Artık çökme yalnızca o an gönderilen satırı etkiler.
     *
     * Ayırma, kilitlenme (1213) ya da kilit beklemesi (1205) hatasında
     * yeniden denenir (bkz. Database::retry).
     *
     * @return array<string,mixed>|null Satır; kuyruk boşsa null
     */
    public function claimNext(): ?array
    {
        return Database::retry(function (): ?array {
            $candidates = $this->db->query(
                "SELECT id FROM mail_kayitlari
                  WHERE durum = 'kuyrukta'
                  ORDER BY id ASC
                  LIMIT 5"
            )->fetchAll(PDO::FETCH_COLUMN);

            $claim = $this->db->prepare(
                "UPDATE mail_kayitlari
                    SET durum = 'gonderiliyor', ayrildi_at = NOW()
                  WHERE id = :id AND durum = 'kuyrukta'"
            );

            foreach ($candidates as $id) {
                $claim->execute([':id' => (int) $id]);

                if ($claim->rowCount() !== 1) {
                    continue; // başka süreç kaptı
                }

                $read = $this->db->prepare('SELECT * FROM mail_kayitlari WHERE id = :id');
                $read->execute([':id' => (int) $id]);
                $row = $read->fetch();

                return $row === false ? null : $row;
            }

            return null;
        });
    }

    /**
     * "gonderiliyor"da takılı kalmış kayıtları (süreç çöktü) KUYRUĞA
     * geri koyar; deneme hakkı bittiyse başarısız sayar.
     *
     * Eskiden 15 dakika takılan her satır doğrudan "başarısız" sayılıyordu
     * — 1.2.1'de kuyrukta kalıp yeniden deneniyordu. Bunun bedeli, çöken
     * işçinin elindeki hiç denenmemiş mektupların kalıcı olarak
     * kaybolmasıydı. Şimdi her takılma bir deneme sayılır (deneme + 1);
     * satır ancak MAX_ATTEMPTS'e ulaşınca başarısız olur. Satırlar artık
     * tek tek ve gönderimden hemen önce ayrıldığı için (claimNext) bir
     * takılma en fazla bir mektubu etkiler.
     *
     * KİLİTLENMEYİ ÖNLEMEK İÇİN önce numaralar okunur, sonra her satır
     * BİRİNCİL ANAHTARLA güncellenir. Eskiden tek bir aralık UPDATE'i
     * (durum + tarih koşulu) her ayırmada çalışıyordu; aynı anda çalışan
     * işçilerin ayırma sorgularıyla çakışıp deadlock (1213) üretiyordu.
     * Çağıran taraf da bunu süreç başına bir kez çalıştırır (bkz.
     * Mailer::processQueue).
     *
     * @return int Kuyruğa dönen + başarısız sayılan satır sayısı
     */
    public function releaseStuck(int $seconds = self::STUCK_SECONDS): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM mail_kayitlari
              WHERE durum = 'gonderiliyor'
                AND ayrildi_at IS NOT NULL
                AND ayrildi_at < (NOW() - INTERVAL :saniye SECOND)
              ORDER BY id ASC
              LIMIT 200"
        );
        $stmt->bindValue(':saniye', max(60, $seconds), PDO::PARAM_INT);
        $stmt->execute();

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($ids === []) {
            return 0;
        }

        /* Atamalar soldan sağa uygulanır: durum ve hata ESKİ "deneme"
         * değerine bakar, deneme en son artırılır. Koşul tekrarlanır:
         * okuma ile yazma arasında satır gönderilmiş olabilir. */
        $update = $this->db->prepare(
            "UPDATE mail_kayitlari
                SET durum = IF(deneme + 1 >= :max1, 'basarisiz', 'kuyrukta'),
                    hata  = IF(deneme + 1 >= :max2,
                               'Gönderim yarıda kaldı ve deneme hakkı bitti (süreç zaman aşımına uğradı). Ulaşıp ulaşmadığı bilinmiyor.',
                               'Önceki gönderim yarıda kaldı; yeniden kuyruğa alındı.'),
                    ayrildi_at = NULL,
                    deneme = deneme + 1
              WHERE id = :id AND durum = 'gonderiliyor'
                AND ayrildi_at < (NOW() - INTERVAL :saniye SECOND)"
        );

        $adet = 0;

        foreach ($ids as $id) {
            $adet += (int) Database::retry(function () use ($update, $id, $seconds): int {
                $update->bindValue(':max1', self::MAX_ATTEMPTS, PDO::PARAM_INT);
                $update->bindValue(':max2', self::MAX_ATTEMPTS, PDO::PARAM_INT);
                $update->bindValue(':id', (int) $id, PDO::PARAM_INT);
                $update->bindValue(':saniye', max(60, $seconds), PDO::PARAM_INT);
                $update->execute();

                return $update->rowCount();
            });
        }

        return $adet;
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

    /**
     * Bu adrese son $minutes dakikada bu ŞABLONLA mektup gitti mi?
     * (doğrulama ve "zaten kayıtlı" mektuplarının tekrarını sınırlamak için)
     */
    public function sentTemplateRecently(string $email, string $template, int $minutes): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM mail_kayitlari
              WHERE alici_eposta = :eposta AND sablon = :sablon
                AND created_at >= (NOW() - INTERVAL :dakika MINUTE)"
        );
        $stmt->bindValue(':eposta', mb_strtolower($email));
        $stmt->bindValue(':sablon', $template);
        $stmt->bindValue(':dakika', max(1, $minutes), PDO::PARAM_INT);
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

    /**
     * Son başarısız mektuplar (Sistem Bilgisi'ndeki kuyruk kartı için).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentFailed(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, alici_eposta, konu, hata, deneme, created_at
               FROM mail_kayitlari
              WHERE durum = 'basarisiz'
              ORDER BY id DESC
              LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Bugün gönderilenler.
     *
     * Tarih sütunu bir ARALIKLA karşılaştırılır. Eskiden sütun DATE()
     * fonksiyonuna sarılıp CURDATE() ile eşitleniyordu: sütun bir fonksiyona
     * sarılınca indeks kullanılamaz, gönderilmiş BÜTÜN kayıtlar taranır
     * (300 bin kayıtta sorgu başına ~1 sn; panel her açılışta soruyordu).
     */
    public function countSentToday(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_kayitlari
              WHERE durum = 'gonderildi'
                AND gonderildi_at >= CURDATE() AND gonderildi_at < CURDATE() + INTERVAL 1 DAY"
        )->fetchColumn();
    }

    /**
     * Durum sayaçları. Dört ayrı COUNT yerine tek GROUP BY: tablo bir
     * kez okunur.
     *
     * @return array{toplam:int,gonderildi:int,kuyrukta:int,basarisiz:int,bugun:int}
     */
    public function stats(): array
    {
        $sayilar = ['kuyrukta' => 0, 'gonderiliyor' => 0, 'gonderildi' => 0, 'basarisiz' => 0];

        foreach ($this->db->query('SELECT durum, COUNT(*) AS adet FROM mail_kayitlari GROUP BY durum') as $satir) {
            $sayilar[(string) $satir['durum']] = (int) $satir['adet'];
        }

        return [
            'toplam'     => array_sum($sayilar),
            'gonderildi' => $sayilar['gonderildi'],
            'kuyrukta'   => $sayilar['kuyrukta'],
            'basarisiz'  => $sayilar['basarisiz'],
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
