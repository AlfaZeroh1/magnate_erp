<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Log;
use App\Core\Session;

if (Auth::check()) Log::write('auth', Auth::id(), 'logout', 'Signed out');
Auth::logout();
Session::flash('You have been signed out.');
header('Location: login.php');
