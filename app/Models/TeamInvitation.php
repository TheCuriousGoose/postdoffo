<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $team_id
 * @property string $email
 * @property TeamRole $role
 * @property string $token
 * @property int $invited_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'email', 'role', 'invited_by_id'])]
class TeamInvitation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (TeamInvitation $invitation) {
            $invitation->token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }
}
