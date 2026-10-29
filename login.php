<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Session;

if (Auth::check()) { header('Location: index.php'); exit; }

$error = Session::flash();
$login = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
// Only allow going back to a page of this app: keep just a safe query string.
$nextQuery = (string)(parse_url($next, PHP_URL_QUERY) ?? '');
$next = (str_ends_with((string)parse_url($next, PHP_URL_PATH), 'index.php') && preg_match('/^p=[a-z_]+(&[\w%.=-]*)*$/', $nextQuery)) ? $nextQuery : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify();
        $login = trim((string)($_POST['login'] ?? ''));
        [$ok, $msg] = Auth::attempt($login, (string)($_POST['password'] ?? ''));
        if ($ok) {
            header('Location: index.php' . ($next ? '?' . $next : ''));
            exit;
        }
        $error = $msg;
    } catch (HttpException $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<?php require __DIR__ . '/pages/_head.php'; ?>
<title>Sign in · Magnate</title>
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <section class="auth-art">
    <div class="d-flex align-items-center gap-3">
      <img src="assets/img/logo-mark.svg" width="48" height="48" alt="">
      <div><div class="font-brand fs-4 fw-bold" style="letter-spacing:.16em">MAGNATE</div><div class="small text-uppercase" style="letter-spacing:.22em;color:#fb7185">Flower Exporters</div></div>
    </div>
    <div style="max-width:520px;position:relative;z-index:1">
      <h1 class="mb-3">From the farm<br>to the world, in bloom.</h1>
      <p class="opacity-75 mb-4">One place to capture orders, source from farms, check deliveries, pack, invoice and see exactly what each shipment earned.</p>
      <ul class="list-unstyled pts opacity-75 small">
        <li><i class="bi bi-check2-circle me-2 text-success"></i>Order → farm orders → confirmation → delivery check → packing → invoice</li>
        <li><i class="bi bi-check2-circle me-2 text-success"></i>Statements, payments and multi-currency accounting</li>
        <li><i class="bi bi-check2-circle me-2 text-success"></i>Order journey &amp; profitability reports</li>
      </ul>
    </div>
    <div class="small opacity-50">© <?= date('Y') ?> <?= e(setting('company_name', 'Magnate Flowers Limited')) ?></div>
  </section>
  <section class="auth-form">
    <div class="auth-card">
      <div class="text-center d-lg-none mb-4"><img src="assets/img/logo.png" alt="Magnate" style="height:56px"></div>
      <h2 class="fw-bold mb-1">Welcome back</h2>
      <p class="text-secondary mb-4">Sign in to continue to your workspace.</p>
      <?php if ($error): ?><div class="alert alert-danger d-flex gap-2 align-items-center py-2"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="on" id="loginForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= $next ? e('index.php?' . $next) : '' ?>">
        <div class="mb-3">
          <label class="form-label" for="login">Username or email</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent"><i class="bi bi-person"></i></span>
            <input class="form-control" id="login" name="login" value="<?= e($login) ?>" required autofocus autocomplete="username">
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent"><i class="bi bi-lock"></i></span>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.firstElementChild.className=p.type==='password'?'bi bi-eye':'bi bi-eye-slash'"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button class="btn btn-primary btn-lg w-100" id="loginBtn"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
      </form>
      <p class="small text-secondary mt-4 text-center">Forgot your password? Ask an administrator to reset it from <em>Administration → Users</em>.</p>
    </div>
  </section>
</div>
<script>
  if (matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.setAttribute('data-bs-theme', 'dark');
  document.getElementById('loginForm').addEventListener('submit', function () {
    const b = document.getElementById('loginBtn'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in…';
  });
</script>
</body>
</html>
