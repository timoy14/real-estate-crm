<?php

namespace App\Http\Requests\App;

use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('deal'));
    }

    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', 'exists:stages,id'],
            'position' => ['required', 'integer', 'min:0'],
            'lost_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            /** @var Deal $deal */
            $deal = $this->route('deal');
            if ($v->errors()->isEmpty() && Stage::find($this->integer('stage_id'))->pipeline_id !== $deal->pipeline_id) {
                $v->errors()->add('stage_id', 'That stage belongs to a different pipeline.');
            }
        }];
    }
}
