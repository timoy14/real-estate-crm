<?php

use App\Enums\Role;
use App\Models\AssistantConversation;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use App\Services\Assistant\AssistantModel;
use App\Services\Assistant\AssistantTurn;
use App\Services\Assistant\FakeAssistantModel;
use App\Services\Knowledge\Embedder;
use App\Services\Knowledge\FakeEmbedder;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed(PipelineSeeder::class);
    app()->bind(Embedder::class, FakeEmbedder::class);
    config(['crm.ai.enabled' => true]);
    $this->anna = User::factory()->create(['role' => Role::Agent]);
    $this->ben = User::factory()->create(['role' => Role::Agent]);
});

function fakeModel(array $queue): FakeAssistantModel
{
    $f = new FakeAssistantModel($queue);
    app()->instance(AssistantModel::class, $f);

    return $f;
}

it('ask returns JSON with the message and resolved refs (label + url)', function () {
    $c = Contact::factory()->create(['owner_id' => $this->anna->id, 'first_name' => 'Maria', 'last_name' => 'Lopez']);
    $p = Property::factory()->create(['title' => 'Sunny Loft']);
    fakeModel([
        AssistantTurn::toolUse([
            ['id' => 't1', 'name' => 'get_record', 'input' => ['type' => 'contact', 'id' => $c->id]],
            ['id' => 't2', 'name' => 'get_record', 'input' => ['type' => 'property', 'id' => $p->id]],
        ]),
        AssistantTurn::end("[contact:{$c->id}] likes [property:{$p->id}]"),
    ]);

    $res = $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'Who?'])->assertOk();

    $res->assertJsonPath('message.role', 'assistant')
        ->assertJsonPath('message.refs.0.ref', "contact:{$c->id}")
        ->assertJsonPath('message.refs.0.label', 'Maria Lopez')
        ->assertJsonPath('message.refs.0.url', "/app/clients/{$c->id}")
        ->assertJsonPath('message.refs.1.url', "/app/properties/{$p->id}");
    expect($res->json('conversation_id'))->toBeInt();
    expect(AssistantConversation::first()->title)->toBe('Who?');
});

it('refs the asking user cannot view are dropped on resolve', function () {
    $mine = Contact::factory()->create(['owner_id' => $this->anna->id]);
    $theirs = Contact::factory()->create(['owner_id' => $this->ben->id]);
    $conv = AssistantConversation::create(['user_id' => $this->anna->id, 'title' => 't']);
    // Simulate a stored message whose refs include a record Anna cannot see.
    $conv->messages()->create(['role' => 'assistant', 'content' => 'x', 'api_content' => null, 'refs' => ["contact:{$mine->id}", "contact:{$theirs->id}"]]);

    $this->actingAs($this->anna)->get("/app/assistant/{$conv->id}")
        ->assertInertia(fn (Assert $page) => $page->component('app/Assistant/Index')
            ->has('messages', 1)
            ->has('messages.0.refs', 1)
            ->where('messages.0.refs.0.ref', "contact:{$mine->id}"));
});

it('403 for another users conversation', function () {
    $conv = AssistantConversation::create(['user_id' => $this->ben->id, 'title' => 't']);
    fakeModel([AssistantTurn::end('x')]);

    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'hi', 'conversation_id' => $conv->id])->assertForbidden();
    $this->actingAs($this->anna)->get("/app/assistant/{$conv->id}")->assertForbidden();
    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'hi', 'conversation_id' => 99999])->assertForbidden();
});

it('422 when the scope record is not visible', function () {
    $theirs = Contact::factory()->create(['owner_id' => $this->ben->id]);
    fakeModel([AssistantTurn::end('x')]);

    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'hi', 'scope_type' => 'contact', 'scope_id' => $theirs->id])
        ->assertUnprocessable()->assertJsonValidationErrors('scope_id');
    expect(AssistantConversation::count())->toBe(0);
});

it('creates a scoped conversation for a visible record', function () {
    $mine = Contact::factory()->create(['owner_id' => $this->anna->id]);
    fakeModel([AssistantTurn::end('x')]);

    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'hi', 'scope_type' => 'contact', 'scope_id' => $mine->id])->assertOk();

    expect(AssistantConversation::first())->scope_type->toBe('contact')->scope_id->toBe($mine->id);
});

it('validates the question', function () {
    $this->actingAs($this->anna)->postJson('/app/assistant/ask', [])->assertJsonValidationErrors('question');
    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => str_repeat('a', 2001)])->assertJsonValidationErrors('question');
    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'x', 'scope_type' => 'inquiry', 'scope_id' => 1])->assertJsonValidationErrors('scope_type');
});

it('with AI disabled the not-configured message is returned as a normal response', function () {
    config(['crm.ai.enabled' => false]);

    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'hi'])
        ->assertOk()->assertJsonPath('message.content', 'AI is not configured. Add ANTHROPIC_API_KEY and VOYAGE_API_KEY to .env.');
});

it('index lists only the users own conversations', function () {
    AssistantConversation::create(['user_id' => $this->anna->id, 'title' => 'mine']);
    AssistantConversation::create(['user_id' => $this->ben->id, 'title' => 'not mine']);

    $this->actingAs($this->anna)->get('/app/assistant')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('app/Assistant/Index')->has('conversations', 1)->where('conversations.0.title', 'mine')->where('ai_enabled', true));
});

it('throttled after 20 per minute', function () {
    config(['crm.ai.enabled' => false]);
    foreach (range(1, 20) as $i) {
        $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => "q$i"])->assertOk();
    }
    $this->actingAs($this->anna)->postJson('/app/assistant/ask', ['question' => 'q21'])->assertStatus(429);
    // another user is unaffected
    $this->actingAs($this->ben)->postJson('/app/assistant/ask', ['question' => 'q'])->assertOk();
});

it('guest gets redirected', function () {
    $this->get('/app/assistant')->assertRedirect('/login');
    $this->post('/app/assistant/ask', ['question' => 'x'])->assertRedirect('/login');
});
