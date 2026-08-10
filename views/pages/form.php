<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Sayfa oluştur / düzenle
 * ---------------------------------------------------------------------
 *  Sol sütun içerik, sağ sütun yayın ve SEO ayarları. Başlık üst
 *  çubukta yazdığı için burada tekrar edilmiyor.
 *
 *  @var App\Models\Page|null $sayfa
 *  @var array<string,string> $errors, $old
 * =====================================================================
 */

use App\Models\Page;

$sayfa  = $sayfa ?? null;
$errors = $errors ?? [];
$old    = $old ?? [];

/** Doğrulama hatasından döndüysek kullanıcının YAZDIĞINI göster. */
$deger = static function (string $alan, string $varsayilan = '') use ($old): string {
    return array_key_exists($alan, $old) ? (string) $old[$alan] : $varsayilan;
};

$eylem = $sayfa === null
    ? url('panel/sayfalar/yeni')
    : url('panel/sayfalar/' . $sayfa->id);

/* İçerik editöre HAM HTML olarak basılır (kaçışlansa editörde etiket
 * metni görünürdü). Veritabanındaki hâli zaten süzülmüştür; ama
 * doğrulama hatasından dönerken $old içinden HENÜZ SÜZÜLMEMİŞ POST
 * verisi geliyor olabilir — o yolu açık bırakmak, kendi tarayıcısında
 * betik çalıştırabilen bir "yazar" demekti. Basmadan önce süzüyoruz. */
$icerik = App\Core\Html::sanitize($deger('icerik', $sayfa?->icerik ?? ''));
$durum  = $deger('durum', $sayfa?->durum ?? 'taslak');
$menude = array_key_exists('menude', $old) ? true : ($sayfa?->menude ?? false);
?>

