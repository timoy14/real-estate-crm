<?php

namespace App\Services\Assistant;

final readonly class AssistantTurn
{
    /**
     * @param  list<array<string, mixed>>  $content  Content blocks as plain arrays, ready to replay.
     * @param  list<array{id: string, name: string, input: array}>  $toolUses
     */
    public function __construct(
        public string $stopReason,
        public array $content,
        public string $text,
        public array $toolUses,
    ) {}

    public static function end(string $text): self
    {
        return new self('end_turn', [['type' => 'text', 'text' => $text]], $text, []);
    }

    /** @param  list<array{id: string, name: string, input: array}>  $uses */
    public static function toolUse(array $uses, string $text = ''): self
    {
        $content = $text === '' ? [] : [['type' => 'text', 'text' => $text]];
        foreach ($uses as $u) {
            $content[] = ['type' => 'tool_use', 'id' => $u['id'], 'name' => $u['name'], 'input' => $u['input']];
        }

        return new self('tool_use', $content, $text, $uses);
    }
}
