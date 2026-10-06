<?php
/**
 * =====================================================================
 *  OrnekController – Ornek modülünün panel ekranı (CRUD + RBAC örneği)
 * ---------------------------------------------------------------------
 *  Görünüm "Ornek::index" biçiminde çağrılır; bu, modülün kendi
 *  views/ klasörüne işaret eder (çekirdeğin views/ klasörü kirlenmez).
 *
 *  Her işlem İKİ kapıdan geçer:
 *    1) Rotadaki "can:…" ara katmanı → rolün bu işe yetkisi var mı?
 *    2) OrnekPolicy                  → bu KAYIT üzerinde var mı?
 *  İkincisi olmadan üye, adres çubuğuna başka bir kaydın numarasını
 *  yazarak onu silebilirdi.
 *
 *  KENDİ MODÜLÜNÜZÜ YAZARKEN bu dosyayı kopyalayın: liste, ekle,
 *  düzenle, durum değiştir ve sil — her biri birkaç satır.
 * =====================================================================
 */

declare(strict_types=1);

namespace Modules\Ornek\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Http\Controller;
use App\Models\Role;
use Modules\Ornek\OrnekPolicy;
use Modules\Ornek\Repositories\OrnekRepository;

final class OrnekController extends Controller
{
    private OrnekRepository $kayitlar;

    public function __construct()
    {
        parent::__construct();

        $this->kayitlar = new OrnekRepository(Database::connection());
    }

    /* =================================================================
     *  LİSTE (+ düzenleme formu)
     * ============================================================== */

    public function index(Request $request): void
    {
        $this->render(null);
    }

    /** Rota: can:ornek.update.own|ornek.manage — formu kaydın bilgileriyle açar. */
    public function edit(Request $request, string $id): void
    {
        $kayit  = $this->visibleRecord((int) $id);
        $policy = new OrnekPolicy(Auth::user());

        if (!$policy->canEdit($kayit)) {
            throw HttpException::forbidden($policy->denialReason($kayit), 'ornek.update.own', ['kayit' => (int) $id]);
        }

        $this->render($kayit);
    }

    /** @param array<string,mixed>|null $duzenlenen */
    private function render(?array $duzenlenen): void
    {
        $policy = new OrnekPolicy(Auth::user());

        /* Yetki matrisi: her rol × her yetki, Role::can'in GERÇEK
         * cevabıyla — elle yazılmış bir tablo değil. module.json'daki
         * "yetkiler" değişirse tablo da kendiliğinden değişir. */
        $matris = [];
        foreach (OrnekPolicy::YETKILER as $yetki => $etiket) {
            foreach (Role::all() as $rol) {
                $matris[$yetki][$rol] = Role::can($rol, $yetki);
            }
        }

        $this->view('Ornek::index', [
            'title'      => 'Örnek Modül',
            'subtitle'   => 'Kendi modülünüzü yazarken kopyalayacağınız çalışan örnek: CRUD, onay akışı ve RBAC.',
            'kayitlar'   => $this->kayitlar->listFor($policy->scope(), (int) Auth::id()),
            'duzenlenen' => $duzenlenen,
            'policy'     => $policy,
            'matris'     => $matris,
            'rol'        => (string) (Auth::user()?->rol ?? ''),
            'dolu'       => $this->kayitlar->isFull(),
            'errors'     => Flash::errors(),
            'old'        => Flash::old(),
        ]);
    }

    /* =================================================================
     *  EKLE / GÜNCELLE
     * ============================================================== */

    /** Rota: can:ornek.create (yönetici, editör, üye). */
    public function store(Request $request): void
    {
        if ($this->kayitlar->isFull()) {
            Flash::warning('Tablo üst sınıra (' . OrnekRepository::UST_SINIR . ' kayıt) ulaştı; önce birkaç kayıt silinmeli.');
            Response::redirect(url('panel/ornek'));
        }

        $policy = new OrnekPolicy(Auth::user());
        $temiz  = $this->validated($policy, url('panel/ornek'));
        $durum  = $policy->statusAfterSave((string) ($temiz['durum'] ?? ''));

        // Sahip her zaman isteği yapan kullanıcıdır; formdan gelmez.
        $this->kayitlar->create((string) $temiz['baslik'], (string) ($temiz['aciklama'] ?? ''), $durum, Auth::id());

        Flash::success(match ($durum) {
            'yayinda' => 'Kayıt eklendi ve yayına alındı.',
            'onay'    => 'Kayıt eklendi ve onaya gönderildi; bir editör onaylayınca yayına çıkar.',
            default   => 'Kayıt taslak olarak eklendi.',
        });
        Response::redirect(url('panel/ornek'));
    }

