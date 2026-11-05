<?php

namespace App\Services\Assistant;

interface AssistantModel
{
    /**
     * One model call.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  list<array<string, mixed>>  $tools
     *
     * @throws \Throwable on any API failure (the runner turns it into a friendly message)
     */
    public function respond(string $system, array $messages, array $tools): AssistantTurn;
}
