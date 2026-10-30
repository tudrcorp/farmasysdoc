<?php

use App\Filament\Pages\AiAgent\AiAgentPage;
use App\Models\AiAgentConversation;
use App\Models\AiAgentMessage;
use App\Models\User;
use App\Services\AiAgent\AiAgentBudget;
use App\Services\AiAgent\AiAgentChat;
use App\Services\AiAgent\AiAgentProgress;
use App\Services\AiAgent\AiAgentToolbox;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function geminiEvents(array ...$events): string
{
    return implode('', array_map(fn (array $event): string => 'data: '.json_encode($event)."\n\n", $events));
}

function geminiText(string $text): string
{
    return geminiEvents([
        'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]],
        'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 100, 'thoughtsTokenCount' => 50],
    ]);
}

beforeEach(function (): void {
    config(['services.gemini.api_key' => 'test-key', 'ai_agent.monthly_budget_usd' => 10]);
    $this->user = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    $this->conversation = AiAgentConversation::query()->create(['user_id' => $this->user->id, 'title' => 'Prueba']);
    $this->chat = app(AiAgentChat::class);
});

it('saves the answer with its cost', function (): void {
    Http::fake(['*' => Http::response(geminiText('Hola'))]);

    $message = $this->chat->ask($this->conversation, 'Hola', $this->user);

    expect($message->content)->toBe('Hola')
        ->and($message->input_tokens)->toBe(1000)
        ->and($message->output_tokens)->toBe(150)
        ->and((float) $message->cost_usd)->toBeGreaterThan(0);
});

it('runs requested tools, returns the model content and audits the call', function (): void {
    $functionPart = ['functionCall' => ['name' => 'unknown_tool', 'args' => ['a' => 1]], 'thoughtSignature' => 'firma'];

    Http::fakeSequence()
        ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [$functionPart]]]]])
        ->push(geminiText('Listo'));

    $message = $this->chat->ask($this->conversation, 'Pregunta', $this->user);

    expect($message->content)->toBe('Listo')
        ->and($message->tool_calls[0]['tool'])->toBe('unknown_tool');

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'contents.1.parts.0.thoughtSignature') === 'firma');
});

it('returns empty tool arguments as an object', function (): void {
    Http::fakeSequence()
        ->push(geminiEvents(['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'unknown_tool', 'args' => new stdClass]]]]]]]))
        ->push(geminiText('Listo'));

    $this->chat->ask($this->conversation, 'Pregunta', $this->user);

    Http::assertSent(fn ($request): bool => str_contains($request->body(), '"args":{}'));
});

it('stops when the monthly budget is exhausted', function (): void {
    Http::fake();
    config(['ai_agent.monthly_budget_usd' => 0]);

    $message = $this->chat->ask($this->conversation, 'Hola', $this->user);

    expect($message->failed)->toBeTrue();
    Http::assertNothingSent();
});

it('shows gemini failures as a message', function (): void {
    Http::fake(['*' => Http::response([], 402)]);

    $message = $this->chat->ask($this->conversation, 'Hola', $this->user);

    expect($message->failed)->toBeTrue()
        ->and($message->content)->toContain('saldo');
});

it('forces an answer on the last round instead of failing', function (): void {
    $call = geminiEvents(['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'unknown_tool', 'args' => ['a' => 1]]]]]]]]);
    config(['ai_agent.max_tool_rounds' => 2]);

    Http::fakeSequence()->push($call)->push(geminiText('Con lo que encontré'));

    expect($this->chat->ask($this->conversation, 'Hola', $this->user)->content)->toBe('Con lo que encontré');

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'toolConfig.functionCallingConfig.mode') === 'NONE');
});

it('joins streamed text and reports it live', function (): void {
    Http::fake(['*' => Http::response(geminiEvents(
        ['candidates' => [['content' => ['parts' => [['text' => 'Ho']]]]]],
        ['candidates' => [['content' => ['parts' => [['text' => 'la']]]]]],
    ))]);

    $seen = [];
    $progress = new AiAgentProgress(function (string $html) use (&$seen): void {
        $seen[] = $html;
    }, fn (string $status) => null, minIntervalSeconds: 0);

    $conversation = $this->conversation;
    $conversation->messages()->create(['role' => AiAgentMessage::ROLE_USER, 'content' => 'Hola']);

    expect($this->chat->respond($conversation, $this->user, $progress)->content)->toBe('Hola')
        ->and($seen)->not->toBeEmpty();
});

it('calculates the near limit warning', function (): void {
    AiAgentMessage::query()->create([
        'ai_agent_conversation_id' => $this->conversation->id,
        'role' => AiAgentMessage::ROLE_ASSISTANT,
        'content' => 'x',
        'cost_usd' => 8.5,
    ]);

    expect(app(AiAgentBudget::class)->isNearLimit())->toBeTrue()
        ->and(app(AiAgentBudget::class)->isExhausted())->toBeFalse();
});

it('offers no tool that changes data', function (): void {
    $names = array_column(app(AiAgentToolbox::class)->declarations(), 'name');

    expect($names)->not->toContain('update', 'delete', 'write_sql');
});

it('sends a question from the page and answers it', function (): void {
    Http::fake(['*' => Http::response(geminiText('Respuesta'))]);
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farmaadmin'));

    Livewire::test(AiAgentPage::class)
        ->call('send', 'Hola')
        ->assertSet('awaitingAnswer', true)
        ->call('answer')
        ->assertSet('awaitingAnswer', false);

    expect(AiAgentMessage::query()->pluck('content')->all())->toBe(['Hola', 'Respuesta']);
});
