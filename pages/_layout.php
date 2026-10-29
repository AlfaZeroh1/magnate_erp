<?php
/** @var string $content @var string $pageTitle @var string $pageIcon @var string $p @var array $user */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Fx;
use App\Core\Menu;

$missingRates = Fx::missingThisMonth();
$justLoggedIn = !empty($_SESSION['just_logged_in']);
unset($_SESSION['just_logged_in']);

$mg = [
    'csrf'  => Csrf::token(),
    'user'  => ['id' => (int)$user['id'], 'full_name' => $user['full_name'], 'theme' => $user['theme'], 'is_super' => (int)$user['is_super'] === 1, 'role' => $user['role_name']],
    'perms' => Auth::permissions(),
    'base'  => ['id' => Fx::baseId(), 'code' => Fx::baseCode()],
    'currencies'   => DB::all('SELECT id, code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code'),
    'stages'       => array_map(fn($m) => [$m[0], $m[1], $m[2]], stage_meta()),
    'missingRates' => $missingRates,
    'justLoggedIn' => $justLoggedIn,
    'company'      => setting('company_name', 'Magnate Flowers'),
];
$avatar = avatar_url($user);
$active = $p === 'order' ? 'orders' : ($p === 'freight_booking' ? 'freight' : ($p === 'report' || $p === 'order_journey' ? 'reports' : $p));
$attention = (int)DB::val("SELECT COUNT(*) FROM orders WHERE stage IN ('sourcing','confirmed','received','packing') AND departure_date <= CURDATE() + INTERVAL 1 DAY");
?><!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<?php require __DIR__ . '/_head.php'; ?>
<title><?= e($pageTitle) ?> · Magnate</title>
<link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
<script>
  // apply theme before paint to avoid a flash
  (function(){var t=<?= json_encode($user['theme']) ?>;if(t==='auto'){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-bs-theme',t);})();
</script>
</head>
<body>
<div id="mg-progress"></div>
<div class="mg-backdrop"></div>

<aside class="mg-sidebar" aria-label="Main navigation">
  <a class="mg-brand" href="index.php">
    <img src="assets/img/logo-mark.svg" alt="">
    <div><div class="name">MAGNATE</div><div class="tag">Flower Exporters</div></div>
  </a>
  <nav class="mg-nav">
    <?php foreach (Menu::visible() as [$group, $items]): ?>
      <?php if ($group): ?><div class="group"><?= e($group) ?></div><?php endif; ?>
      <?php foreach ($items as [$key, $label, $icon]): ?>
        <a href="index.php?p=<?= e($key) ?>" class="<?= $active === $key ? 'active' : '' ?>">
          <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
          <?php if ($key === 'exchange_rates' && $missingRates): ?><span class="badge rounded-pill text-bg-warning"><?= count($missingRates) ?></span><?php endif; ?>
          <?php if ($key === 'orders' && $attention): ?><span class="badge rounded-pill text-bg-light text-danger" title="Orders departing within a day that are not packed / invoiced"><?= $attention ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="mg-sidebar-foot">© <?= date('Y') ?> <?= e(setting('company_name', 'Magnate Flowers')) ?> · v2.0</div>
</aside>

<div class="mg-main">
  <header class="mg-topbar">
    <button class="btn btn-light btn-icon d-lg-none" data-toggle-sidebar aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
    <div class="min-w-0">
      <h1><?= e($pageTitle) ?></h1>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-light d-none d-md-inline-flex align-items-center gap-2 text-secondary" data-quick-nav>
        <i class="bi bi-search"></i><span class="small">Quick find</span><span class="cmd-k">Ctrl K</span>
      </button>
      <button class="btn btn-light btn-icon d-md-none" data-quick-nav aria-label="Search"><i class="bi bi-search"></i></button>
      <div class="dropdown">
        <button class="btn btn-light btn-icon position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
          <i class="bi bi-bell"></i><?php if ($missingRates || $attention): ?><span class="notif-dot"></span><?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end" style="width:320px">
          <div class="px-2 py-1 small fw-semibold text-secondary">Notifications</div>
          <?php if ($missingRates): ?>
            <a class="dropdown-item d-flex gap-2 align-items-start" href="#" data-fx-prompt>
              <i class="bi bi-currency-exchange text-warning mt-1"></i>
              <span><strong>Exchange rates missing</strong><br><span class="small text-secondary"><?= e(implode(', ', array_column($missingRates, 'code'))) ?> not set for <?= date('F') ?></span></span>
            </a>
          <?php endif; ?>
          <?php if ($attention): ?>
            <a class="dropdown-item d-flex gap-2 align-items-start" href="index.php?p=orders&due=1">
              <i class="bi bi-alarm text-danger mt-1"></i>
              <span><strong><?= $attention ?> order<?= $attention > 1 ? 's' : '' ?> departing soon</strong><br><span class="small text-secondary">Not yet invoiced</span></span>
            </a>
          <?php endif; ?>
          <?php if (!$missingRates && !$attention): ?><div class="dropdown-item-text small text-secondary py-3 text-center"><i class="bi bi-check2-circle"></i> All caught up</div><?php endif; ?>
        </div>
      </div>
      <button class="btn btn-light btn-icon" data-theme-toggle aria-label="Toggle theme"><i class="bi bi-moon-stars" data-theme-icon></i></button>
      <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2 ps-1 pe-2 rounded-pill" data-bs-toggle="dropdown">
          <?php if ($avatar): ?><img class="mg-avatar" src="<?= e($avatar) ?>" alt=""><?php else: ?><span class="mg-avatar"><?= e(initials($user['full_name'])) ?></span><?php endif; ?>
          <span class="d-none d-sm-inline small fw-semibold"><?= e(explode(' ', $user['full_name'])[0]) ?></span>
          <i class="bi bi-chevron-down small text-secondary"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="px-3 py-2"><div class="fw-semibold"><?= e($user['full_name']) ?></div><div class="small text-secondary"><?= e($user['role_name']) ?></div></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="index.php?p=profile"><i class="bi bi-person-circle me-2"></i>My profile</a></li>
          <li><a class="dropdown-item" href="index.php?p=profile#password"><i class="bi bi-key me-2"></i>Change password</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="mg-content fade-in">
    <?= $content ?>
  </main>
  <footer class="mg-footer d-flex justify-content-between flex-wrap gap-2">
    <span><?= e(setting('company_name', 'Magnate Flowers')) ?> ERP</span>
    <span>Signed in as <?= e($user['username']) ?></span>
  </footer>
</div>

<script>window.MG = <?= json_encode($mg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.3/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ($pageScripts ?? [] as $s): ?><script src="<?= asset('js/' . $s) ?>"></script><?php endforeach; ?>
<?php if (!empty($pageInline)): ?><script><?= $pageInline ?></script><?php endif; ?>
</body>
</html>
