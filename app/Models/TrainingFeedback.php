<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a trainee thought of a module: a 1–5 rating and what was unclear.
 * One per person per module; sending it again replaces the earlier one.
 */
class TrainingFeedback extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $table = 'training_feedback';

    protected $fillable = ['organisation_id', 'user_id', 'module_key', 'rating', 'comments'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
