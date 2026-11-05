<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use Throwable;

/** The Claude tool-use loop. Never throws for AI/model problems: it saves a friendly assistant message. */
class AssistantRunner
{
    public const MAX_RESULT_CHARS = 20000;

    /** No new model round starts after this many seconds (the request limit is 180s). */
    public const MAX_SECONDS = 120;

    public const MSG_DISABLED = 'AI is not configured. Add ANTHROPIC_API_KEY and VOYAGE_API_KEY to .env.';

    public const MSG_SERVICE = "Sorry, I couldn't reach the AI service. Please try again.";

    public const MSG_REFUSAL = "Sorry, I can't help with that request.";

    public const MSG_INCOMPLETE = "I couldn't finish that answer. Try asking a more specific question.";

    public function __construct(
        private AssistantModel $model,
        private AssistantTools $tools,
        private RefResolver $resolver,
    ) {}

    public function ask(User $user, AssistantConversation $c, string $question): AssistantMessage
    {
        if ($c->title === '') {
            $c->title = mb_substr($question, 0, 60);
            $c->save();
        }

        $history = $this->history($c);

        $modelText = $this->contextPrefix($user, $c).$question;
        $userApi = [['role' => 'user', 'content' => [['type' => 'text', 'text' => $modelText]]]];
        $c->messages()->create(['role' => 'user', 'content' => $question, 'api_content' => $userApi]);
        $c->touch();

        if (! config('crm.ai.enabled')) {
            return $this->save($c, self::MSG_DISABLED);
        }

        try {
            return $this->loop($user, $c, [...$history, ...$userApi]);
        } catch (Throwable $e) {
            report($e);

            return $this->save($c, self::MSG_SERVICE);
        }
    }

    private function loop(User $user, AssistantConversation $c, array $messages): AssistantMessage
    {
        $system = SystemPrompt::for($user, now());
        $definitions = AssistantTools::definitions();
        $max = (int) config('crm.ai.max_tool_rounds', 6);
        $produced = [];
        $allowed = []; // refs that appeared in this turn's tool results
        $started = now();

        for ($round = 1; $round <= $max; $round++) {
            if ($round > 1 && $started->diffInSeconds(now()) >= self::MAX_SECONDS) {
                break;
            }
            $turn = $this->model->respond($system, $messages, $definitions);
            $assistant = ['role' => 'assistant', 'content' => $turn->content];

            if ($turn->stopReason === 'tool_use' && $turn->toolUses !== []) {
                if ($round === $max) {
                    break;
                }
                $results = [];
                foreach ($turn->toolUses as $use) {
                    $raw = $this->tools->execute((string) $use['name'], (array) $use['input'], $user);
                    $sent = $this->cap($raw);
                    foreach ($this->structuredRefs($raw) as $ref) {
                        // Exact match on the quoted JSON string value (not a substring of another ref).
                        if (str_contains($sent, '"'.$ref.'"')) {
                            $allowed[$ref] = true;
                        }
                    }
                    $block = ['type' => 'tool_result', 'tool_use_id' => $use['id'], 'content' => $sent];
                    if ($this->isError($raw)) {
                        $block['is_error'] = true; // SDK ToolResultBlockParam::isError (wire is_error)
                    }
                    $results[] = $block;
                }
                $result = ['role' => 'user', 'content' => $results];
                array_push($messages, $assistant, $result);
                array_push($produced, $assistant, $result);

                continue;
            }

            return match ($turn->stopReason) {
                'end_turn' => trim($turn->text) === ''
                    ? $this->save($c, self::MSG_INCOMPLETE)
                    : $this->save($c, $turn->text, [...$produced, $assistant], $this->citedRefs($turn->text, $allowed)),
                'refusal' => $this->save($c, self::MSG_REFUSAL),
                default => $this->save($c, self::MSG_INCOMPLETE),
            };
        }

        return $this->save($c, self::MSG_INCOMPLETE);
    }

    /** Prior turns as API messages. A user turn with no saved assistant reply (failed turn) is skipped. */
    private function history(AssistantConversation $c): array
    {
        $rows = $c->messages()->get()->values();
        $out = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row->api_content)) {
                continue;
            }
            if ($row->role === 'user') {
                $next = $rows->get($i + 1);
                if (! $next || $next->role !== 'assistant' || ! is_array($next->api_content)) {
                    continue;
                }
            }
            foreach ($row->api_content as $m) {
                $out[] = $m;
            }
        }

        return $out;
    }

    private function contextPrefix(User $user, AssistantConversation $c): string
    {
        if (! $c->scope_type || ! $c->scope_id) {
            return '';
        }
        $label = $this->resolver->label($user, $c->scope_type, $c->scope_id);

        return $label === null ? '' : "(Context: the user is viewing {$c->scope_type}:{$c->scope_id} – {$label}.)\n";
    }

    private function isError(string $json): bool
    {
        $data = json_decode($json, true);

        return is_array($data) && array_key_exists('error', $data);
    }

    private function cap(string $result): string
    {
        return mb_strlen($result) > self::MAX_RESULT_CHARS
            ? mb_substr($result, 0, self::MAX_RESULT_CHARS)."\n[truncated: result too long]"
            : $result;
    }

    /**
     * Refs the server itself put in a tool result: values of "ref"/"*_ref" keys. Text inside
     * excerpts or notes (which customers can write) is never scanned.
     *
     * @return list<string>
     */
    private function structuredRefs(string $json): array
    {
        $data = json_decode($json, true);
        $found = [];
        $walk = function ($node) use (&$walk, &$found) {
            foreach ((array) $node as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } elseif (is_string($key) && ($key === 'ref' || str_ends_with($key, '_ref')) && RefResolver::isRef($value)) {
                    $found[] = $value;
                }
            }
        };
        if (is_array($data)) {
            $walk($data);
        }

        return $found;
    }

    /** @return list<string> */
    private function citedRefs(string $text, array $allowed): array
    {
        preg_match_all(RefResolver::PATTERN, $text, $m, PREG_SET_ORDER);
        $refs = [];
        foreach ($m as $hit) {
            $ref = "{$hit[1]}:{$hit[2]}";
            if (isset($allowed[$ref])) {
                $refs[$ref] = $ref;
            }
        }

        return array_values($refs);
    }

    private function save(AssistantConversation $c, string $text, ?array $api = null, array $refs = []): AssistantMessage
    {
        $c->touch();

        return $c->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'api_content' => $api,
            'refs' => $refs,
        ]);
    }
}
