<?php

namespace App\Services\Assistant;

use RuntimeException;
use Throwable;

/** Scripted model for tests: a queue of turns (or exceptions to throw) and a record of every call. */
class FakeAssistantModel implements AssistantModel
{
    /** @var list<array{system: string, messages: array, tools: array}> */
    public array $calls = [];

    /** @param  list<AssistantTurn|Throwable>  $queue */
    public function __construct(private array $queue = []) {}

    public function respond(string $system, array $messages, array $tools): AssistantTurn
    {
        $this->calls[] = compact('system', 'messages', 'tools');
        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next ?? throw new RuntimeException('FakeAssistantModel queue is empty');
    }
}
