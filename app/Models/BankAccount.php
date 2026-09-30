<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the institution's own bank accounts: where a BANK, cheque or card
 * receipt was paid in. The ledger still posts to the Bank control account;
 * this row says which physical account the money landed in.
 */
class BankAccount extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'name', 'bank_name', 'account_number', 'account_name', 'branch_name', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
