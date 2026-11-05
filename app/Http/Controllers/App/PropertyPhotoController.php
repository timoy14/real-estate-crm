<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\StorePropertyPhotosRequest;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyPhotoController extends Controller
{
    public function store(StorePropertyPhotosRequest $request, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $property) {
            // Lock the parent row so concurrent uploads cannot pass the 12-photo cap.
            Property::whereKey($property->id)->lockForUpdate()->first();
            $position = (int) $property->photos()->max('position') + ($property->photos()->exists() ? 1 : 0);

            foreach ($request->file('photos') as $file) {
                $path = $this->storeWithoutMetadata($file, "properties/{$property->id}");
                $property->photos()->create(['path' => $path, 'position' => $position++]);
            }
        });

        return back();
    }

    /**
     * Photos are public, so re-encode through GD: this drops EXIF (including GPS),
     * which could reveal an address hidden by hide_exact_address. The EXIF
     * Orientation is applied first so the image still displays upright.
     */
    private function storeWithoutMetadata(UploadedFile $file, string $dir): string
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($raw);

        if ($img === false) {
            throw ValidationException::withMessages(['photos' => 'One of the photos could not be processed.']);
        }

        $ext = match (strtolower($file->guessExtension() ?? '')) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        if ($ext === 'jpg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
            $img = $rotated ?: $img;
        }

        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        match ($ext) {
            'png' => imagepng($img),
            'webp' => imagewebp($img),
            default => imagejpeg($img, null, 90),
        };
        $bytes = (string) ob_get_clean();

        $path = $dir.'/'.Str::random(40).'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function destroy(Property $property, PropertyPhoto $photo): RedirectResponse
    {
        Gate::authorize('managePhotos', $property);
        abort_unless($photo->property_id === $property->id, 404);

        $photo->delete();
        Storage::disk('public')->delete($photo->path);

        return back();
    }

    public function order(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('managePhotos', $property);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $owned = $property->photos()->pluck('id')->all();

        if (array_diff($ids, $owned) !== []) {
            throw ValidationException::withMessages(['ids' => 'Some photos do not belong to this property.']);
        }

        DB::transaction(function () use ($ids, $property) {
            foreach ($ids as $i => $id) {
                $property->photos()->whereKey($id)->update(['position' => $i]);
            }
        });

        return back();
    }
}
