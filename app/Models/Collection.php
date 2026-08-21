<?php

namespace App\Models;

use App\Enums\AuthType;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workspace_id
 * @property string|null $parent_id
 * @property string $name
 * @property int $order
 * @property array<string, string>|null $variables
 * @property array<int, array{key: string, value: string, enabled?: bool}>|null $headers
 * @property AuthType|null $auth_type
 * @property array{token?: string, username?: string, password?: string, key?: string, value?: string, in?: string}|null $auth
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workspace_id', 'parent_id', 'name', 'order', 'variables', 'headers', 'auth_type', 'auth'])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'headers' => 'array',
            'auth_type' => AuthType::class,
            'auth' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Deleting the subtree through Eloquent rather than letting the database
        // cascade do it means Request::deleting still runs for every descendant,
        // which is what clears their uploaded form-data files off the disk.
        static::deleting(function (self $collection): void {
            $collection->children->each->delete();
            $collection->requests->each->delete();
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    /**
     * @return HasMany<Request, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class)->ordered();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forWorkspace(Builder $query, string $workspaceId): void
    {
        $query->where('workspace_id', $workspaceId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('order')->orderBy('name');
    }
}
