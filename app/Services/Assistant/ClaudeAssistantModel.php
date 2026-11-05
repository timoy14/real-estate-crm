<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use GuzzleHttp\Client as GuzzleClient;
use Throwable;

/**
 * Calls the Claude Messages API. Parameters confirmed against vendor/anthropic-ai/sdk:
 * MessagesService::create(maxTokens, messages, model, outputConfig, system, tools); the system
 * prompt is a text block with cacheControl; no thinking parameter is sent.
 */
class ClaudeAssistantModel implements AssistantModel
{
    public const TIMEOUT_SECONDS = 60.0;

    private ?Client $client = null;

    public function respond(string $system, array $messages, array $tools): AssistantTurn
    {
        try {
            $message = $this->client()->messages->create(
                maxTokens: 16000,
                messages: $messages,
                model: (string) config('crm.ai.model'),
                outputConfig: ['effort' => 'medium'],
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                tools: $tools,
            );

            // Read the stop reason before touching content.
            $stopReason = (string) $message->stopReason;

            $content = [];
            $text = '';
            $toolUses = [];
            foreach ($message->content as $block) {
                switch ($block->type) {
                    case 'text':
                        $content[] = ['type' => 'text', 'text' => $block->text];
                        $text .= $block->text;
                        break;
                    case 'tool_use':
                        $input = is_array($block->input) ? $block->input : [];
                        $content[] = ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input' => $input];
                        $toolUses[] = ['id' => $block->id, 'name' => $block->name, 'input' => $input];
                        break;
                    case 'thinking':
                        // Thinking blocks must be replayed unchanged.
                        $content[] = ['type' => 'thinking', 'thinking' => $block->thinking, 'signature' => $block->signature];
                        break;
                    case 'redacted_thinking':
                        $content[] = ['type' => 'redacted_thinking', 'data' => $block->data];
                        break;
                }
            }

            return new AssistantTurn($stopReason, $content, $text, $toolUses);
        } catch (Throwable $e) {
            // Re-throw without the original: its request/response objects can carry headers and bodies.
            $status = $e instanceof APIStatusException ? ' status '.$e->status : '';
            throw new AssistantModelFailed('Claude API call failed ('.class_basename($e).$status.')');
        }
    }

    private function client(): Client
    {
        // RequestOptions::timeout is advisory only (the SDK never reads it), so the
        // 60s limit is enforced on the Guzzle transporter; both are set.
        return $this->client ??= new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: [
                'timeout' => self::TIMEOUT_SECONDS,
                'transporter' => new GuzzleClient(['timeout' => self::TIMEOUT_SECONDS, 'connect_timeout' => 10]),
            ],
        );
    }
}
