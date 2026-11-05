<?php

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ChecksLinkedRecords;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Serves store (POST) and update (PATCH); update never changes the stage (use move). */
class DealRequest extends FormRequest
{
    use ChecksLinkedRecords, ResolvesOwner;

    public function authorize(): bool
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal
            ? $this->user()->can('update', $deal)
            : $this->user()->can('create', Deal::class);
    }

    private function isUpdate(): bool
    {
        return $this->route('deal') instanceof Deal;
    }

    public function rules(): array
    {
        $req = $this->isUpdate() ? 'sometimes' : 'required';

        return [
            'title' => [$req, 'string', 'max:255'],
            'pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
            'stage_id' => [$this->isUpdate() ? 'prohibited' : 'required', 'integer', 'exists:stages,id'],
            'contact_id' => [$req, 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'value' => ['nullable', 'integer', 'min:0', 'max:9000000000000'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $this->assertOwnClient($v);

            if (! $this->isUpdate()) {
                $stage = Stage::find($this->integer('stage_id'));
                if ($this->filled('pipeline_id') && $stage->pipeline_id !== $this->integer('pipeline_id')) {
                    $v->errors()->add('stage_id', 'That stage belongs to a different pipeline.');
                }
            }
        }];
    }

    /**
     * Deal columns only; the controller decides owner, pipeline and position.
     * A blank commission is left out so an update keeps the current one.
     */
    public function dealData(): array
    {
        $data = $this->safe()->except(['owner_id', 'pipeline_id', 'stage_id']);
        if (array_key_exists('commission_percent', $data) && $data['commission_percent'] === null) {
            unset($data['commission_percent']);
        }

        return $data;
    }
}
