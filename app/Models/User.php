<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $avatar_path
 * @property int|null $last_workspace_id
 * @property UserRole $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar
 */
#[Appends(['avatar'])]
#[Fillable(['name', 'email', 'password', 'provider', 'provider_id', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements OAuthenticatable, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Ensures new instances are non-admin before their first save, since
     * the "role" column's DB default isn't hydrated back onto the model
     * until it's re-fetched.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
    ];

    /**
     * Disk holding uploaded profile pictures. The private one, handed back out
     * by AvatarController::show — that keeps self-hosted installs working with
     * no `storage:link` step. The file name is always a generated ULID, never
     * anything the uploader supplied.
     */
    public const AVATAR_DISK = 'local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A deleted account leaves nothing of itself on disk.
        static::deleted(function (self $user): void {
            $user->deleteAvatarFile();
        });
    }

    /**
     * The URL of the user's profile picture, or null when they haven't set one.
     * Shared with the front end on every page load, hence the appended attribute.
     */
    public function getAvatarAttribute(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        // Relative, so a deployment behind a proxy or on a second hostname still
        // points pictures at itself rather than at whatever APP_URL happens to say.
        return route('profile.avatar.show', [
            'user' => $this->id,
            'file' => basename($this->avatar_path),
        ], absolute: false);
    }

    /**
     * Remove the stored avatar file. A no-op for users without one.
     */
    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path !== null) {
            Storage::disk(self::AVATAR_DISK)->delete($this->avatar_path);
        }
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function lastWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'last_workspace_id');
    }
}
