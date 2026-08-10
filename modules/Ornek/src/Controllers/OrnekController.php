<?php
/**
 * =====================================================================
 *  OrnekController – Ornek modülünün panel ekranı
 * ---------------------------------------------------------------------
 *  Görünüm "Ornek::index" biçiminde çağrılır; bu, modülün kendi
 *  views/ klasörüne işaret eder (çekirdeğin views/ klasörü kirlenmez).
 * =====================================================================
 */

declare(strict_types=1);

namespace Modules\Ornek\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Http\Controller;
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
        $this->view('Ornek::index', [
            'title'    => 'Ornek',
            'subtitle' => 'Ornek modülünün örnek listeleme ekranı.',
            'kayitlar' => $this->kayitlar->all(),
            'errors'   => Flash::errors(),
            'old'      => Flash::old(),
        ]);
    }

    public function store(Request $request): void
    {
        $validator = new Validator($_POST);
        $validator->text('baslik', 'Başlık', 2, 150, true);

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), $_POST);
            Response::redirect(url('panel/ornek'));
        }

        $this->kayitlar->create((string) $validator->validated()['baslik']);

        Flash::success('Kayıt eklendi.');
        Response::redirect(url('panel/ornek'));
    }

    /** Rotadaki {id} bu metoda argüman olarak gelir. */
    public function destroy(Request $request, string $id): void
    {
        $this->kayitlar->delete((int) $id);

        Flash::success('Kayıt silindi.');
        Response::redirect(url('panel/ornek'));
    }
}
