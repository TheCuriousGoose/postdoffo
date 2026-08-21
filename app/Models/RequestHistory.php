<?php

namespace App\Models;

use Database\Factories\RequestHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $request_id
 * @property string $workspace_id
 * @property int|null $user_id
 * @property string $method
 * @property string $url
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $response_snapshot
 * @property Carbon $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'request_id', 'workspace_id', 'user_id', 'method', 'url',
    'status_code', 'duration_ms', 'response_snapshot', 'executed_at',
])]
class RequestHistory extends Model
{
    /** @use HasFactory<RequestHistoryFactory> */
    use HasFactory;

    protected $table = 'request_history';

    protected function casts(): array
    {
        return [
            // A snapshot holds the whole response body, which for a login call is
            // the access token itself — the same secret an environment variable
            // gets encrypted for, just arriving by a different route.
            'response_snapshot' => 'encrypted:array',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    protected function recent(Builder $query): void
    {
        $query->orderByDesc('executed_at');
    }
}
