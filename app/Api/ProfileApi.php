<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Log;
use App\Services\Upload;

/** Self-service: every signed-in user can manage their own profile. */
final class ProfileApi extends BaseApi
{
    protected function rules(): array
    {
        return ['update' => 'auth', 'password' => 'auth', 'avatar' => 'auth', 'avatar_remove' => 'auth', 'theme' => 'auth', 'activity' => 'auth'];
    }

    protected function readOnly(): array
    {
        return ['activity'];
    }

    protected function doUpdate(): array
    {
        $id = Auth::id();
        $d = ['full_name' => $this->req->str('full_name'), 'email' => strtolower($this->req->str('email')),
              'phone' => $this->req->strOrNull('phone'), 'job_title' => $this->req->strOrNull('job_title')];
        $err = [];
        if ($d['full_name'] === '') $err['full_name'] = 'Your name is required.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
        elseif (DB::val('SELECT id FROM users WHERE email = :e AND id <> :id', ['e' => $d['email'], 'id' => $id])) $err['email'] = 'Another user has this email.';
        if ($err) throw HttpException::validation($err);
        DB::update('users', $d, 'id = :id', ['id' => $id]);
        Log::write('user', $id, 'profile', 'Updated own profile');
        return ['_message' => 'Profile updated.'];
    }

    protected function doPassword(): array
    {
        $id = Auth::id();
        $hash = DB::val('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        $current = (string)$this->req->get('current_password', '');
        $new = (string)$this->req->get('new_password', '');
        $confirm = (string)$this->req->get('confirm_password', '');
        $err = [];
        if (!password_verify($current, (string)$hash)) $err['current_password'] = 'Current password is wrong.';
        if (strlen($new) < 8) $err['new_password'] = 'Use at least 8 characters.';
        elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $err['new_password'] = 'Use letters and at least one number.';
        elseif ($new === $current) $err['new_password'] = 'Choose a different password from the current one.';
        if ($new !== $confirm) $err['confirm_password'] = 'Passwords do not match.';
        if ($err) throw HttpException::validation($err);
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_changed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        session_regenerate_id(true);
        Log::write('user', $id, 'password', 'Changed own password');
        return ['_message' => 'Password changed.'];
    }

    protected function doAvatar(): array
    {
        $id = Auth::id();
        $path = Upload::image($this->req->files['avatar'] ?? null, ROOT . '/uploads/avatars', 'u' . $id, (int)config('uploads.avatar_max_kb', 2048));
        $old = DB::val('SELECT avatar FROM users WHERE id = :id', ['id' => $id]);
        DB::update('users', ['avatar' => basename($path)], 'id = :id', ['id' => $id]);
        if ($old && is_file(ROOT . '/uploads/avatars/' . basename((string)$old))) @unlink(ROOT . '/uploads/avatars/' . basename((string)$old));
        return ['url' => 'uploads/avatars/' . rawurlencode(basename($path)), '_message' => 'Photo updated.'];
    }

    protected function doAvatarRemove(): array
    {
        $id = Auth::id();
        $old = DB::val('SELECT avatar FROM users WHERE id = :id', ['id' => $id]);
        if ($old && is_file(ROOT . '/uploads/avatars/' . basename((string)$old))) @unlink(ROOT . '/uploads/avatars/' . basename((string)$old));
        DB::update('users', ['avatar' => null], 'id = :id', ['id' => $id]);
        return ['_message' => 'Photo removed.'];
    }

    protected function doTheme(): array
    {
        $t = $this->req->str('theme');
        if (!in_array($t, ['light', 'dark', 'auto'], true)) $t = 'auto';
        DB::update('users', ['theme' => $t], 'id = :id', ['id' => Auth::id()]);
        return ['theme' => $t];
    }

    protected function doActivity(): array
    {
        return DB::all("SELECT created_at, description, order_id FROM activity_log WHERE user_id = :u AND entity <> 'auth' ORDER BY id DESC LIMIT 15", ['u' => Auth::id()]);
    }
}
