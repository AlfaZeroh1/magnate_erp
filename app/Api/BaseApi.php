<?php
declare(strict_types=1);

namespace App\Api;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Every API module extends this. Route "orders.save" calls OrdersApi::doSave()
 * after the permission declared in rules()['save'] has been checked.
 */
abstract class BaseApi
{
    public function __construct(protected Request $req) {}

    /**
     * action => permission string | 'auth' (any signed-in user) | callable(Request): void
     * Actions not listed here do not exist.
     */
    abstract protected function rules(): array;

    /** Actions that only read data may be called with GET. */
    protected function readOnly(): array
    {
        return [];
    }

    final public function dispatch(string $action, string $httpMethod)
    {
        $rules = $this->rules();
        $method = 'do' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $action)));
        if (!array_key_exists($action, $rules) || !method_exists($this, $method)) {
            throw new HttpException('Unknown action.', 404);
        }
        if ($httpMethod !== 'POST' && !in_array($action, $this->readOnly(), true)) {
            throw new HttpException('Method not allowed.', 405);
        }
        $rule = $rules[$action];
        if (is_callable($rule)) $rule($this->req);
        elseif ($rule !== 'auth') Auth::require($rule);
        return $this->$method();
    }

    protected function requireAny(array $perms): void
    {
        if (!Auth::canAny($perms)) throw new HttpException('You do not have the right to do this.', 403);
    }
}
