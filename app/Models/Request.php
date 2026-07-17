<?php

namespace App\Models;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use Database\Factories\RequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $collection_id
 * @property string $name
 * @property HttpMethod $method
 * @property string $url
 * @property int $order
 * @property array<int, array{key: string, value: string, enabled?: bool}>|null $headers
 * @property array<int, array{key: string, value: string, enabled?: bool}>|null $query_params
 * @property array<string, mixed>|null $body
 * @property BodyType $body_type
 * @property string|null $pre_request_script
 * @property string|null $test_script
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'collection_id', 'name', 'method', 'url', 'order',
    'headers', 'query_params', 'body', 'body_type',
    'pre_request_script', 'test_script',
])]
class Request extends Model
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'body_type' => BodyType::class,
            'headers' => 'array',
            'query_params' => 'array',
            'body' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * @return HasMany<RequestHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(RequestHistory::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forCollection(Builder $query, int $collectionId): void
    {
        $query->where('collection_id', $collectionId);
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
