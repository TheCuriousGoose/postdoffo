<?php

namespace App\Actions;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns an uploaded picture into the user's avatar.
 *
 * The browser hands us an already-cropped square, but nothing stops a client
 * from posting something else entirely, so the file is decoded and re-encoded
 * here rather than stored as-is: that squares it, caps it at AVATAR_SIZE, and
 * drops every byte that isn't pixels — EXIF, colour profiles, and anything
 * smuggled into a chunk of a file that a browser might later sniff as markup.
 */
class StoreUserAvatarAction
{
    /** Stored edge length, in pixels. Retina-sharp at the sizes we render. */
    public const AVATAR_SIZE = 512;

    /** JPEG quality of the stored file. */
    private const QUALITY = 88;

    public function handle(User $user, UploadedFile $file): void
    {
        $image = $this->decode($file);

        try {
            $square = $this->cropToSquare($image);
        } finally {
            imagedestroy($image);
        }

        try {
            $encoded = $this->encode($square);
        } finally {
            imagedestroy($square);
        }

        $previous = $user->avatar_path;

        // Fresh name per upload: the URL is cacheable forever, and a replaced
        // picture can't be served from a cache under the old path.
        $path = 'avatars/'.$user->id.'/'.Str::ulid()->toBase32().'.jpg';

        Storage::disk(User::AVATAR_DISK)->put($path, $encoded);

        $user->forceFill(['avatar_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk(User::AVATAR_DISK)->delete($previous);
        }
    }

    /**
     * Read the upload into GD. Validation has already established that this is
     * an image of a type we accept; a failure here means the file is corrupt or
     * uses an encoding this GD build wasn't compiled with (WebP, typically).
     */
    private function decode(UploadedFile $file): GdImage
    {
        $contents = @file_get_contents($file->getRealPath());

        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'avatar' => __('That image could not be read. Try a JPEG or PNG.'),
            ]);
        }

        return $image;
    }

    /**
     * Centre-crop to a square and scale it down to AVATAR_SIZE. A picture
     * smaller than that keeps its own size — upscaling only costs bytes.
     */
    private function cropToSquare(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $size = min($side, self::AVATAR_SIZE);

        $canvas = imagecreatetruecolor($size, $size);

        // JPEG has no alpha, so transparency has to land on something: white,
        // which reads as "no background" against every avatar surface we have.
        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white !== false) {
            imagefill($canvas, 0, 0, $white);
        }

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            (int) (($width - $side) / 2),
            (int) (($height - $side) / 2),
            $size,
            $size,
            $side,
            $side,
        );

        return $canvas;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
