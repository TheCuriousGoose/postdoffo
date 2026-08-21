<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Services\VariableResolver;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property int $owner_id
 * @property string|null $team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $role Not a column — set on the fly by WorkspaceController::index()
 *                             from roleFor() so the frontend gets each workspace's role without a second lookup.
 */
#[Fillable(['name'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The team this workspace belongs to, if any — null for a standalone
     * workspace. Belonging to a team is what makes every one of that team's
     * members count toward {@see roleFor()} below.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<User, $this, WorkspaceMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The best access this user has here, blending two independent sources: an
     * explicit per-workspace membership (someone invited directly, whether or
     * not this workspace is in a team), and whatever role their team
     * membership grants on every workspace the team owns. Higher of the two
     * wins — an org admin who was also individually invited as a viewer still
     * gets the co-owner-equivalent access their team role carries.
     */
    public function roleFor(User $user): ?WorkspaceRole
    {
        if ($this->owner_id === $user->id) {
            return WorkspaceRole::Owner;
        }

        $pivot = $this->members()->where('user_id', $user->id)->first()?->pivot;
        $explicit = $pivot?->role;

        $team = $this->team_id === null ? null : ($this->relationLoaded('team') ? $this->team : $this->team()->first());
        $viaTeam = $team?->roleFor($user)?->asWorkspaceRole();

        return match (true) {
            $explicit !== null && $viaTeam !== null => WorkspaceRole::higherOf($explicit, $viaTeam),
            default => $explicit ?? $viaTeam,
        };
    }

    /**
     * @return HasMany<Collection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * @return HasMany<Environment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /**
     * Workspace-level "globals" — the lowest-precedence variable layer, applied
     * whatever environment is active. See {@see VariableResolver}.
     *
     * @return HasMany<WorkspaceVariable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(WorkspaceVariable::class);
    }

    /**
     * @return HasMany<RequestHistory, $this>
     */
    public function requestHistory(): HasMany
    {
        return $this->hasMany(RequestHistory::class);
    }

    /**
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }
}
