<?php
/**
 * =====================================================================
 *  SettingsController – Site Ayarları
 * ---------------------------------------------------------------------
 *  Ayarlar formu, "ayarlar" tablosundaki satırlardan OTOMATİK üretilir.
 *  Yeni bir ayar eklemek için tabloya satır eklemek yeterlidir; bu
 *  denetleyicide veya görünümde HİÇBİR ŞEY değiştirmeniz gerekmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Core\Uploader;
use App\Http\Controller;
use RuntimeException;

final class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('settings/index', [
            'title'    => 'Site Ayarları',
            'subtitle' => 'Sitenizin genel, iletişim, sosyal medya, SEO ve sistem ayarları.',
            'groups'   => Setting::grouped($this->db),
            'labels'   => Setting::groupLabels(),
        ]);
    }

    public function update(Request $request): void
    {
        $groups = Setting::grouped($this->db);
        $values = [];

        // Formda gelmeyen "onay" (checkbox) alanları KAPALI sayılır;
        // bu yüzden ekrandaki TÜM ayarları tek tek dolaşıp değerlerini
        // (checkbox ise gelmese bile "0" olarak) topluyoruz.
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if ((int) $row['duzenlenebilir'] === 0) {
                    continue;
                }

                $key = (string) $row['anahtar'];

                $values[$key] = $row['tip'] === 'onay'
                    ? ($request->bool($key) ? '1' : '0')
                    : trim((string) ($_POST[$key] ?? ''));
            }
        }

        Setting::saveMany($this->db, $values);

        Flash::success('Ayarlar kaydedildi.');
        Response::redirect(url('panel/ayarlar'));
    }

    /** Site logosunu yükler ve "site_logo" ayarına yazar. */
    public function uploadLogo(Request $request): void
    {
        if (!$request->hasFile('logo')) {
            Flash::error('Dosya seçilmedi.');
            Response::redirect(url('panel/ayarlar'));
        }

        try {
            $newFile = Uploader::logo((array) $request->file('logo'));
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(url('panel/ayarlar'));
        }

        $old = Setting::get('site_logo');

        Setting::set($this->db, 'site_logo', $newFile);

        if ($old !== '' && $old !== $newFile) {
            Uploader::delete($old);
        }

        Flash::success('Logo güncellendi.');
        Response::redirect(url('panel/ayarlar'));
    }
}