    /** Rota: can:ornek.update.own|ornek.manage — ardından kayıt düzeyi denetim. */
    public function update(Request $request, string $id): void
    {
        $kayit  = $this->visibleRecord((int) $id);
        $policy = new OrnekPolicy(Auth::user());

        if (!$policy->canEdit($kayit)) {
            throw HttpException::forbidden($policy->denialReason($kayit), 'ornek.update.own', ['kayit' => (int) $id]);
        }

        $temiz = $this->validated($policy, url('panel/ornek/duzenle/' . (int) $kayit['id']));
        $durum = $policy->statusAfterSave((string) ($temiz['durum'] ?? ''));

        $this->kayitlar->update((int) $kayit['id'], (string) $temiz['baslik'], (string) ($temiz['aciklama'] ?? ''), $durum);

        /* Yayınlayamayan biri yayındaki kaydını değiştirdiyse kayıt
         * yeniden onaya düştü: bunu söyleyelim, kaybolmuş sanmasın. */
        Flash::success($kayit['durum'] === 'yayinda' && $durum === 'onay'
            ? 'Değişiklik kaydedildi; kayıt yeniden onaya gönderildi.'
            : 'Kayıt güncellendi.');
        Response::redirect(url('panel/ornek'));
    }

    /**
     * Form doğrulaması. Seçilebilecek durumlar role göre değişir:
     * üye "Yayında" gönderirse doğrulamadan geçemez.
     *
     * @return array<string,mixed>
     */
    private function validated(OrnekPolicy $policy, string $geri): array
    {
        $validator = new Validator($_POST);
        $validator->text('baslik', 'Başlık', 2, 150, true)
                  ->text('aciklama', 'Açıklama', 0, 255)
                  ->in('durum', $policy->formStatuses(), 'Durum');

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), $_POST);
            Response::redirect($geri);
        }

        return $validator->validated();
    }

    /* =================================================================
     *  DURUM / SİL / ÖRNEK ÜRET
     * ============================================================== */

    /** Rota: can:ornek.update.own|ornek.publish|ornek.manage — hedef durum OrnekPolicy'ye sorulur. */
    public function status(Request $request, string $id): void
    {
        $kayit  = $this->visibleRecord((int) $id);
        $policy = new OrnekPolicy(Auth::user());
        $hedef  = $request->input('durum');

        if (!$policy->canMoveTo($kayit, $hedef)) {
            throw HttpException::forbidden(
                $policy->canPublish() || $policy->owns($kayit)
                    ? 'Bu kayıt bu duruma taşınamaz.'
                    : $policy->denialReason($kayit),
                'ornek.publish',
                ['kayit' => (int) $id, 'hedef' => $hedef]
            );
        }

        $this->kayitlar->setStatus((int) $kayit['id'], $hedef);

        Flash::success(match ($hedef) {
            'yayinda' => $kayit['durum'] === 'onay' ? 'Kayıt onaylandı ve yayına alındı.' : 'Kayıt yayına alındı.',
            'onay'    => $kayit['durum'] === 'yayinda' ? 'Kayıt yayından kaldırıldı; onay sırasında bekliyor.' : 'Kayıt onaya gönderildi.',
            default   => 'Kayıt taslağa alındı.',
        });
        Response::redirect(url('panel/ornek'));
    }

    /** Rota: can:ornek.update.own|ornek.manage — ardından kayıt düzeyi denetim. */
    public function destroy(Request $request, string $id): void
    {
        $kayit  = $this->visibleRecord((int) $id);
        $policy = new OrnekPolicy(Auth::user());

        if (!$policy->canEdit($kayit)) {
            throw HttpException::forbidden($policy->denialReason($kayit), 'ornek.update.own', ['kayit' => (int) $id]);
        }

        $this->kayitlar->delete((int) $kayit['id']);

        Flash::success('Kayıt silindi.');
        Response::redirect(url('panel/ornek'));
    }

    /** Rota: can:ornek.manage (yalnızca yönetici). */
    public function seed(Request $request): void
    {
        $uretilen = $this->kayitlar->seedSamples(5, $this->kayitlar->ownerCandidates());

        if ($uretilen === 0) {
            Flash::warning('Üst sınıra (' . OrnekRepository::UST_SINIR . ' kayıt) ulaşıldı; önce birkaç kayıt silin.');
        } else {
            Flash::success($uretilen . ' rastgele kayıt eklendi; sahipleri yönetici, editör ve üyeler arasında dağıtıldı.');
        }

        Response::redirect(url('panel/ornek'));
    }

    /**
     * Kaydı bulur. Görmeye yetkisi olmadığı kaydın VARLIĞINI ele
     * vermez: başkasının taslağı için 403 değil 404 döner.
     *
     * @return array<string,mixed>
     */
    private function visibleRecord(int $id): array
    {
        $kayit = $this->kayitlar->find($id);

        if ($kayit === null || !(new OrnekPolicy(Auth::user()))->canView($kayit)) {
            throw HttpException::notFound();
        }

        return $kayit;
    }
}
