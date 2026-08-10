<?php
/**
 * =====================================================================
 *  MailApiController – E-posta merkezinin AJAX uç noktaları
 * ---------------------------------------------------------------------
 *  TOPLU GÖNDERİM NASIL ÇALIŞIR?
 *
 *    1) "gonder"  → mektuplar TEK TEK kuyruğa yazılır (durum=kuyrukta),
 *                   ekrana toplam sayı ve bir "toplu numarası" döner.
 *                   Bu adım hızlıdır: sadece veritabanına yazar.
 *
 *    2) "isle"    → tarayıcı bu ucu ARKA ARKAYA çağırır; her çağrı
 *                   parti boyutu kadar (varsayılan 15) mektup gönderir
 *                   ve kalan sayıyı döndürür. İlerleme çubuğu buradan
 *                   beslenir.
 *
 *  NEDEN BÖYLE? 500 kişiye tek istekte mektup göndermek PHP'nin
 *  max_execution_time sınırına takılır; işlem yarıda kesilir ve
 *  kimin aldığını bilemezsiniz. Kuyruk sayesinde her mektubun
 *  durumu tek tek kayıtlıdır, kesinti olsa bile kalanı gönderirsiniz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Auth;
use App\Core\Mail\Mailable;
use App\Core\Mail\MailException;
use App\Core\Mail\Mailer;
use App\Core\Mail\Notifier;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Core\View;
use App\Http\Controller;
use App\Models\MailLog;
use App\Models\Role;
use Throwable;

final class MailApiController extends Controller
{
    /** Tek gönderimde izin verilen en yüksek alıcı sayısı. */
    private const MAX_RECIPIENTS = 2000;

    /* =================================================================
     *  GEÇMİŞ
     * ============================================================== */

    public function list(Request $request): void
    {
        $result = $this->mails()->paginate($this->tableQuery($request, 4) + [
            'durum'    => $request->input('durum'),
            'tur'      => $request->input('tur'),
            'toplu_id' => $request->input('toplu_id'),
        ]);

        $rows = [];

        foreach ($result['rows'] as $log) {
            $rows[] = $this->toTableRow($log);
        }

        // "bekleyen": kuyruk sayacı listeyle birlikte tazelensin.
        $this->tableJson($request, $result, $rows, ['bekleyen' => $result['bekleyen']]);
    }

    /** @return array<int,string> */
    private function toTableRow(MailLog $log): array
    {
        $alici = $log->aliciAd !== ''
            ? '<span class="cy-user-cell__name">' . e($log->aliciAd) . '</span><span class="cy-user-cell__meta">' . e($log->aliciEposta) . '</span>'
            : '<span class="cy-user-cell__name">' . e($log->aliciEposta) . '</span>';

        $durum = '<span class="cy-status ' . $log->statusVariant() . '"><span class="cy-status__dot"></span>' . e($log->statusLabel()) . '</span>';

        if ($log->durum === MailLog::DURUM_BASARISIZ && $log->hata !== '') {
            $durum .= '<br><span class="cy-cell-muted" style="font-size:.72rem" title="' . e($log->hata) . '">'
                    . e(mb_strimwidth($log->hata, 0, 48, '…', 'UTF-8')) . '</span>';
        }

        $actions = '<div class="cy-actions">'
            . '<button type="button" class="cy-btn-icon cy-btn-icon--view js-preview" data-id="' . $log->id . '" title="Önizle">'
            . icon('eye', 'cy-icon cy-icon--sm') . '</button>';

        if (Auth::can('mail.send') && $log->durum === MailLog::DURUM_BASARISIZ) {
            $actions .= '<button type="button" class="cy-btn-icon cy-btn-icon--edit js-requeue" data-id="' . $log->id . '" title="Yeniden gönder">'
                      . icon('refresh', 'cy-icon cy-icon--sm') . '</button>';
        }

        if (Auth::can('mail.send')) {
            $actions .= '<button type="button" class="cy-btn-icon cy-btn-icon--delete js-delete" data-id="' . $log->id . '"'
                      . ' data-label="' . e($log->konu) . '" title="Sil">' . icon('trash', 'cy-icon cy-icon--sm') . '</button>';
        }

        $actions .= '</div>';

        return [
            $alici,
            '<span>' . e($log->konu) . '</span>',
            '<span class="cy-cell-muted">' . e($log->typeLabel()) . '</span>',
            $durum,
            '<span class="cy-nowrap cy-cell-muted" title="' . e(MailLog::formatDate($log->gonderildiAt ?? $log->createdAt)) . '">'
                . e(human_date($log->gonderildiAt ?? $log->createdAt)) . '</span>',
            $actions,
        ];
    }

    /** Kayıtlı mektubun HTML gövdesini önizleme penceresine döndürür. */
    public function fetch(Request $request): void
    {
        $log = $this->requireLog($request);

        Response::json([
            'success' => true,
            'id'      => $log->id,
            'alici'   => $log->aliciAd !== '' ? $log->aliciAd . ' <' . $log->aliciEposta . '>' : $log->aliciEposta,
            'konu'    => $log->konu,
            'durum'   => $log->statusLabel(),
            'tur'     => $log->typeLabel(),
            'hata'    => $log->hata,
            'deneme'  => $log->deneme,
            'tarih'   => MailLog::formatDate($log->gonderildiAt ?? $log->createdAt),
            'govde'   => $this->mails()->body($log->id),
        ]);
    }

    public function requeue(Request $request): void
    {
        $log = $this->requireLog($request);

        if (!$this->mails()->requeue($log->id)) {
            Response::error('Bu mektup zaten kuyrukta.', 422);
        }

        Response::success('Mektup yeniden kuyruğa alındı.', [
            'id'       => $log->id,
            'bekleyen' => $this->mails()->countPending(),
        ]);
    }

    public function delete(Request $request): void
    {
        $log = $this->requireLog($request);

        $this->mails()->delete($log->id);

        Response::success('Kayıt silindi.', ['id' => $log->id]);
    }

    /** 90 günden eski, başarıyla gönderilmiş kayıtları temizler. */
    public function purge(Request $request): void
    {
        $days    = $request->int('gun', 7, 3650) ?? 90;
        $deleted = $this->mails()->purge($days);

        Response::success($deleted . ' eski kayıt silindi.');
    }

    /* =================================================================
     *  GÖNDERİM
     * ============================================================== */

    /** Seçilen hedef kitledeki kişi sayısını canlı gösterir. */
    public function audience(Request $request): void
    {
        try {
            $recipients = $this->resolveRecipients($request);
        } catch (MailException $e) {
            Response::error($e->getMessage(), 422);
        }

        $ornek = array_slice(array_column($recipients, 'eposta'), 0, 5);

        Response::json([
            'success' => true,
            'sayi'    => count($recipients),
            'ornek'   => $ornek,
        ]);
    }

    /** Yazılan mektubun tarayıcıda görünecek HTML'ini üretir. */
    public function preview(Request $request): void
    {
        $draft = $this->validateDraft($request);

        Response::json([
            'success' => true,
            'konu'    => $draft['konu'],
            'govde'   => $this->renderBody($draft, 'Örnek Alıcı'),
        ]);
    }

    /**
     * Mektupları kuyruğa yazar. GÖNDERMEZ — gönderim "isle" ucunda
     * parti parti yapılır.
     */
    public function send(Request $request): void
    {
        $draft = $this->validateDraft($request);

        try {
            $recipients = $this->resolveRecipients($request);
        } catch (MailException $e) {
            Response::error($e->getMessage(), 422);
        }

        if ($recipients === []) {
            Response::error('Seçtiğiniz hedef kitlede hiç alıcı yok.', 422);
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            Response::error('Tek seferde en fazla ' . self::MAX_RECIPIENTS . ' alıcıya gönderebilirsiniz.', 422);
        }

        $batchId = bin2hex(random_bytes(16));
        $queued  = 0;

        foreach ($recipients as $recipient) {
            $mail = Mailable::make()
                ->to($recipient['eposta'], $recipient['ad'])
                ->subject($draft['konu'])
                ->html($this->renderBody($draft, $recipient['ad']))
                ->template('duyuru')
                ->type(count($recipients) > 1 ? 'toplu' : 'bildirim')
                ->forUser($recipient['id']);

            $reply = Mailer::adminAddress();

            if ($reply !== '') {
                $mail->replyTo($reply, Setting::get('site_adi', ''));
            }

            Mailer::queue($mail, $batchId);
            $queued++;
        }

        Response::success($queued . ' mektup kuyruğa alındı.', [
            'toplu_id'     => $batchId,
            'toplam'       => $queued,
            'parti'        => $this->batchSize(),
            'bekleyen'     => $this->mails()->countPending(),
        ]);
    }

    /**
     * Kuyruktan bir parti gönderir. Tarayıcı bu ucu "kalan" sıfır
     * olana kadar tekrar tekrar çağırır.
     */
    public function process(Request $request): void
    {
        $result = Mailer::processQueue($this->batchSize());

        Response::json([
            'success'    => true,
            'gonderildi' => $result['gonderildi'],
            'basarisiz'  => $result['basarisiz'],
            'kalan'      => $result['kalan'],
            'hata'       => Mailer::lastError(),
        ]);
    }

    /** Ayarlar sayfasındaki "Sınama E-postası" düğmesi. */
    public function test(Request $request): void
    {
        $to = trim($request->input('eposta'));

        if ($to === '') {
            $to = Auth::user()?->eposta ?? '';
        }

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Response::error('Geçerli bir e-posta adresi girin.', 422, ['errors' => ['eposta' => 'Geçerli bir e-posta adresi girin.']]);
        }

        try {
            Notifier::sinama($to, Auth::user()?->fullName() ?? '');
        } catch (Throwable $e) {
            Response::error('Gönderilemedi: ' . $e->getMessage(), 502);
        }

        $surucu = Setting::get('mail_surucu', 'kayit');

        Response::success(
            $surucu === 'kayit'
                ? 'Sınama mektubu "storage/mail" klasörüne yazıldı. Gerçekten göndermek için gönderim yöntemini SMTP yapın.'
                : 'Sınama e-postası ' . $to . ' adresine gönderildi.'
        );
    }

    /** SMTP bağlantısını mektup göndermeden dener. */
    public function verify(Request $request): void
    {
        try {
            Mailer::verify();
        } catch (Throwable $e) {
            Response::error('Bağlantı kurulamadı: ' . $e->getMessage(), 502);
        }

        Response::success('Bağlantı başarılı: ' . Mailer::transport()->name() . ' hazır.');
    }

    /* =================================================================
     *  İÇ YARDIMCILAR
     * ============================================================== */

    /**
     * Formdaki mektup taslağını doğrular.
     *
     * @return array{konu:string,govde:string,baslik:string,dugme_metni:string,dugme_url:string}
     */
    private function validateDraft(Request $request): array
    {
        $errors = [];

        $konu  = trim($request->input('konu'));
        $govde = trim((string) ($_POST['govde'] ?? ''));

        if (mb_strlen($konu, 'UTF-8') < 3) {
            $errors['konu'] = 'Konu en az 3 karakter olmalı.';
        } elseif (mb_strlen($konu, 'UTF-8') > 200) {
            $errors['konu'] = 'Konu en fazla 200 karakter olabilir.';
        }

        if (mb_strlen($govde, 'UTF-8') < 10) {
            $errors['govde'] = 'Mesaj en az 10 karakter olmalı.';
        } elseif (mb_strlen($govde, 'UTF-8') > 20000) {
            $errors['govde'] = 'Mesaj en fazla 20.000 karakter olabilir.';
        }

        $baslik    = trim($request->input('baslik'));
        $butonAd   = trim($request->input('dugme_metni'));
        $butonUrl  = trim($request->input('dugme_url'));

        if ($butonUrl !== '' && filter_var($butonUrl, FILTER_VALIDATE_URL) === false) {
            $errors['dugme_url'] = 'Geçerli bir adres girin (https:// ile başlamalı).';
        }

        if ($butonUrl !== '' && $butonAd === '') {
            $errors['dugme_metni'] = 'Düğme adresi girdiyseniz düğme metni de gerekir.';
        }

        if ($errors !== []) {
            Response::error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $errors]);
        }

        return [
            'konu'        => $konu,
            'govde'       => $govde,
            'baslik'      => $baslik !== '' ? $baslik : $konu,
            'dugme_metni' => $butonAd,
            'dugme_url'   => $butonUrl,
        ];
    }

    /**
     * @param array{konu:string,govde:string,baslik:string,dugme_metni:string,dugme_url:string} $draft
     */
    private function renderBody(array $draft, string $name): string
    {
        $content = View::capture('emails/duyuru', [
            'baslik'     => $draft['baslik'],
            'govde'      => $draft['govde'],
            'ad'         => $name,
            'dugmeMetni' => $draft['dugme_metni'],
            'dugmeUrl'   => $draft['dugme_url'],
        ]);

        return View::capture('emails/layout', [
            'content'  => $content,
            'mailKonu' => $draft['konu'],
            'onizleme' => $draft['konu'],
        ]);
    }

    /**
     * Hedef kitle seçimini gerçek alıcı listesine çevirir.
     *
     * @return array<int,array{eposta:string,ad:string,id:int|null}>
     */
    private function resolveRecipients(Request $request): array
    {
        $audience = $request->input('hedef', 'elle');

        $recipients = match (true) {
            $audience === 'tumu'             => $this->fromUsers($this->users()->mailRecipients('', 'aktif')),
            str_starts_with($audience, 'rol:') => $this->fromRole(substr($audience, 4)),
            $audience === 'kullanici'        => $this->fromSingleUser($request),
            default                          => $this->fromManualList($request),
        };

        // Aynı adrese iki kez mektup gitmesin.
        $unique = [];

        foreach ($recipients as $recipient) {
            $key = mb_strtolower($recipient['eposta'], 'UTF-8');

            if (!array_key_exists($key, $unique)) {
                $unique[$key] = $recipient;
            }
        }

        return array_values($unique);
    }

    /**
     * @param array<int,array{id:int,ad:string,soyad:string,eposta:string}> $users
     * @return array<int,array{eposta:string,ad:string,id:int|null}>
     */
    private function fromUsers(array $users): array
    {
        $recipients = [];

        foreach ($users as $user) {
            if (filter_var($user['eposta'], FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[] = [
                'eposta' => $user['eposta'],
                'ad'     => trim($user['ad'] . ' ' . $user['soyad']),
                'id'     => $user['id'],
            ];
        }

        return $recipients;
    }

    /** @return array<int,array{eposta:string,ad:string,id:int|null}> */
    private function fromRole(string $role): array
    {
        if (!Role::exists($role)) {
            throw new MailException('Geçersiz rol seçimi.');
        }

        return $this->fromUsers($this->users()->mailRecipients($role, 'aktif'));
    }

    /** @return array<int,array{eposta:string,ad:string,id:int|null}> */
    private function fromSingleUser(Request $request): array
    {
        $id = $request->int('kullanici_id', 1);

        if ($id === null) {
            throw new MailException('Bir kullanıcı seçin.');
        }

        $user = $this->users()->find($id);

        if ($user === null || $user->eposta === '') {
            throw new MailException('Kullanıcı bulunamadı.');
        }

        return [['eposta' => $user->eposta, 'ad' => $user->fullName(), 'id' => $user->id]];
    }

    /**
     * Elle yazılan adresleri ayrıştırır. Virgül, noktalı virgül,
     * boşluk ve satır sonu ayraç sayılır.
     *
     * @return array<int,array{eposta:string,ad:string,id:int|null}>
     */
    private function fromManualList(Request $request): array
    {
        $raw   = (string) ($_POST['adresler'] ?? '');
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        $recipients = [];
        $invalid    = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (filter_var($part, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = $part;
                continue;
            }

            $recipients[] = ['eposta' => $part, 'ad' => '', 'id' => null];
        }

        if ($invalid !== []) {
            throw new MailException('Geçersiz adres: ' . implode(', ', array_slice($invalid, 0, 3)));
        }

        if ($recipients === []) {
            throw new MailException('En az bir e-posta adresi girin.');
        }

        return $recipients;
    }

    private function batchSize(): int
    {
        $size = (int) Setting::get('mail_parti_boyutu', '15');

        return max(1, min($size, 50));
    }

    private function requireLog(Request $request): MailLog
    {
        $id = $request->int('id', 1);

        if ($id === null) {
            Response::error('Geçersiz kayıt numarası.', 400);
        }

        $log = $this->mails()->find($id);

        if ($log === null) {
            Response::error('E-posta kaydı bulunamadı.', 404);
        }

        return $log;
    }
}
