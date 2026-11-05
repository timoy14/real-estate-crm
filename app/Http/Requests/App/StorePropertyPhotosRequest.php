<?php

namespace App\Http\Requests\App;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StorePropertyPhotosRequest extends FormRequest
{
    public const MAX_PIXELS = 25_000_000;

    public function authorize(): bool
    {
        return $this->user()->can('managePhotos', $this->route('property'));
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.Property::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', $this->maxPixels(...)],
        ];
    }

    /** Reads only the header (getimagesize), so a huge image is refused before GD decodes it. */
    private function maxPixels(string $attribute, mixed $file, \Closure $fail): void
    {
        $size = $file instanceof UploadedFile ? @getimagesize($file->getRealPath()) : false;
        if ($size && $size[0] * $size[1] > self::MAX_PIXELS) {
            $fail('This photo is too large (over 25 megapixels). Resize it and try again.');
        }
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $existing = $this->route('property')->photos()->count();
            $new = count((array) $this->file('photos'));
            if ($existing + $new > Property::MAX_PHOTOS) {
                $v->errors()->add('photos', 'A property can have at most '.Property::MAX_PHOTOS.' photos.');
            }
        }];
    }
}
