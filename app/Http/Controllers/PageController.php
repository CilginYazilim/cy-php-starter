<?php
/**
 * =====================================================================
 *  PageController – İçerik sayfaları (panel)
 * ---------------------------------------------------------------------
 *  Sayfa sayısı doğası gereği azdır (Hakkımızda, Gizlilik, KVKK…),
 *  bu yüzden DataTables + AJAX kurmuyoruz: liste tek sorguyla basılır,
 *  düzenleme klasik bir form gönderimidir.
 *
 *  İÇERİK NEREDE SÜZÜLÜR? Denetleyicide DEĞİL, PageRepository::bind()
 *  içinde. Kaydetmenin tek yolu depodan geçtiği için "bir yerde
 *  sanitize çağırmayı unutmak" mümkün değildir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Flash;
use App\Core\Html;
use App\Core\Log\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repositories\PageRepository;

final class PageController extends Controller
{
    private function pages(): PageRepository
    {
        return new PageRepository($this->db);
    }

    /* =================================================================
     *  LİSTE
     * ============================================================== */

    public function index(Request $request): void
    {
        $this->view('pages/index', [
            'title'    => 'Sayfalar',
            'subtitle' => 'Hakkımızda, İletişim ve diğer içerik sayfalarını buradan yazın.',
            'sayfalar' => $this->pages()->all(),
            'istatistik' => $this->pages()->stats(),
        ]);
    }

    /* =================================================================
     *  YENİ / DÜZENLE
     * ============================================================== */

    public function create(Request $request): void
    {
        $this->form(null);
    }

    public function edit(Request $request, string $id): void
    {
        $sayfa = $this->pages()->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('panel/sayfalar/' . $id);
        }

        $this->form($sayfa);
    }

    private function form(?\App\Models\Page $sayfa): void
    {
        $this->view('pages/form', [
            'title'    => $sayfa === null ? 'Yeni Sayfa' : $sayfa->baslik,
            'subtitle' => $sayfa === null
                ? 'Başlık ve içerik yazın; adresi başlıktan otomatik üretiriz.'
                : 'Sayfayı düzenleyin. Kaydettiğiniz an ön yüzde geçerli olur.',
            'sayfa'   => $sayfa,
            'errors'  => Flash::errors(),
            'old'     => Flash::old(),
            'scripts' => ['editor.js', 'pages.js'],
        ]);
    }

    /* =================================================================
     *  KAYDETME
     * ============================================================== */

    public function store(Request $request): void
    {
        $this->save(null);
    }

    public function update(Request $request, string $id): void
    {
        $sayfa = $this->pages()->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('panel/sayfalar/' . $id);
        }

        $this->save($sayfa);
    }

    private function save(?\App\Models\Page $mevcut): void
    {
        $depo   = $this->pages();
        $errors = [];

        $baslik = trim((string) ($_POST['baslik'] ?? ''));
        $slug   = Html::slug(trim((string) ($_POST['slug'] ?? '')));
        $icerik = (string) ($_POST['icerik'] ?? '');

        if (mb_strlen($baslik) < 2) {
            $errors['baslik'] = 'Başlık en az 2 karakter olmalı.';
        }
        if (mb_strlen($baslik) > 190) {
            $errors['baslik'] = 'Başlık en fazla 190 karakter olabilir.';
        }

        /* Adres boş bırakıldıysa başlıktan üretiyoruz — yönetici
         * "slug" diye bir kavram bilmek zorunda değil. */
        if ($slug === '') {
            $slug = $depo->uniqueSlug($baslik, $mevcut?->id);
        }

        if (PageRepository::reserved($slug)) {
            $errors['slug'] = 'Bu adres sistem tarafından kullanılıyor. Başka bir adres seçin.';
        } elseif ($depo->slugTaken($slug, $mevcut?->id)) {
            $errors['slug'] = 'Bu adres başka bir sayfada kullanılıyor.';
        }

        /* KORUMALI SAYFANIN ADRESİ DEĞİŞTİRİLEMEZ. Üst menü, alt
         * bilgi ve ana sayfadaki düğmeler "hakkimizda" adresine
         * bağlantı verir; adres değişirse hepsi 404'e düşer. */
        if ($mevcut !== null && $mevcut->korumali && $slug !== $mevcut->slug) {
            $slug = $mevcut->slug;
            Flash::warning('Bu sayfa çekirdek sayfalardan biri; adresi değiştirilemez.');
        }

        if (Html::sanitize($icerik) === '') {
            $errors['icerik'] = 'İçerik boş bırakılamaz.';
        }

        if ($errors !== []) {
            Flash::withInput($errors, $_POST);
            Response::redirect(url($mevcut === null ? 'panel/sayfalar/yeni' : 'panel/sayfalar/' . $mevcut->id));
        }

        $veri = [
            'baslik'       => $baslik,
            'slug'         => $slug,
            'ozet'         => trim((string) ($_POST['ozet'] ?? '')),
            'icerik'       => $icerik,
            'durum'        => (string) ($_POST['durum'] ?? 'taslak'),
            'menude'       => isset($_POST['menude']),
            'sira'         => (int) ($_POST['sira'] ?? 0),
            'seo_baslik'   => trim((string) ($_POST['seo_baslik'] ?? '')),
            'seo_aciklama' => trim((string) ($_POST['seo_aciklama'] ?? '')),
            'yazar_id'     => Auth::id(),
        ];

        if ($mevcut === null) {
            $id = $depo->create($veri);
            Logger::info('Sayfa oluşturuldu', ['sayfa' => $id, 'slug' => $slug], 'app');
            Flash::success('“' . $baslik . '” sayfası oluşturuldu.');
        } else {
            $id = $mevcut->id;
            $depo->update($id, $veri);
            Logger::info('Sayfa güncellendi', ['sayfa' => $id, 'slug' => $slug], 'app');
            Flash::success('“' . $baslik . '” sayfası kaydedildi.');
        }

        Response::redirect(url('panel/sayfalar/' . $id));
    }

    /* =================================================================
     *  SİLME
     * ============================================================== */

    public function destroy(Request $request, string $id): void
    {
        $sayfa = $this->pages()->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('panel/sayfalar/' . $id);
        }

        if ($sayfa->korumali) {
            Flash::error('“' . $sayfa->baslik . '” çekirdek bir sayfa; silinemez. İsterseniz taslağa alabilirsiniz.');
            Response::redirect(url('panel/sayfalar'));
        }

        $this->pages()->delete($sayfa->id);

        Logger::warning('Sayfa silindi', ['sayfa' => $sayfa->id, 'slug' => $sayfa->slug], 'app');

        Flash::success('“' . $sayfa->baslik . '” sayfası silindi.');
        Response::redirect(url('panel/sayfalar'));
    }
}
