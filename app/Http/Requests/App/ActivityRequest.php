<?php

namespace App\Http\Requests\App;

use App\Enums\ActivityType;
use App\Enums\ViewingStatus;
use App\Http\Requests\Concerns\ChecksLinkedRecords;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Serves store (POST) and update (PATCH). Links can be set on store only. */
class ActivityRequest extends FormRequest
{
    use ChecksLinkedRecords;

    private function activity(): ?Activity
    {
        $a = $this->route('activity');

        return $a instanceof Activity ? $a : null;
    }

    public function authorize(): bool
    {
        $activity = $this->activity();

        return $activity
            ? $this->user()->can('update', $activity)
            : $this->user()->can('create', Activity::class);
    }

    public function rules(): array
    {
        $store = $this->activity() === null;
        $req = $store ? 'required' : 'sometimes';
        $isViewing = $this->input('type') === ActivityType::Viewing->value;

        return array_merge([
            'type' => $store ? ['required', Rule::enum(ActivityType::class)] : ['prohibited'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'scheduled_at' => [$store && $isViewing ? 'required' : 'nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'call_outcome' => ['nullable', Rule::in(Activity::CALL_OUTCOMES)],
            'viewing_status' => $store ? ['prohibited'] : ['sometimes', Rule::enum(ViewingStatus::class)],
            'feedback' => ['nullable', 'string', 'max:10000'],
            'contact_id' => [$store && $isViewing ? 'required' : 'nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => [$store && $isViewing ? 'required' : 'nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'deal_id' => ['nullable', 'integer', Rule::exists('deals', 'id')->whereNull('deleted_at')],
        ], $store ? [] : ['contact_id' => ['prohibited'], 'property_id' => ['prohibited'], 'deal_id' => ['prohibited']]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $activity = $this->activity();

            if ($activity) {
                if ($this->filled('viewing_status') && $activity->type !== ActivityType::Viewing) {
                    $v->errors()->add('viewing_status', 'Only viewings have a viewing status.');
                }

                return;
            }

            if (! $this->filled('contact_id') && ! $this->filled('property_id') && ! $this->filled('deal_id')) {
                $v->errors()->add('contact_id', 'Link this to a client, property or deal.');

                return;
            }
            $this->assertOwnClient($v);
            $this->assertOwnDeal($v);
        }];
    }

    /** Validated columns with datetimes normalised to the app timezone. */
    public function activityData(): array
    {
        $data = $this->safe()->all();
        foreach (['occurred_at', 'scheduled_at'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = Date::parse($data[$key])->timezone(config('app.timezone'));
            }
        }

        return $data;
    }
}
