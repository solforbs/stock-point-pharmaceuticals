<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Part 16.4 (V6) — one issued version of a controlled document. Staff
 * acknowledge a specific version, so a new version starts from zero.
 */
class DocumentVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'controlled_document_id', 'version', 'file_path', 'file_name', 'content_json', 'change_summary', 'effective_date', 'uploaded_by',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d', 'content_json' => 'array'];
    }

    /**
     * @return BelongsTo<ControlledDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ControlledDocument::class, 'controlled_document_id');
    }

    /**
     * @return HasMany<DocumentAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
