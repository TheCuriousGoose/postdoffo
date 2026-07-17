<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $email
 * @property WorkspaceRole $role
 * @property string $token
 * @property int $invited_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workspace_id', 'email', 'role', 'invited_by_id'])]
class WorkspaceInvitation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvitation $invitation) {
            $invitation->token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
        ];
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
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }
}
