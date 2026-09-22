<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read string $quote_id
 * @property-read int $user_id
 * @property-read array<string, mixed> $payload_json
 * @property-read array<string, mixed> $response_json
 * @property-read Carbon $expires_at
 */
class PriceQuoteLog extends Model
{
    use BelongsToTenantBranch, HasUuids;

    public $timestamps = false; // immutable — created_at is set explicitly once

    protected $fillable = [
        'quote_id', 'user_id', 'customer_id', 'branch_id', 'sale_mode',
        'payload_json', 'response_json', 'expires_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'response_json' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('price_quote_logs is immutable — records are never updated.');
    }

    public function delete(): ?bool
    {
        throw new \LogicException('price_quote_logs is immutable — records are never deleted.');
    }
}
