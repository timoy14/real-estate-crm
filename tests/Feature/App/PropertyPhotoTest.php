<?php

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
    $this->property = Property::factory()->for($this->anna, 'listingAgent')->create();
});

function img(string $name = 'a.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 200, 200);
}

test('listing agent uploads 2 photos stored with positions 0,1', function () {
    $this->actingAs($this->anna)
        ->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img('a.jpg'), img('b.png')]])
        ->assertRedirect();

    $photos = $this->property->photos()->get();
    expect($photos)->toHaveCount(2)->and($photos->pluck('position')->all())->toBe([0, 1]);
    foreach ($photos as $p) {
        expect($p->path)->toStartWith("properties/{$this->property->id}/");
        Storage::disk('public')->assertExists($p->path);
    }
    expect($this->property->fresh()->coverUrl())->toBe(Storage::disk('public')->url($photos[0]->path));
});

test('rejects a 13th photo', function () {
    for ($i = 0; $i < 11; $i++) {
        PropertyPhoto::create(['property_id' => $this->property->id, 'path' => "properties/x/$i.jpg", 'position' => $i]);
    }
    $this->actingAs($this->anna)->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img('a.jpg')]])
        ->assertSessionHasNoErrors();
    expect($this->property->photos()->count())->toBe(12);

    $this->actingAs($this->anna)->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img('b.jpg')]])
        ->assertSessionHasErrors('photos');
    expect($this->property->photos()->count())->toBe(12);
});

test('rejects a .gif', function () {
    $this->actingAs($this->anna)->post("/app/properties/{$this->property->slug}/photos", [
        'photos' => [UploadedFile::fake()->image('a.gif')],
    ])->assertSessionHasErrors('photos.0');
    expect($this->property->photos()->count())->toBe(0);
});

test('non-listing agent gets 403', function () {
    $photo = PropertyPhoto::create(['property_id' => $this->property->id, 'path' => 'properties/x/1.jpg', 'position' => 0]);

    $this->actingAs($this->ben)->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img()]])->assertForbidden();
    $this->actingAs($this->ben)->delete("/app/properties/{$this->property->slug}/photos/{$photo->id}")->assertForbidden();
    $this->actingAs($this->ben)->patch("/app/properties/{$this->property->slug}/photos/order", ['ids' => [$photo->id]])->assertForbidden();
    expect($this->property->photos()->count())->toBe(1);
});

test('reorder sets positions from ids order', function () {
    $a = PropertyPhoto::create(['property_id' => $this->property->id, 'path' => 'p/a.jpg', 'position' => 0]);
    $b = PropertyPhoto::create(['property_id' => $this->property->id, 'path' => 'p/b.jpg', 'position' => 1]);
    $c = PropertyPhoto::create(['property_id' => $this->property->id, 'path' => 'p/c.jpg', 'position' => 2]);

    $this->actingAs($this->anna)->patch("/app/properties/{$this->property->slug}/photos/order", ['ids' => [$c->id, $a->id, $b->id]])
        ->assertRedirect();
    expect($this->property->photos()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
});

test('reorder rejects ids from another property', function () {
    $other = Property::factory()->for($this->anna, 'listingAgent')->create();
    $x = PropertyPhoto::create(['property_id' => $other->id, 'path' => 'p/x.jpg', 'position' => 0]);
    $a = PropertyPhoto::create(['property_id' => $this->property->id, 'path' => 'p/a.jpg', 'position' => 0]);

    $this->actingAs($this->anna)->patch("/app/properties/{$this->property->slug}/photos/order", ['ids' => [$x->id, $a->id]])
        ->assertSessionHasErrors('ids');
    expect($x->fresh()->position)->toBe(0);
});

test('delete removes the file', function () {
    $this->actingAs($this->anna)->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img()]]);
    $photo = $this->property->photos()->first();
    Storage::disk('public')->assertExists($photo->path);

    $this->actingAs($this->anna)->delete("/app/properties/{$this->property->slug}/photos/{$photo->id}")->assertRedirect();
    Storage::disk('public')->assertMissing($photo->path);
    expect(PropertyPhoto::count())->toBe(0);
});

test('cannot delete a photo through a different property', function () {
    $other = Property::factory()->for($this->anna, 'listingAgent')->create();
    $photo = PropertyPhoto::create(['property_id' => $other->id, 'path' => 'p/x.jpg', 'position' => 0]);
    $this->actingAs($this->anna)->delete("/app/properties/{$this->property->slug}/photos/{$photo->id}")->assertNotFound();
});

test('uploaded jpeg has its EXIF/GPS metadata stripped', function () {
    $agent = User::factory()->create();
    $p = Property::factory()->for($agent, 'listingAgent')->create();

    // Build a JPEG and splice in an APP1 Exif segment carrying a GPS-looking payload.
    $im = imagecreatetruecolor(20, 20);
    ob_start();
    imagejpeg($im);
    $jpeg = ob_get_clean();
    $exif = "Exif\0\0"."MM\0*\0\0\0\x08\0\0GPS-SECRET-LOCATION";
    $seg = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $withExif = substr($jpeg, 0, 2).$seg.substr($jpeg, 2);
    $tmp = tempnam(sys_get_temp_dir(), 'ex').'.jpg';
    file_put_contents($tmp, $withExif);
    expect($withExif)->toContain('GPS-SECRET-LOCATION');

    $file = new UploadedFile($tmp, 'geo.jpg', 'image/jpeg', null, true);
    $this->actingAs($agent)->post("/app/properties/{$p->slug}/photos", ['photos' => [$file]])->assertRedirect();

    $stored = Storage::disk('public')->get($p->photos()->first()->path);
    expect($stored)->not->toContain('Exif')->not->toContain('GPS-SECRET-LOCATION');
    expect(@imagecreatefromstring($stored))->not->toBeFalse();
});

// --- Final review fixes (B-I3) ---

/** A PNG whose header claims the given size; getimagesize() reads only the header. */
function pngHeaderClaiming(int $w, int $h): UploadedFile
{
    $ihdr = pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
    $chunk = pack('N', strlen($ihdr)).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    $path = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($path, "\x89PNG\r\n\x1a\n".$chunk.str_repeat("\0", 64));

    return new UploadedFile($path, 'huge.png', 'image/png', null, true);
}

test('rejects an image above 25 megapixels before decoding it', function () {
    $this->actingAs($this->anna)
        ->post("/app/properties/{$this->property->slug}/photos", ['photos' => [img('ok.jpg'), pngHeaderClaiming(6000, 5000)]])
        ->assertSessionHasErrors('photos.1');
    expect($this->property->photos()->count())->toBe(0);
});

test('a request over post_max_size returns to the form with a friendly photos error', function () {
    $url = "/app/properties/{$this->property->slug}/photos";
    $this->actingAs($this->anna)
        ->call('POST', $url, [], [], [], ['CONTENT_LENGTH' => (string) (PHP_INT_MAX >> 1), 'HTTP_REFERER' => "http://crm.test/app/properties/{$this->property->slug}"])
        ->assertRedirect("/app/properties/{$this->property->slug}")
        ->assertSessionHasErrors(['photos' => 'Those photos are too large to upload at once. Try fewer or smaller files.']);
});
