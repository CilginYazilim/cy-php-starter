<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Geliştirici hata ekranı
 * ---------------------------------------------------------------------
 *  SADECE APP_DEBUG=true iken basılır (bkz. ErrorHandler::renderDebug).
 *  Kaynak kodu, dosya yolları ve yığın izi içerir — yayında ASLA
 *  görünmemelidir.
 *
 *  Stil satır içidir: bu ekran, tema dosyaları yüklenemediğinde bile
 *  okunabilir kalmalıdır.
 *
 *  @var Throwable                  $exception
 *  @var array<int,string>          $snippet   satır no => kod
 *  @var array<int,array{konum:string,cagri:string}> $frames
 * =====================================================================
 */

use App\Core\ErrorHandler;

/** @var Throwable $exception */
$errorLine = $exception->getLine();

/* Zincirdeki önceki istisnalar: asıl sebep genelde en sondadır. */
$chain    = [];
$previous = $exception->getPrevious();

while ($previous !== null && count($chain) < 5) {
    $chain[]  = $previous;
    $previous = $previous->getPrevious();
}
?>

<div style="max-width:960px;margin:32px auto;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif">

    <div style="background:#7f1d1d;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0">
        <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.75">
            <?= e($exception::class) ?>
        </div>
        <h1 style="margin:6px 0 0;font-size:20px;line-height:1.4;font-weight:600;word-break:break-word">
            <?= e($exception->getMessage()) ?>
        </h1>
        <div style="margin-top:10px;font-size:13px;font-family:ui-monospace,Consolas,monospace;opacity:.85">
            <?= e(ErrorHandler::relative($exception->getFile())) ?>:<?= (int) $errorLine ?>
        </div>
    </div>

    <div style="background:#0f172a;color:#e2e8f0;padding:4px 0;border-radius:0 0 12px 12px;overflow-x:auto">
        <?php if ($snippet === []): ?>
            <p style="padding:16px 24px;margin:0;color:#94a3b8;font-size:13px">Kaynak dosya okunamadı.</p>
        <?php else: ?>
            <pre style="margin:0;padding:12px 0;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.6"><?php
                foreach ($snippet as $number => $code):
                    $isError = $number === $errorLine;
                    ?><div style="padding:0 24px;<?= $isError ? 'background:#7f1d1d;' : '' ?>"><span style="display:inline-block;width:52px;color:<?= $isError ? '#fecaca' : '#475569' ?>;user-select:none"><?= (int) $number ?></span><?= e($code) ?></div><?php
                endforeach;
            ?></pre>
        <?php endif; ?>
    </div>

    <?php if ($chain !== []): ?>
        <div style="margin-top:20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:16px 20px">
            <h2 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#9a3412">Önceki istisnalar (asıl sebep)</h2>
            <?php foreach ($chain as $index => $item): ?>
                <div style="padding:8px 0;<?= $index > 0 ? 'border-top:1px solid #fed7aa;' : '' ?>">
                    <div style="font-size:13px;color:#7c2d12;font-weight:600"><?= e($item::class) ?></div>
                    <div style="font-size:13px;color:#431407;word-break:break-word"><?= e($item->getMessage()) ?></div>
                    <div style="font-size:12px;color:#9a3412;font-family:ui-monospace,Consolas,monospace">
                        <?= e(ErrorHandler::relative($item->getFile())) ?>:<?= (int) $item->getLine() ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">
        <h2 style="margin:0;padding:14px 20px;font-size:14px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0">
            Yığın izi
        </h2>

        <?php if ($frames === []): ?>
            <p style="padding:16px 20px;margin:0;color:#64748b;font-size:13px">Yığın izi boş.</p>
        <?php else: ?>
            <ol style="margin:0;padding:8px 20px 16px 44px;font-size:13px;color:#334155">
                <?php foreach ($frames as $frame): ?>
                    <li style="padding:5px 0;word-break:break-word">
                        <span style="font-family:ui-monospace,Consolas,monospace;color:#0f172a"><?= e($frame['cagri']) ?></span><br>
                        <span style="font-family:ui-monospace,Consolas,monospace;color:#64748b;font-size:12px"><?= e($frame['konum']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <div style="margin-top:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px">
        <h2 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#0f172a">İstek</h2>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <?php
            $details = [
                'Yöntem'   => (string) ($_SERVER['REQUEST_METHOD'] ?? '-'),
                'Adres'    => (string) ($_SERVER['REQUEST_URI'] ?? '-'),
                'Rota'     => (string) ($_GET['r'] ?? '/'),
                'IP'       => (string) ($_SERVER['REMOTE_ADDR'] ?? '-'),
                'Oturum'   => isset($_SESSION['_auth_user_id']) ? '#' . (int) $_SESSION['_auth_user_id'] : 'yok',
                'PHP'      => PHP_VERSION,
                'Zaman'    => date('d.m.Y H:i:s'),
            ];

            foreach ($details as $label => $value):
                ?>
                <tr>
                    <td style="padding:5px 0;color:#64748b;width:110px;vertical-align:top"><?= e($label) ?></td>
                    <td style="padding:5px 0;color:#0f172a;font-family:ui-monospace,Consolas,monospace;word-break:break-all"><?= e($value) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <p style="margin:18px 2px 0;font-size:12px;color:#64748b">
        Bu ekran yalnızca <code>APP_DEBUG=true</code> iken görünür.
        Yayına alırken <code>.env</code> dosyasında <code>APP_DEBUG=false</code> yapın —
        aksi halde ziyaretçiler kaynak kodunuzu görebilir.
        Hatanın tam kaydı <code>storage/logs/error-<?= e(date('Y-m-d')) ?>.log</code> dosyasındadır.
    </p>
</div>
