<?php
declare(strict_types=1);
/**
 * One-time installer:
 *   1. database connection -> writes app/config.php
 *   2. creates tables + seed data (and optionally imports a v1 database)
 *   3. creates the first administrator
 * Locks itself once an administrator exists.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Csrf;

$step = 1;
$error = '';
$notice = '';
$configPath = APP . '/config.php';
$hasConfig = is_file($configPath);

function pdo_from(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int)$db['port']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . str_replace('`', '', $db['name']) . '`');
    return $pdo;
}

function run_sql_file(PDO $pdo, string $file, array $replace = []): void
{
    $sql = file_get_contents($file);
    if ($replace) $sql = strtr($sql, $replace);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') $pdo->exec($stmt);
    }
}

$pdo = null;
if ($hasConfig) {
    try { $pdo = pdo_from(config('db')); } catch (Throwable $e) { $error = 'Cannot connect with app/config.php: ' . $e->getMessage(); }
}
$tablesExist = $pdo && (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetch();
$hasUsers = $tablesExist && (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

if ($hasUsers) {
    $step = 4;
} elseif ($tablesExist) {
    $step = 3;
} elseif ($pdo) {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step < 4) {
    try {
        Csrf::verify();
        $action = $_POST['action'] ?? '';

        if ($action === 'db' && !$hasConfig) {
            $db = ['host' => trim($_POST['host'] ?: '127.0.0.1'), 'port' => (int)($_POST['port'] ?: 3306), 'name' => trim($_POST['name'] ?: 'magnate'),
                   'user' => trim($_POST['user'] ?? ''), 'pass' => (string)($_POST['pass'] ?? ''), 'charset' => 'utf8mb4'];
            pdo_from($db);
            $cfg = require APP . '/config.sample.php';
            $cfg['db'] = $db;
            $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents($configPath, $php) === false) {
                $error = 'Connected, but app/config.php is not writable. Create it with the contents below, then reload.';
                $notice = $php;
            } else {
                header('Location: install.php'); exit;
            }
        }

        if ($action === 'schema' && $step === 2) {
            run_sql_file($pdo, ROOT . '/sql/schema.sql');
            run_sql_file($pdo, ROOT . '/sql/seed.sql');
            $legacy = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['legacy_db'] ?? ''));
            if ($legacy !== '') {
                run_sql_file($pdo, ROOT . '/sql/migrate_v1.sql', ['magnate_legacy' => $legacy]);
            }
            header('Location: install.php'); exit;
        }

        if ($action === 'admin' && $step === 3) {
            $name = trim($_POST['full_name'] ?? ''); $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? ''); $pass = (string)($_POST['password'] ?? '');
            if ($name === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Fill in name, username and a valid email.');
            if (strlen($pass) < 8) throw new RuntimeException('Password must be at least 8 characters.');
            $st = $pdo->prepare('INSERT INTO users (full_name, username, email, password_hash, role_id, status, password_changed_at) VALUES (?,?,?,?,1,\'active\',NOW())');
            $st->execute([$name, $username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            header('Location: login.php'); exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="en">
<head>
<?php require __DIR__ . '/pages/_head.php'; ?>
<title>Install · Magnate</title>
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center py-5" style="min-height:100vh">
<div class="card" style="width:100%;max-width:560px">
  <div class="card-body p-4">
    <div class="text-center mb-4"><img src="assets/img/logo.png" alt="Magnate" style="height:60px"></div>
    <div class="d-flex justify-content-between small text-secondary mb-3">
      <?php foreach (['Database', 'Tables', 'Administrator', 'Done'] as $i => $label): ?>
        <span class="<?= $step === $i + 1 ? 'text-primary fw-bold' : ($step > $i + 1 ? 'text-success' : '') ?>"><?= $step > $i + 1 ? '✓ ' : ($i + 1) . '. ' ?><?= $label ?></span>
      <?php endforeach; ?>
    </div>
    <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><pre class="small bg-light p-2 border rounded" style="max-height:240px"><?= e($notice) ?></pre><?php endif; ?>

    <?php if ($step === 1): ?>
      <h5 class="fw-bold">Connect to MySQL</h5>
      <form method="post" class="row g-3"><?= Csrf::field() ?><input type="hidden" name="action" value="db">
        <div class="col-8"><label class="form-label">Host</label><input class="form-control" name="host" value="127.0.0.1"></div>
        <div class="col-4"><label class="form-label">Port</label><input class="form-control" name="port" value="3306"></div>
        <div class="col-12"><label class="form-label">Database name (created if missing)</label><input class="form-control" name="name" value="magnate"></div>
        <div class="col-6"><label class="form-label">User</label><input class="form-control" name="user" value="root"></div>
        <div class="col-6"><label class="form-label">Password</label><input class="form-control" type="password" name="pass"></div>
        <div class="col-12"><button class="btn btn-primary w-100">Connect &amp; save</button></div>
      </form>
    <?php elseif ($step === 2): ?>
      <h5 class="fw-bold">Create tables</h5>
      <p class="small text-secondary">Creates the v2 schema and base data in <strong><?= e(config('db.name')) ?></strong>.</p>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="schema">
        <label class="form-label">Import data from your v1 database (optional)</label>
        <input class="form-control mb-1" name="legacy_db" placeholder="e.g. magnate_old">
        <div class="form-text mb-3">Import <code>sql/latest.sql</code> into a separate database first, then enter its name here. Customers, farms, varieties, prices, orders, packing lists, invoices, rates, expenses and users are carried over.</div>
        <button class="btn btn-primary w-100">Create tables</button>
      </form>
    <?php elseif ($step === 3): ?>
      <h5 class="fw-bold">Create the administrator</h5>
      <form method="post" class="row g-3"><?= Csrf::field() ?><input type="hidden" name="action" value="admin">
        <div class="col-12"><label class="form-label">Full name</label><input class="form-control" name="full_name" required></div>
        <div class="col-6"><label class="form-label">Username</label><input class="form-control" name="username" required></div>
        <div class="col-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
        <div class="col-12"><label class="form-label">Password (min 8)</label><input class="form-control" type="password" name="password" minlength="8" required></div>
        <div class="col-12"><button class="btn btn-primary w-100">Create &amp; go to sign in</button></div>
      </form>
    <?php else: ?>
      <div class="text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h5 class="fw-bold mt-2">Magnate is installed</h5>
        <p class="small text-secondary">For safety, delete or rename <code>install.php</code> on the live server.</p>
        <a href="login.php" class="btn btn-primary">Go to sign in</a>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
