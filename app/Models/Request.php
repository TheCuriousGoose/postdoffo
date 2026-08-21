<?php

namespace App\Models;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use Database\Factories\RequestFactory;
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
 * @property string $collection_id
 * @property string $name
 * @property HttpMethod $method
 * @property string $url
 * @property int $order
 * @property array<int, array{key: string, value: string, enabled?: bool}>|null $headers
 * @property array<int, array{key: string, value: string, enabled?: bool}>|null $query_params
 * @property array<string, mixed>|null $body
 * @property BodyType $body_type
 * @property AuthType|null $auth_type
 * @property array{token?: string, username?: string, password?: string, key?: string, value?: string, in?: string}|null $auth
 * @property string|null $pre_request_script
 * @property string|null $test_script
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'collection_id', 'name', 'method', 'url', 'order',
    'headers', 'query_params', 'body', 'body_type',
    'auth_type', 'auth', 'pre_request_script', 'test_script',
])]
class Request extends Model
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'body_type' => BodyType::class,
            'headers' => 'array',
            'query_params' => 'array',
            'body' => 'array',
            'auth_type' => AuthType::class,
            'auth' => 'array',
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
     * Files uploaded for this request's form-data body.
     *
     * @return HasMany<RequestFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(RequestFile::class);
    }

    protected static function booted(): void
    {
        // The database would cascade these rows away on its own, but going through
        // Eloquent is what gets the uploaded blobs off the disk with them.
        static::deleting(function (self $request): void {
            $request->files->each->delete();
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forCollection(Builder $query, string $collectionId): void
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
