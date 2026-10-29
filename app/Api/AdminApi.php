<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;
use App\Core\Perms;
use App\Core\Settings;
use App\Services\Upload;

final class AdminApi extends BaseApi
{
    private const SETTING_KEYS = ['company_name', 'company_tagline', 'company_address', 'company_email', 'company_phone', 'company_website', 'company_pin',
                                  'invoice_terms', 'origin_declaration', 'doc_accent', 'default_origin', 'volumetric_divisor'];

    protected function rules(): array
    {
        return [
            'users' => 'users.view', 'user_save' => fn($r) => Auth::require($r->int('id') ? 'users.edit' : 'users.create'),
            'user_status' => 'users.edit', 'user_reset' => 'users.edit', 'user_delete' => 'users.delete',
            'user_perms' => 'roles.view', 'user_perms_save' => 'roles.edit',
            'roles' => 'roles.view', 'role_save' => 'roles.edit', 'role_delete' => 'roles.edit', 'registry' => 'roles.view',
            'settings' => 'settings.view', 'settings_save' => 'settings.edit', 'logo_upload' => 'settings.edit',
            'activity' => 'activity.view',
        ];
    }

    protected function readOnly(): array
    {
        return ['users', 'user_perms', 'roles', 'registry', 'settings', 'activity'];
    }

    // ---------------- Users ----------------
    protected function doUsers(): array
    {
        return DB::all('SELECT u.id, u.full_name, u.username, u.email, u.phone, u.job_title, u.role_id, r.name AS role_name, u.status, u.avatar,
                               u.last_login_at, u.created_at, u.must_change_password,
                               (SELECT COUNT(*) FROM user_permissions up WHERE up.user_id = u.id) AS overrides
                          FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.status, u.full_name');
    }

    protected function doUserSave(): array
    {
        $id = $this->req->int('id');
        $d = [
            'full_name' => $this->req->str('full_name'), 'username' => strtolower($this->req->str('username')),
            'email' => strtolower($this->req->str('email')), 'phone' => $this->req->strOrNull('phone'),
            'job_title' => $this->req->strOrNull('job_title'), 'role_id' => $this->req->int('role_id'),
        ];
        $err = [];
        if ($d['full_name'] === '') $err['full_name'] = 'Name is required.';
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $d['username'])) $err['username'] = '3–60 characters: letters, numbers, dot, dash, underscore.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
        if (!DB::val('SELECT id FROM roles WHERE id = :id', ['id' => $d['role_id']])) $err['role_id'] = 'Choose a role.';
        if (DB::val('SELECT id FROM users WHERE username = :u AND id <> :id', ['u' => $d['username'], 'id' => $id])) $err['username'] = 'Username already taken.';
        if (DB::val('SELECT id FROM users WHERE email = :e AND id <> :id', ['e' => $d['email'], 'id' => $id])) $err['email'] = 'Email already used.';
        $pass = (string)$this->req->get('password', '');
        if (!$id && strlen($pass) < 8) $err['password'] = 'Set an initial password (min 8 characters).';
        if ($err) throw HttpException::validation($err);

