<?php

namespace App\Models;

use Database\Factories\RequestFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file uploaded for a request's form-data body. `path` points at the private
 * local disk — these are never publicly served; the only way back out is
 * RequestFileController::show(), which re-checks workspace access.
 *
 * @property int $id
 * @property string $request_id
 * @property string $filename
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['request_id', 'filename', 'path', 'mime_type', 'size'])]
class RequestFile extends Model
{
    /** @use HasFactory<RequestFileFactory> */
    use HasFactory;

    /** Disk holding every uploaded form-data file. */
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keeps the disk in step with the table. Request and Collection delete their
        // children through Eloquent rather than leaning on the database cascade, so
        // this fires for a deleted request or folder too, not just a removed field.
        static::deleted(function (self $file): void {
            Storage::disk(self::DISK)->delete($file->path);
        });
    }

    /**
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forRequest(Builder $query, string $requestId): void
    {
        $query->where('request_id', $requestId);
    }
}
