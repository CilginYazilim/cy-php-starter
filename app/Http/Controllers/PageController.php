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
 *
 *  GÖRSELLER: Kapak (upload/img/kapak/) sayfanın üstünde ve paylaşım
 *  görseli (og:image) olarak kullanılır. Editörden yüklenen görseller
 *  upload/img/sayfa/ altına gider. İkisi de Uploader::image() ile
 *  YENİDEN ÜRETİLİR; içerikteki <img> yalnızca kendi alan adımızı
 *  gösterebilir (bkz. Html::localImage).
 *
 *  ÖNİZLEME: Taslak sayfa ön yüzde 404'tür. "Önizle" düğmesi 30 dakika
 *  geçerli, APP_KEY ile imzalı bir adres üretir (onizleme/{id}?son=…&imza=…).
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
use App\Core\Signer;
use App\Core\Uploader;
use App\Http\Controller;
use App\Repositories\PageRepository;
use RuntimeException;

final class PageController extends Controller
{
    /** Önizleme bağlantısının ömrü (saniye). */
    public const ONIZLEME_SURESI = 1800;

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
     *  ÖNİZLEME
     * ============================================================== */

    /** "Önizle": her tıklamada yeni, 30 dakika geçerli bir imzalı adres. */
    public function preview(Request $request, string $id): void
    {
        $sayfa = $this->pages()->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('panel/sayfalar/' . $id);
        }

        Response::redirect(self::previewUrl($sayfa->id));
    }

    public static function previewUrl(int $id, ?int $now = null): string
    {
        $son = ($now ?? time()) + self::ONIZLEME_SURESI;

        return url('onizleme/' . $id, ['son' => $son, 'imza' => Signer::sign(self::previewPayload($id, $son))]);
    }

    /** İmzalı önizleme adresi geçerli mi? (süresi dolmamış ve imza tutuyor) */
    public static function previewValid(int $id, int $son, string $imza, ?int $now = null): bool
    {
        $now ??= time();

        return $son >= $now
            && $son <= $now + self::ONIZLEME_SURESI
            && Signer::check(self::previewPayload($id, $son), $imza);
    }

    private static function previewPayload(int $id, int $son): string
    {
        return 'sayfa-onizleme|' . $id . '|' . $son;
    }

    /* =================================================================
     *  EDİTÖRDEN GÖRSEL YÜKLEME (AJAX)
     * ============================================================== */

    public function uploadImage(Request $request): void
    {
        if (!$request->hasFile('gorsel')) {
            Response::error('Bir görsel seçin.', 422);
        }

        try {
            $yol = Uploader::image((array) $request->file('gorsel'), 'sayfa');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

        Logger::info('Sayfa görseli yüklendi', ['dosya' => $yol], 'app');

        Response::success('Görsel eklendi.', ['url' => Uploader::url($yol)]);
    }

    /* =================================================================
     *  KAYDETME
     * ============================================================== */

    public function store(Request $request): void
    {
        $this->save($request, null);
    }

    public function update(Request $request, string $id): void
    {
        $sayfa = $this->pages()->find((int) $id);

        if ($sayfa === null) {
            throw HttpException::notFound('panel/sayfalar/' . $id);
        }

        $this->save($request, $sayfa);
    }

    /** POST'tan metin; dizi gönderilmişse varsayılan (bkz. Request::string). */
    private static function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    private function save(Request $request, ?\App\Models\Page $mevcut): void
    {
        $depo   = $this->pages();
        $errors = [];

        $baslik = trim(self::post('baslik'));
        $slug   = Html::slug(trim(self::post('slug')));
        $icerik = self::post('icerik');

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

        $geri = url($mevcut === null ? 'panel/sayfalar/yeni' : 'panel/sayfalar/' . $mevcut->id);

        if ($errors !== []) {
            Flash::withInput($errors, $_POST);
            Response::redirect($geri);
        }

        /* KAPAK: yeni dosya → yüklenir; "kaldır" işaretli → boşalır;
         * ikisi de yoksa eskisi kalır. Eski dosya ancak kayıt başarıyla
         * yazıldıktan SONRA silinir. */
        $eskiKapak = $mevcut?->kapak ?? '';
        $kapak     = $eskiKapak;

        if ($request->hasFile('kapak')) {
            try {
                $kapak = Uploader::image((array) $request->file('kapak'), 'kapak');
            } catch (RuntimeException $e) {
                Flash::withInput(['kapak' => $e->getMessage()], $_POST);
                Response::redirect($geri);
            }
        } elseif (isset($_POST['kapak_kaldir'])) {
            $kapak = '';
        }

        $veri = [
            'baslik'       => $baslik,
            'slug'         => $slug,
            'ozet'         => trim(self::post('ozet')),
            'icerik'       => $icerik,
            'kapak'        => $kapak,
            'durum'        => self::post('durum', 'taslak'),
            'menude'       => isset($_POST['menude']),
            // smallint unsigned sütun: dışarıdaki değer veritabanı hatasına dönüşmesin.
            'sira'         => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
            'seo_baslik'   => trim(self::post('seo_baslik')),
            'seo_aciklama' => trim(self::post('seo_aciklama')),
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

        if ($eskiKapak !== '' && $eskiKapak !== $kapak) {
            Uploader::delete($eskiKapak);
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
        Uploader::delete($sayfa->kapak);

        Logger::warning('Sayfa silindi', ['sayfa' => $sayfa->id, 'slug' => $sayfa->slug], 'app');

        Flash::success('“' . $sayfa->baslik . '” sayfası silindi.');
        Response::redirect(url('panel/sayfalar'));
    }
}