        if ($id === Auth::id() && (int)DB::val('SELECT is_super FROM roles WHERE id = :r', ['r' => $d['role_id']]) !== 1 && Auth::isSuper()) {
            $this->ensureAnotherAdmin($id);
        }
        if ($id) {
            if ($pass !== '') {
                if (strlen($pass) < 8) throw HttpException::validation(['password' => 'Min 8 characters.']);
                $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
                $d['password_changed_at'] = date('Y-m-d H:i:s');
            }
            DB::update('users', $d, 'id = :id', ['id' => $id]);
            Log::write('user', $id, 'update', "Updated user {$d['username']}");
            return ['id' => $id, '_message' => 'User updated.'];
        }
        $d += ['password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'status' => 'active', 'must_change_password' => $this->req->bool('must_change') ? 1 : 0,
               'created_by' => Auth::id()];
        $id = DB::insert('users', $d);
        Log::write('user', $id, 'create', "Created user {$d['username']}");
        return ['id' => $id, '_message' => 'User created.'];
    }

    private function ensureAnotherAdmin(int $excludingId): void
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.is_super = 1 AND u.status = 'active' AND u.id <> :id", ['id' => $excludingId]);
        if ($n === 0) throw new HttpException('There must always be at least one active administrator.', 409);
    }

    protected function doUserStatus(): array
    {
        $id = $this->req->int('id');
        $status = $this->req->str('status') === 'active' ? 'active' : 'inactive';
        if ($id === Auth::id() && $status === 'inactive') throw new HttpException('You cannot deactivate yourself.', 409);
        if ($status === 'inactive') $this->ensureAnotherAdmin($id);
        DB::update('users', ['status' => $status], 'id = :id', ['id' => $id]);
        Log::write('user', $id, 'status', 'User ' . ($status === 'active' ? 'activated' : 'deactivated'));
        return ['_message' => 'User ' . ($status === 'active' ? 'activated.' : 'deactivated.')];
    }

    /** Generates a temporary password the admin passes on; user must change it at next sign-in. */
    protected function doUserReset(): array
    {
        $id = $this->req->int('id');
        $u = DB::one('SELECT username FROM users WHERE id = :id', ['id' => $id]);
        if (!$u) throw new HttpException('User not found.', 404);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $tmp = '';
        for ($i = 0; $i < 10; $i++) $tmp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        DB::update('users', ['password_hash' => password_hash($tmp, PASSWORD_DEFAULT), 'must_change_password' => 1, 'password_changed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        Log::write('user', $id, 'reset', "Password reset for {$u['username']}");
        return ['temporary_password' => $tmp];
    }

    protected function doUserDelete(): array
    {
        $id = $this->req->int('id');
        if ($id === Auth::id()) throw new HttpException('You cannot delete yourself.', 409);
        $this->ensureAnotherAdmin($id);
        if (DB::val('SELECT COUNT(*) FROM activity_log WHERE user_id = :id', ['id' => $id]) > 0) {
            throw new HttpException('This user has history in the system. Deactivate them instead so the audit trail stays intact.', 409);
        }
        DB::delete('users', 'id = :id', ['id' => $id]);
        Log::write('user', $id, 'delete', 'User deleted');
        return ['_message' => 'User deleted.'];
    }

    protected function doUserPerms(): array
    {
        $u = DB::one('SELECT u.id, u.full_name, u.role_id, r.name AS role_name, r.is_super FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id', ['id' => $this->req->int('id')]);
        if (!$u) throw new HttpException('User not found.', 404);
        return [
            'user' => $u,
            'role_perms' => DB::col('SELECT permission FROM role_permissions WHERE role_id = :r', ['r' => $u['role_id']]),
            'overrides' => DB::all('SELECT permission, allow FROM user_permissions WHERE user_id = :u', ['u' => $u['id']]),
        ];
    }

    /** overrides: {perm: 1 (grant) | 0 (revoke)}; anything not listed follows the role. */
    protected function doUserPermsSave(): array
    {
        $uid = $this->req->int('id');
        $over = $this->req->arr('overrides');
        DB::tx(function () use ($uid, $over) {
            DB::delete('user_permissions', 'user_id = :u', ['u' => $uid]);
            foreach ($over as $perm => $allow) {
                if (!Perms::isValid((string)$perm)) continue;
                DB::insert('user_permissions', ['user_id' => $uid, 'permission' => $perm, 'allow' => (int)$allow ? 1 : 0]);
            }
        });
        Log::write('user', $uid, 'rights', 'Updated personal rights (' . count($over) . ' override(s))');
        return ['_message' => 'Rights saved.'];
    }

    // ---------------- Roles ----------------
    protected function doRegistry(): array
    {
        return ['registry' => Perms::registry(), 'labels' => Perms::LABELS];
    }

    protected function doRoles(): array
    {
        $roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users FROM roles r ORDER BY r.is_super DESC, r.name');
        foreach ($roles as &$r) $r['permissions'] = DB::col('SELECT permission FROM role_permissions WHERE role_id = :r', ['r' => $r['id']]);
        return $roles;
    }

    protected function doRoleSave(): array
    {
        $id = $this->req->int('id');
        $name = $this->req->str('name');
        if ($name === '') throw HttpException::validation(['name' => 'Name is required.']);
        if (DB::val('SELECT id FROM roles WHERE name = :n AND id <> :id', ['n' => $name, 'id' => $id])) throw HttpException::validation(['name' => 'A role with this name exists.']);
        $perms = array_values(array_filter((array)$this->req->get('permissions', []), fn($p) => Perms::isValid((string)$p)));
        $id = DB::tx(function () use ($id, $name, $perms) {
            if ($id) {
                $role = DB::one('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
                if (!$role) throw new HttpException('Role not found.', 404);
                DB::update('roles', ['name' => $name, 'description' => $this->req->strOrNull('description')], 'id = :id', ['id' => $id]);
            } else {
                $id = DB::insert('roles', ['name' => $name, 'description' => $this->req->strOrNull('description')]);
            }
            if (!(int)DB::val('SELECT is_super FROM roles WHERE id = :id', ['id' => $id])) {
                DB::delete('role_permissions', 'role_id = :r', ['r' => $id]);
                foreach ($perms as $p) DB::insert('role_permissions', ['role_id' => $id, 'permission' => $p]);
            }
            Log::write('role', $id, 'save', "Role \"$name\" saved with " . count($perms) . ' right(s)');
            return $id;
        });
        return ['id' => $id, '_message' => 'Role saved.'];
    }

    protected function doRoleDelete(): array
    {
        $r = DB::one('SELECT * FROM roles WHERE id = :id', ['id' => $this->req->int('id')]);
        if (!$r) throw new HttpException('Role not found.', 404);
        if ((int)$r['is_system']) throw new HttpException('Built-in roles cannot be deleted.', 409);
        if (DB::val('SELECT COUNT(*) FROM users WHERE role_id = :id', ['id' => $r['id']]) > 0) throw new HttpException('Move the users in this role to another role first.', 409);
        DB::delete('roles', 'id = :id', ['id' => $r['id']]);
        Log::write('role', (int)$r['id'], 'delete', "Role \"{$r['name']}\" deleted");
        return ['_message' => 'Role deleted.'];
    }

    // ---------------- Settings ----------------
    protected function doSettings(): array
    {
        $all = Settings::all();
        return array_intersect_key($all, array_flip(self::SETTING_KEYS)) + ['logo' => is_file(ROOT . '/assets/img/logo.png') ? asset('img/logo.png') : null];
    }

    protected function doSettingsSave(): array
    {
        foreach (self::SETTING_KEYS as $k) {
            if ($this->req->has($k)) Settings::set($k, $this->req->str($k));
        }
        Log::write('settings', null, 'update', 'Company settings updated');
        return ['_message' => 'Settings saved.'];
    }

    /** Replace assets/img/logo.png (used on every document). */
    protected function doLogoUpload(): array
    {
        $file = $this->req->files['logo'] ?? null;
        $path = Upload::image($file, ROOT . '/assets/img', 'logo', 4096, ['png'], true);
        Log::write('settings', null, 'logo', 'Company logo replaced');
        return ['url' => asset('img/' . basename($path)), '_message' => 'Logo updated. It now appears on all documents.'];
    }

    // ---------------- Activity ----------------
    protected function doActivity(): array
    {
        $w = ['1=1']; $p = [];
        if ($u = $this->req->intOrNull('user_id')) { $w[] = 'al.user_id = :u'; $p['u'] = $u; }
        if ($e = $this->req->str('entity')) { $w[] = 'al.entity = :e'; $p['e'] = $e; }
        if ($f = $this->req->date('from')) { $w[] = 'al.created_at >= :f'; $p['f'] = $f . ' 00:00:00'; }
        if ($t = $this->req->date('to')) { $w[] = 'al.created_at <= :t'; $p['t'] = $t . ' 23:59:59'; }
        return DB::all('SELECT al.id, al.created_at, al.entity, al.action, al.description, al.order_id, al.ip, u.full_name AS user
                          FROM activity_log al LEFT JOIN users u ON u.id = al.user_id
                         WHERE ' . implode(' AND ', $w) . ' ORDER BY al.id DESC LIMIT 3000', $p);
    }
}
