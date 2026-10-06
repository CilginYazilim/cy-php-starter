<?php
/**
 * =====================================================================
 *  OrnekController – Ornek modülünün panel ekranı (RBAC örneği)
 * ---------------------------------------------------------------------
 *  Görünüm "Ornek::index" biçiminde çağrılır; bu, modülün kendi
 *  views/ klasörüne işaret eder (çekirdeğin views/ klasörü kirlenmez).
 *
 *  Her işlem İKİ kapıdan geçer:
 *    1) Rotadaki "can:…" ara katmanı → rolün bu işe yetkisi var mı?
 *    2) OrnekPolicy                  → bu KAYIT üzerinde var mı?
 *  İkincisi olmadan editör, adres çubuğuna başka bir kaydın numarasını
 *  yazarak onu silebilirdi.
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

    public function index(Request $request): void
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
            'title'    => 'Örnek Modül',
            'subtitle' => 'Modül sisteminin ve rol tabanlı yetkinin (RBAC) çalışan örneği.',
            'kayitlar' => $this->kayitlar->listFor($policy->scope(), (int) Auth::id()),
            'policy'   => $policy,
            'matris'   => $matris,
            'rol'      => (string) (Auth::user()?->rol ?? ''),
            'errors'   => Flash::errors(),
            'old'      => Flash::old(),
        ]);
    }

    /** Rota: can:ornek.create (yönetici, editör). */
    public function store(Request $request): void
    {
        $validator = new Validator($_POST);
        $validator->text('baslik', 'Başlık', 2, 150, true)
                  ->text('aciklama', 'Açıklama', 0, 255)
                  ->in('durum', array_keys(OrnekRepository::DURUMLAR), 'Durum');

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), $_POST);
            Response::redirect(url('panel/ornek'));
        }

        $temiz = $validator->validated();

        // Sahip her zaman isteği yapan kullanıcıdır; formdan gelmez.
        $this->kayitlar->create(
            (string) $temiz['baslik'],
            (string) ($temiz['aciklama'] ?? ''),
            (string) ($temiz['durum'] ?? 'taslak'),
            Auth::id()
        );

        Flash::success('Kayıt eklendi.');
        Response::redirect(url('panel/ornek'));
    }

    /** Rota: can:ornek.update.own|ornek.manage — ardından kayıt düzeyi denetim. */
    public function toggle(Request $request, string $id): void
    {
        $kayit = $this->authorizedRecord((int) $id);
        $yeni  = $kayit['durum'] === 'yayinda' ? 'taslak' : 'yayinda';

        $this->kayitlar->setStatus((int) $kayit['id'], $yeni);

        Flash::success($yeni === 'yayinda' ? 'Kayıt yayına alındı.' : 'Kayıt taslağa alındı.');
        Response::redirect(url('panel/ornek'));
    }

    /** Rota: can:ornek.update.own|ornek.manage — ardından kayıt düzeyi denetim. */
    public function destroy(Request $request, string $id): void
    {
        $kayit = $this->authorizedRecord((int) $id);

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
            Flash::success($uretilen . ' rastgele örnek kayıt eklendi (sahipleri yönetici ve editörler arasında dağıtıldı).');
        }

        Response::redirect(url('panel/ornek'));
    }

    /**
     * Kaydı bulur ve bu kullanıcının onu değiştirmeye yetkisi olduğunu
     * doğrular. Görmeye yetkisi olmadığı kaydın VARLIĞINI da ele
     * vermez: başkasının taslağı için 404 döner.
     *
     * @return array<string,mixed>
     */
    private function authorizedRecord(int $id): array
    {
        $policy = new OrnekPolicy(Auth::user());
        $kayit  = $this->kayitlar->find($id);

        if ($kayit === null || !$policy->canView($kayit)) {
            throw HttpException::notFound();
        }

        if (!$policy->canModify($kayit)) {
            throw HttpException::forbidden($policy->denialReason($kayit), 'ornek.update.own', ['kayit' => $id]);
        }

        return $kayit;
    }
}
