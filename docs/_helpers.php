<?php
declare(strict_types=1);

/** Shared header + footer helpers for document pages. */
function doc_open(array $company, string $title, string $no = '', string $date = '', array $chips = [], string $badge = '', bool $void = false): void
{
    echo '<div class="page">';
    if ($void) echo '<div class="watermark">VOID</div>';
    echo '<header class="doc"><img src="assets/img/logo.png" alt="' . e($company['name']) . '"><div class="co"><strong>' . e($company['name']) . '</strong><br>'
        . nl2br(e($company['address'])) . '<br>' . e($company['phone']) . ($company['email'] ? ' · ' . e($company['email']) : '')
        . ($company['website'] ? '<br>' . e($company['website']) : '') . ($company['pin'] ? '<br>PIN ' . e($company['pin']) : '') . '</div></header>';
    echo '<div class="titlebar"><h1>' . e($title) . ($badge ? ' <span class="badge">' . e($badge) . '</span>' : '') . '</h1><div class="chips">';
    if ($no) echo '<span class="chip">No. <b>' . e($no) . '</b></span>';
    if ($date) echo '<span class="chip">Date <b>' . e($date) . '</b></span>';
    foreach ($chips as $k => $v) if ($v !== null && $v !== '') echo '<span class="chip">' . e($k) . ' <b>' . e($v) . '</b></span>';
    echo '</div></div>';
}

function doc_close(array $company, array $signatures = [], array $banks = [], string $note = ''): void
{
    echo '<div class="spacer"></div>';
    if ($note) echo '<div class="note">' . nl2br(e($note)) . '</div>';
    if ($banks) {
        echo '<div class="box" style="border:0;padding:0"><h4>Payment details</h4><div class="banks">';
        foreach ($banks as $b) {
            echo '<div class="box"><strong>' . e($b['bank_name']) . '</strong>' . ($b['branch'] ? ' · ' . e($b['branch']) : '') . ($b['currency'] ? ' · ' . e($b['currency']) : '')
                . '<br>' . e($b['account_name']) . '<br>A/C <strong>' . e($b['account_number']) . '</strong>' . ($b['swift_code'] ? '<br>SWIFT ' . e($b['swift_code']) : '') . '</div>';
        }
        echo '</div></div>';
    }
    if ($signatures) {
        echo '<div class="sign">';
        foreach ($signatures as $s) echo '<div>' . e($s) . '</div>';
        echo '</div>';
    }
    echo '<footer class="doc"><span>' . e($company['name']) . ($company['tagline'] ? ' — ' . e($company['tagline']) : '') . '</span><span>Printed ' . date('d M Y H:i') . ' · Magnate ERP</span></footer></div>';
}
