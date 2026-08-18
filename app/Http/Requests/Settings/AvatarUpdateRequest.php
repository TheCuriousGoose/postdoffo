<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class AvatarUpdateRequest extends FormRequest
{
    /**
     * Ceiling for an uploaded profile picture, in kilobytes. The editor sends a
     * cropped 512px JPEG that lands far below this — the headroom is for clients
     * that post the original file instead.
     */
    public const MAX_KILOBYTES = 8192;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                // Rejects SVG, which is a document that can carry script, not a picture.
                'image',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif',
                'max:'.self::MAX_KILOBYTES,
                // The lower bound keeps unusable thumbnails out; the upper one stops a
                // small file from expanding into gigabytes of pixels once decoded.
                'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.dimensions' => __('Profile pictures must be at least 64x64 pixels and no larger than 8000x8000.'),
            'avatar.mimetypes' => __('Profile pictures must be a JPEG, PNG, WebP or GIF image.'),
        ];
    }

    /**
     * The uploaded picture, typed for callers.
     */
    public function picture(): UploadedFile
    {
        $file = $this->file('avatar');

        assert($file instanceof UploadedFile);

        return $file;
    }
}
