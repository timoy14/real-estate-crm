<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AskAssistantRequest;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\AssistantRunner;
use App\Services\Assistant\RefResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __construct(private RefResolver $resolver) {}

    public function index(Request $request): Response
    {
        return $this->page($request, null);
    }

    public function show(Request $request, AssistantConversation $conversation): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return $this->page($request, $conversation);
    }

    public function ask(AskAssistantRequest $request, AssistantRunner $runner): JsonResponse
    {
        // Several model rounds can outlast PHP's default 30s; the runner stops new rounds at 120s.
        set_time_limit(180);
        $user = $request->user();

        if ($request->filled('conversation_id')) {
            // A missing id and someone else's id are indistinguishable.
            $conversation = AssistantConversation::query()->find($request->integer('conversation_id'));
            abort_unless($conversation && $conversation->user_id === $user->id, 403);
        } else {
            $type = $request->input('scope_type');
            $scopeId = $request->filled('scope_id') ? $request->integer('scope_id') : null;
            if ($type && $scopeId && $this->resolver->label($user, $type, $scopeId) === null) {
                throw ValidationException::withMessages(['scope_id' => 'That record is not available.']);
            }
            $conversation = AssistantConversation::create([
                'user_id' => $user->id,
                'title' => mb_substr($request->string('question')->toString(), 0, 60),
                'scope_type' => $type ?: null,
                'scope_id' => $type ? $scopeId : null,
            ]);
        }

        $message = $runner->ask($user, $conversation, $request->string('question')->toString());

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => $this->present($request->user(), $message),
        ]);
    }

    private function page(Request $request, ?AssistantConversation $conversation): Response
    {
        $user = $request->user();

        return Inertia::render('app/Assistant/Index', [
            'conversations' => AssistantConversation::query()->where('user_id', $user->id)
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'updated_at' => $c->updated_at?->toIso8601String()])
                ->values(),
            'conversation' => $conversation ? ['id' => $conversation->id, 'title' => $conversation->title] : null,
            'messages' => $conversation
                ? $conversation->messages()->get()->map(fn ($m) => $this->present($user, $m))->values()
                : [],
            'ai_enabled' => (bool) config('crm.ai.enabled'),
        ]);
    }

    /** Refs are re-resolved for the asking user every time they are sent out. */
    private function present($user, AssistantMessage $m): array
    {
        return [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'refs' => $m->role === 'assistant' ? $this->resolver->resolve($user, $m->refs ?? []) : [],
        ];
    }
}
