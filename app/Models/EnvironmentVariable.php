<?php

namespace App\Models;

use Database\Factories\EnvironmentVariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $environment_id
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['environment_id', 'key', 'value', 'is_secret'])]
class EnvironmentVariable extends Model
{
    /** @use HasFactory<EnvironmentVariableFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Encrypted at rest so a database dump or backup doesn't hand over
            // the workspace's tokens. Every value, not just the is_secret ones —
            // that flag is a UI masking hint the user can forget to set, and
            // making it decide encryption would mean the forgetful case is the
            // unprotected one. Members of the workspace still see the plaintext;
            // this guards the database, not the people with access to it.
            'value' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