<form method="post" action="<?= e($eylem) ?>" id="cy_page_form" novalidate>
    <?= csrf_field() ?>

    <?php if ($errors !== []): ?>
        <div class="cy-alert cy-alert--danger mb-3">
            Lütfen işaretli alanları düzeltin. Sayfa kaydedilmedi.
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- ================= SOL: İÇERİK ================= -->
        <div class="col-12 col-xl-8">
            <div class="cy-card mb-3">
                <div class="cy-card__body">
                    <div class="mb-3">
                        <label class="form-label" for="baslik">Sayfa Başlığı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg<?= isset($errors['baslik']) ? ' is-invalid' : '' ?>"
                               id="baslik" name="baslik" maxlength="190" autocomplete="off"
                               value="<?= e($deger('baslik', $sayfa?->baslik ?? '')) ?>"
                               placeholder="Örn: Hakkımızda">
                        <?php if (isset($errors['baslik'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['baslik']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="slug">Adres</label>
                        <div class="cy-slug<?= isset($errors['slug']) ? ' is-invalid' : '' ?>">
                            <span class="cy-slug__prefix"><?= e(rtrim(App\Core\Url::base(), '/')) ?>/</span>
                            <input type="text" class="form-control" id="slug" name="slug" maxlength="190"
                                   autocomplete="off" value="<?= e($deger('slug', $sayfa?->slug ?? '')) ?>"
                                   placeholder="basliktan-otomatik-uretilir"
                                   <?= ($sayfa?->korumali ?? false) ? 'readonly' : '' ?>>
                        </div>
                        <?php if (isset($errors['slug'])): ?>
                            <div class="invalid-feedback d-block"><?= e($errors['slug']) ?></div>
                        <?php elseif ($sayfa?->korumali ?? false): ?>
                            <div class="form-text">
                                <?= icon('lock', 'cy-icon cy-icon--sm') ?>
                                Çekirdek sayfa: menü ve alt bilgi bu adrese bağlantı verdiği için değiştirilemez.
                            </div>
                        <?php else: ?>
                            <div class="form-text">Boş bırakırsanız başlıktan otomatik üretilir.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="ozet">Kısa Özet</label>
                        <input type="text" class="form-control" id="ozet" name="ozet" maxlength="255"
                               value="<?= e($deger('ozet', $sayfa?->ozet ?? '')) ?>"
                               placeholder="Sayfanın bir cümlelik tanıtımı">
                        <div class="form-text">Listelerde ve paylaşımlarda görünür. Boşsa içerikten üretilir.</div>
                    </div>
                </div>
            </div>

            <div class="cy-card">
                <div class="cy-card__header">
                    <h2 class="cy-section-title mb-0"><?= icon('edit', 'cy-icon cy-icon--sm') ?> İçerik</h2>
                    <span class="cy-muted small">Metni biçimlendirmek için üstteki araç çubuğunu kullanın</span>
                </div>

                <div class="cy-card__body">
                    <?php /*
                        EDİTÖRÜN ÇALIŞMA BİÇİMİ
                        Görünen alan bir contenteditable div'dir; formda
                        gönderilen ise onun HTML'ini taşıyan gizli alandır.
                        JavaScript kapalıysa gizli alan yine gönderilir ve
                        mevcut içerik KAYBOLMAZ — bozuk bir editör yüzünden
                        sayfa boşalmasın diye.
                    */ ?>
                    <div class="cy-editor" data-cy-editor data-target="icerik">
                        <div class="cy-editor__toolbar" role="toolbar" aria-label="Biçimlendirme"></div>
                        <div class="cy-editor__area" contenteditable="true" role="textbox" aria-multiline="true"
                             data-placeholder="Sayfanın içeriğini buraya yazın…"><?= $icerik ?></div>
                        <div class="cy-editor__foot">
                            <span data-cy-editor-count>0 kelime</span>
                            <span>Kaydederken güvenli olmayan etiketler temizlenir</span>
                        </div>
                    </div>

                    <textarea name="icerik" id="icerik" class="d-none"><?= e($icerik) ?></textarea>

                    <?php if (isset($errors['icerik'])): ?>
                        <div class="invalid-feedback d-block mt-2"><?= e($errors['icerik']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================= SAĞ: YAYIN + SEO ================= -->
        <div class="col-12 col-xl-4">
            <div class="cy-card mb-3">
                <div class="cy-card__header">
                    <h2 class="cy-section-title mb-0"><?= icon('send', 'cy-icon cy-icon--sm') ?> Yayın</h2>
                </div>
                <div class="cy-card__body">
                    <label class="form-label" for="durum">Durum</label>
                    <select class="form-select mb-3" id="durum" name="durum">
                        <option value="taslak" <?= $durum !== 'yayin' ? 'selected' : '' ?>>Taslak — yalnızca panelde</option>
                        <option value="yayin"  <?= $durum === 'yayin' ? 'selected' : '' ?>>Yayında — herkes görebilir</option>
                    </select>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="menude" name="menude" value="1" <?= $menude ? 'checked' : '' ?>>
                        <label class="form-check-label" for="menude">Üst menüde göster</label>
                    </div>

                    <label class="form-label" for="sira">Sıra</label>
                    <input type="number" class="form-control" id="sira" name="sira" min="0" max="9999"
                           value="<?= e($deger('sira', (string) ($sayfa?->sira ?? 0))) ?>">
                    <div class="form-text mb-3">Küçük sayı menüde önce görünür.</div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn cy-btn cy-btn--primary">
                            <?= icon('save', 'cy-icon cy-icon--sm') ?> Kaydet
                        </button>

                        <?php if ($sayfa !== null && $sayfa->yayinda()): ?>
                            <a class="btn cy-btn cy-btn--ghost cy-btn--sm" href="<?= e(url($sayfa->slug)) ?>"
                               target="_blank" rel="noopener">
                                <?= icon('external', 'cy-icon cy-icon--sm') ?> Sitede Görüntüle
                            </a>
                        <?php endif; ?>

                        <a class="btn cy-btn cy-btn--ghost cy-btn--sm" href="<?= e(url('panel/sayfalar')) ?>">
                            <?= icon('chevron', 'cy-icon cy-icon--sm cy-flip') ?> Listeye Dön
                        </a>
                    </div>

                    <?php if ($sayfa !== null): ?>
                        <hr class="cy-divider">
                        <dl class="cy-detail cy-detail--compact mb-0">
                            <dt>Oluşturulma</dt> <dd><?= e(Page::formatDate($sayfa->createdAt)) ?></dd>
                            <dt>Son güncelleme</dt> <dd><?= e(Page::formatDate($sayfa->updatedAt)) ?></dd>
                            <?php if ($sayfa->yazarAdi !== null && trim($sayfa->yazarAdi) !== ''): ?>
                                <dt>Yazan</dt> <dd><?= e($sayfa->yazarAdi) ?></dd>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cy-card">
                <div class="cy-card__header">
                    <h2 class="cy-section-title mb-0"><?= icon('search', 'cy-icon cy-icon--sm') ?> Arama Motoru</h2>
                </div>
                <div class="cy-card__body">
                    <label class="form-label" for="seo_baslik">SEO Başlığı</label>
                    <input type="text" class="form-control mb-1" id="seo_baslik" name="seo_baslik" maxlength="190"
                           value="<?= e($deger('seo_baslik', $sayfa?->seoBaslik ?? '')) ?>"
                           placeholder="Boşsa sayfa başlığı kullanılır">
                    <div class="form-text mb-3">60 karakteri geçmemesi önerilir.</div>

                    <label class="form-label" for="seo_aciklama">SEO Açıklaması</label>
                    <textarea class="form-control mb-1" id="seo_aciklama" name="seo_aciklama" rows="3"
                              maxlength="255" placeholder="Boşsa özet ya da içeriğin başı kullanılır"><?= e($deger('seo_aciklama', $sayfa?->seoAciklama ?? '')) ?></textarea>
                    <div class="form-text">150–160 karakter arası ideal uzunluktur.</div>
                </div>
            </div>
        </div>
    </div>
</form>
