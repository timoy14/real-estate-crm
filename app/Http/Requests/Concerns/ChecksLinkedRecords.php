<?php

namespace App\Http\Requests\Concerns;

use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/** after() checks that a linked record is one the user may see. */
trait ChecksLinkedRecords
{
    /** @param  class-string<Model>  $model  a model with a visibleTo scope */
    protected function assertVisible(Validator $v, string $field, string $model, string $message): void
    {
        if ($this->filled($field)
            && ! $model::query()->visibleTo($this->user())->whereKey($this->integer($field))->exists()) {
            $v->errors()->add($field, $message);
        }
    }

    protected function assertOwnClient(Validator $v): void
    {
        $this->assertVisible($v, 'contact_id', Contact::class, 'Pick one of your own clients.');
    }

    protected function assertOwnDeal(Validator $v): void
    {
        $this->assertVisible($v, 'deal_id', Deal::class, 'Pick one of your own deals.');
    }
}
