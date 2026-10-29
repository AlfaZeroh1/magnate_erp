<?php
use App\Core\Resources;
$pageScripts = ['resource.js'];
$tabs = Resources::setupTabs();
?>
<div class="page-head">
  <div class="title"><div class="ico"><i class="bi bi-sliders"></i></div>
    <div><h2>Setup Lists</h2><div class="sub">Lengths, box types, marks, categories, drop points, countries, expense types and currencies.</div></div></div>
</div>
<ul class="nav nav-tabs-modern mb-3" id="setupTabs">
  <?php foreach ($tabs as $i => $t): $d = Resources::get($t); ?>
    <li class="nav-item"><a class="nav-link <?= $i === 0 ? 'active' : '' ?>" href="#" data-res="<?= e($t) ?>"><i class="bi <?= e($d['icon']) ?> me-1"></i><?= e($d['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
<div id="setupBody"></div>
<?php ob_start(); ?>
(function () {
  const $b = $('#setupBody');
  const show = (name) => { $b.off(); App.mountResource($b, name, { compact: true }); };
  $('#setupTabs').on('click', '[data-res]', function (e) { e.preventDefault(); $('#setupTabs .nav-link').removeClass('active'); $(this).addClass('active'); show($(this).data('res')); });
  show(<?= json_encode($tabs[0]) ?>);
})();
<?php $pageInline = ob_get_clean(); ?>
