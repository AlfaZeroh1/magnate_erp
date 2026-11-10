<?php
declare(strict_types=1);

/** Shared header + footer helpers for document pages. */
function doc_open(array $company, string $title, string $no = '', string $date = '', array $chips = [], string $badge = '', bool $void = false): void
{
    echo '<div class="page">';
    if ($void) echo '<div class="watermark">VOID</div>';
    $lines = [];
    if ($company['address']) $lines[] = nl2br(e($company['address']));
    if ($company['phone']) $lines[] = 'Cell: ' . e($company['phone']);
    foreach (array_filter([$company['email'], $company['email2'] ?? '']) as $em) $lines[] = 'E-mail: ' . e($em);
    if ($company['website']) $lines[] = 'Web: ' . e($company['website']);
    if ($company['pin']) $lines[] = 'PIN ' . e($company['pin']);
    echo '<header class="doc"><img src="' . e(doc_logo_url()) . '" alt="' . e($company['name']) . '"><div class="co"><strong>' . e($company['name']) . '</strong><br>'
        . implode('<br>', $lines) . '</div></header>';
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
        echo '<div class="box" style="border:0;padding:0"><h4>Bank details</h4><div class="banks">';
        foreach ($banks as $b) {
            $kv = ['Beneficiary bank' => $b['bank_name'], 'Branch' => $b['branch'], 'A/C name' => $b['account_name'], 'A/C no' => $b['account_number'],
                   'Swift code' => $b['swift_code'], 'Bank code' => $b['bank_code'] ?? '', 'Branch code' => $b['branch_code'] ?? '', 'Currency' => $b['currency']];
            echo '<div class="box"><div class="kv">';
            foreach ($kv as $k => $v) if ($v !== null && $v !== '') echo '<span>' . e($k) . '</span><span>' . e($v) . '</span>';
            echo '</div></div>';
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

/** Flat key from a free-text label, used to group document rows by category. */
function doc_group(?string $label, string $fallback = 'Other flowers'): string
{
    $label = trim((string)$label);
    return $label !== '' ? mb_strtoupper($label) : mb_strtoupper($fallback);
}
