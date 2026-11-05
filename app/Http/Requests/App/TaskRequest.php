<?php

namespace App\Http\Requests\App;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\ChecksLinkedRecords;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Serves store (POST) and update (PATCH, including `{completed: bool}`). */
class TaskRequest extends FormRequest
{
    use ChecksLinkedRecords, ResolvesOwner;

    private function task(): ?Task
    {
        $t = $this->route('task');

        return $t instanceof Task ? $t : null;
    }

    public function authorize(): bool
    {
        $task = $this->task();

        return $task ? $this->user()->can('update', $task) : $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        $req = $this->task() ? 'sometimes' : 'required';

        return [
            'title' => [$req, 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'completed' => ['sometimes', 'boolean'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'deal_id' => ['nullable', 'integer', Rule::exists('deals', 'id')->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $this->assertOwnClient($v);
            $this->assertOwnDeal($v);
        }];
    }

    /** Task columns; the controller decides owner and completion. A date-only due date means the end of that day. */
    public function taskData(): array
    {
        $data = $this->safe()->except(['owner_id', 'completed']);

        if (! empty($data['due_at'])) {
            $raw = (string) $data['due_at'];
            $due = Date::parse($raw);
            $data['due_at'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)
                ? $due->endOfDay()
                : $due->timezone(config('app.timezone'));
        }

        return $data;
    }
}
