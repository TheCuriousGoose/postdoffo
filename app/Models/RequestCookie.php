<?php

namespace App\Models;

use Database\Factories\RequestCookieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One stored cookie, belonging to a single user within a single workspace.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property string $domain
 * @property string $path
 * @property string $name
 * @property string $value
 * @property Carbon|null $expires_at
 * @property bool $secure
 * @property bool $http_only
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'workspace_id', 'user_id', 'domain', 'path',
    'name', 'value', 'expires_at', 'secure', 'http_only',
])]
class RequestCookie extends Model
{
    /** @use HasFactory<RequestCookieFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // A session cookie is a credential in the same sense a token is.
            'value' => 'encrypted',
            'expires_at' => 'datetime',
            'secure' => 'boolean',
            'http_only' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forJar(Builder $query, int $workspaceId, int $userId): void
    {
        $query->where('workspace_id', $workspaceId)->where('user_id', $userId);
    }

    /**
     * Session cookies (no expiry) stay until something clears them, matching
     * how a browser treats them for the length of a browsing session.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unexpired(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
