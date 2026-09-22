<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossOrganisations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasUuids, SharedAcrossOrganisations;

    protected $fillable = ['code', 'name', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * A category's id with every sub-category beneath it, at any depth, so a
     * filter on "Drugs" also finds the antibiotics and antimalarials.
     *
     * @return list<string>
     */
    public static function withDescendantIds(string $categoryId): array
    {
        $childrenByParent = self::query()->whereNotNull('parent_id')->get(['id', 'parent_id'])
            ->groupBy(fn (self $category) => (string) $category->parent_id)
            ->map(fn ($children) => $children->map(fn (self $category) => (string) $category->id)->all());

        $ids = [];
        $pending = [$categoryId];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            array_push($pending, ...($childrenByParent[$id] ?? []));
        }

        return $ids;
    }
}
