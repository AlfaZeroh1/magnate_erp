<?php
declare(strict_types=1);

namespace App\Api;

use App\Services\StatementService;

final class StatementsApi extends BaseApi
{
    protected function rules(): array
    {
        return ['get' => 'statements.view', 'balances' => 'statements.view'];
    }

    protected function readOnly(): array
    {
        return ['get', 'balances'];
    }

    protected function doGet(): array
    {
        return StatementService::build($this->req->str('type'), $this->req->int('id'), $this->req->date('from'), $this->req->date('to'), $this->req->intOrNull('currency_id'));
    }

    protected function doBalances(): array
    {
        $type = $this->req->str('type') ?: 'customer';
        if (!isset(StatementService::TYPES[$type])) $type = 'customer';
        return StatementService::balances($type, $this->req->date('as_of'));
    }
}
