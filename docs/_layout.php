<?php
/** @var string $body @var string $docTitle @var string $docNo @var string $docDate @var array $company @var string $accent @var array $banks */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle . ($docNo ? ' ' . $docNo : '')) ?> · <?= e($company['name']) ?></title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --accent: <?= e($accent) ?>; --green: #14532d; --ink: #0f172a; --muted: #64748b; --line: #e5e7eb; --soft: #f8fafc; }
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #e9edf2; color: var(--ink); font: 12px/1.45 'Inter', system-ui, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .toolbar { position: sticky; top: 0; z-index: 10; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0a2e22; }
  .toolbar button, .toolbar a { border: 0; border-radius: 8px; padding: 8px 16px; font: 600 13px Inter, sans-serif; cursor: pointer; text-decoration: none; }
  .toolbar .primary { background: var(--accent); color: #fff; } .toolbar .ghost { background: rgba(255,255,255,.12); color: #fff; }
  .page { width: <?= $landscape ? '297mm' : '210mm' ?>; min-height: <?= $landscape ? '210mm' : '297mm' ?>; margin: 18px auto; background: #fff; padding: 14mm 14mm 12mm; box-shadow: 0 10px 40px rgba(15,23,42,.12); position: relative; display: flex; flex-direction: column; }
  .page::before { content: ''; position: absolute; left: 0; top: 0; right: 0; height: 6px; background: linear-gradient(90deg, var(--green), var(--accent)); }
  header.doc { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
  header.doc img { height: 58px; }
  header.doc .co { text-align: right; font-size: 11px; color: var(--muted); line-height: 1.55; }
  header.doc .co strong { color: var(--ink); font-size: 13px; }
  .titlebar { display: flex; justify-content: space-between; align-items: flex-end; margin: 18px 0 14px; gap: 16px; flex-wrap: wrap; }
  .titlebar h1 { font: 700 26px/1 'Fraunces', Georgia, serif; letter-spacing: .04em; margin: 0; color: var(--green); text-transform: uppercase; }
  .titlebar .chips { display: flex; gap: 8px; flex-wrap: wrap; }
  .chip { background: var(--soft); border: 1px solid var(--line); border-radius: 999px; padding: 4px 12px; font-size: 11px; }
  .chip b { color: var(--accent); }
  .badge { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; background: #fee2e2; color: #b91c1c; }
  .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 14px; }
  .grid.g3 { grid-template-columns: repeat(3, 1fr); }
  .box { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; background: #fff; }
  .box h4 { margin: 0 0 6px; font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); }
  .box .big { font-weight: 700; font-size: 14px; }
  .kv { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; font-size: 11px; }
  .kv span:nth-child(odd) { color: var(--muted); } .kv span:nth-child(even) { font-weight: 600; text-align: right; }
  table.t { width: 100%; border-collapse: separate; border-spacing: 0; margin: 4px 0 12px; font-size: 11px; }
  table.t thead th { background: var(--green); color: #fff; font-weight: 600; text-align: left; padding: 7px 8px; font-size: 10px; letter-spacing: .04em; text-transform: uppercase; }
  table.t thead th:first-child { border-top-left-radius: 8px; } table.t thead th:last-child { border-top-right-radius: 8px; }
  table.t td { padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
  table.t tbody tr:nth-child(even) td { background: var(--soft); }
  table.t tr.group td { background: #fdf2f8 !important; font-weight: 700; color: var(--accent); }
  table.t tfoot td { font-weight: 700; border-top: 2px solid var(--green); border-bottom: 0; padding-top: 8px; }
  .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; } .c { text-align: center; }
  .muted { color: var(--muted); } .neg { color: #dc2626; font-weight: 600; } .pos { color: #16a34a; font-weight: 600; }
  .fill { border-bottom: 1px dotted #94a3b8; min-width: 60px; display: inline-block; height: 14px; }
  .totals { margin-left: auto; width: 46%; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
  .totals div { display: flex; justify-content: space-between; padding: 6px 12px; font-size: 11.5px; }
  .totals div + div { border-top: 1px solid var(--line); }
  .totals .grand { background: var(--green); color: #fff; font-size: 14px; font-weight: 700; padding: 9px 12px; }
  .words { font-size: 10.5px; color: var(--muted); margin-top: 6px; text-align: right; font-style: italic; }
  .banks { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 6px; }
  .banks .box { font-size: 10px; padding: 8px 10px; }
  .note { font-size: 10.5px; color: var(--muted); margin: 10px 0; }
  .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 26px; }
  .sign div { border-top: 1px solid var(--ink); padding-top: 4px; font-size: 10px; color: var(--muted); }
  .spacer { flex: 1; }
  footer.doc { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; font-size: 9.5px; color: var(--muted); }
  .pagebreak { page-break-after: always; break-after: page; }
  .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font: 700 120px 'Fraunces', serif; color: rgba(220,38,38,.08); transform: rotate(-24deg); pointer-events: none; }
  @page { size: <?= $landscape ? 'A4 landscape' : 'A4' ?>; margin: 0; }
  @media print {
    html, body { background: #fff; }
    .toolbar { display: none; }
    .page { margin: 0; box-shadow: none; width: auto; min-height: <?= $landscape ? '209mm' : '296mm' ?>; }
  }
  @media screen and (max-width: 840px) { .page { width: 100%; margin: 0; padding: 16px; min-height: 0; } .grid, .grid.g3, .banks { grid-template-columns: 1fr; } .totals { width: 100%; } .table-wrap { overflow-x: auto; } }
</style>
</head>
<body>
<div class="toolbar">
  <button class="primary" onclick="window.print()">🖨  Print / Save as PDF</button>
  <a class="ghost" href="javascript:history.length > 1 ? history.back() : window.close()">Close</a>
</div>
<?= $body ?>
</body>
</html>
