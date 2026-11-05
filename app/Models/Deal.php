<?php

namespace App\Models;

use App\Enums\DealStatus;
use App\Enums\ListingType;
use App\Events\DealStageChanged;
use App\Models\Concerns\HasOwner;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'title', 'pipeline_id', 'stage_id', 'position', 'contact_id', 'property_id', 'value',
    'commission_percent', 'expected_close_date', 'status', 'lost_reason', 'closed_at', 'owner_id',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory, HasOwner, SoftDeletes;

    protected $attributes = [
        'status' => 'open',
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'value' => 'integer',
            'position' => 'integer',
            'commission_percent' => 'decimal:2',
            'expected_close_date' => 'date:Y-m-d',
            'closed_at' => 'datetime',
        ];
    }

    /** Default commission: 3% of a sale, 100% (one month's rent) of a rental. */
    public static function defaultCommission(ListingType $type): string
    {
        return $type === ListingType::Rent ? '100.00' : '3.00';
    }

    protected function estimatedCommission(): Attribute
    {
        return Attribute::get(fn () => $this->value === null
            ? null
            : (int) round($this->value * (float) $this->commission_percent / 100));
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** Append a new deal to the bottom of its stage column. */
    public function appendToStage(): void
    {
        $max = static::query()->where('stage_id', $this->stage_id)->max('position');
        $this->position = $max === null ? 0 : (int) $max + 1;
    }

    public function moveTo(Stage $stage, int $position, ?string $lostReason = null): void
    {
        $from = $this->stage;

        DB::transaction(function () use ($stage, $position, $lostReason, &$from) {
            // Lock the deal row first, then re-read its stage under the lock.
            $fresh = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $this->stage_id = $fresh->stage_id;
            $from = Stage::query()->findOrFail($fresh->stage_id);

            // Lock the affected stages in id order so concurrent moves cannot deadlock.
            Stage::query()->whereKey([$this->stage_id, $stage->id])->orderBy('id')->lockForUpdate()->get();

            $sourceId = $this->stage_id;

            $target = static::query()->where('stage_id', $stage->id)->whereKeyNot($this->id)
                ->orderBy('position')->orderBy('id')->pluck('id')->all();
            $position = max(0, min($position, count($target)));
            array_splice($target, $position, 0, [$this->id]);

            $this->stage_id = $stage->id;
            $this->pipeline_id = $stage->pipeline_id;
            if ($sourceId === $stage->id) {
                // Reordering within a column leaves status, closed_at and lost_reason alone.
            } elseif ($stage->is_won) {
                $this->status = DealStatus::Won;
                $this->closed_at = now();
                $this->lost_reason = null;
            } elseif ($stage->is_lost) {
                $this->status = DealStatus::Lost;
                $this->closed_at = now();
                $this->lost_reason = $lostReason;
            } else {
                $this->status = DealStatus::Open;
                $this->closed_at = null;
                $this->lost_reason = null;
            }
            $this->position = $position;
            $this->save();

            static::renumber($target);

            if ($sourceId !== $stage->id) {
                static::renumber(
                    static::query()->where('stage_id', $sourceId)->orderBy('position')->orderBy('id')->pluck('id')->all(),
                );
            }
        });

        $this->refresh();

        if ($from->id !== $stage->id) {
            DealStageChanged::dispatch($this, $from, $stage);
        }
    }

    /** Rewrite positions 0..n-1 in the given id order. */
    public static function renumber(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            static::query()->whereKey($id)->update(['position' => $i]);
        }
    }
}
