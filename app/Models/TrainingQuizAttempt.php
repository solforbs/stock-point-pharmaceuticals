<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sitting of a module's knowledge check, scored by the server. Every
 * attempt is kept; the best one counts.
 */
class TrainingQuizAttempt extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'user_id', 'module_key', 'correct', 'total', 'score_pct', 'passed', 'answers',
    ];

    /** The chosen options are for managers' review, not for the trainee's next try. */
    protected $hidden = ['answers'];

    protected function casts(): array
    {
        return [
            'correct' => 'integer',
            'total' => 'integer',
            'score_pct' => 'integer',
            'passed' => 'boolean',
            'answers' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
