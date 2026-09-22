<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One trainee's mark against one lesson (read) or practice task (started,
 * then done — verified by the system where it can check the records).
 *
 * @property-read Carbon|null $started_at
 * @property-read Carbon|null $completed_at
 */
class TrainingProgress extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const LESSON = 'LESSON';

    public const TASK = 'TASK';

    protected $table = 'training_progress';

    protected $fillable = [
        'organisation_id', 'user_id', 'module_key', 'item_type', 'item_key',
        'started_at', 'completed_at', 'is_verified', 'verification_detail', 'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
