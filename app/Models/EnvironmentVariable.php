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
