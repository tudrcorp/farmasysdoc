<?php

namespace App\Filament\Pages\AiAgent;

use App\Models\AiAgentConversation;
use App\Models\AiAgentMessage;
use App\Models\User;
use App\Services\AiAgent\AiAgentBudget;
use App\Services\AiAgent\AiAgentChat;
use App\Services\AiAgent\AiAgentProgress;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class AiAgentPage extends Page
{
    protected static ?string $slug = 'agente-ia';

    protected static ?string $navigationLabel = 'Agente IA';

    protected static ?string $title = 'Agente IA';

    protected static string|UnitEnum|null $navigationGroup = 'reports';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Sparkles;

    protected string $view = 'filament.ai-agent.chat';

    public ?int $conversationId = null;

    public string $draft = '';

    public bool $awaitingAnswer = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public function send(string $question): void
    {
        $question = mb_substr(trim($question), 0, 4000);

        if ($question === '' || $this->awaitingAnswer) {
            return;
        }

        $conversation = $this->conversation() ?? $this->user()->aiAgentConversations()->create([
            'title' => app(AiAgentChat::class)->titleFor($question),
        ]);

        $conversation->messages()->create(['role' => AiAgentMessage::ROLE_USER, 'content' => $question]);

        $this->conversationId = $conversation->id;
        $this->draft = '';
        $this->awaitingAnswer = true;

        $this->js('$wire.answer()');
    }

    public function answer(): void
    {
        $conversation = $this->conversation();

        if ($conversation !== null) {
            app(AiAgentChat::class)->respond($conversation, $this->user(), new AiAgentProgress(
                fn (string $html) => $this->stream(content: $html, replace: true, name: 'agent-answer'),
                fn (string $status) => $this->stream(content: e($status), replace: true, name: 'agent-status'),
            ));
        }

        $this->awaitingAnswer = false;
    }

    public function startConversation(): void
    {
        $this->conversationId = null;
        $this->draft = '';
    }

    public function openConversation(int $conversationId): void
    {
        $this->conversationId = $this->user()->aiAgentConversations()->whereKey($conversationId)->value('id');
    }

    public function deleteConversation(int $conversationId): void
    {
        $this->user()->aiAgentConversations()->whereKey($conversationId)->delete();

        if ($this->conversationId === $conversationId) {
            $this->conversationId = null;
        }
    }

    /**
     * @return Collection<int, AiAgentConversation>
     */
    public function getConversations(): Collection
    {
        return $this->user()->aiAgentConversations()->latest('updated_at')->limit(50)->get();
    }

    /**
     * @return Collection<int, AiAgentMessage>
     */
    public function getMessages(): Collection
    {
        return $this->conversation()?->messages()->with('reports')->get() ?? collect();
    }

    /**
     * @return list<array{title: string, question: string}>
     */
    public function getSuggestions(): array
    {
        return [
            ['title' => 'Vencimientos', 'question' => '¿Qué productos se vencen en los próximos 3 meses y cuántas unidades hay por sucursal?'],
            ['title' => 'Ventas', 'question' => 'Compara las ventas de este mes con las del mes pasado por sucursal.'],
            ['title' => 'Reposición', 'question' => '¿Qué productos están por debajo de su punto de reorden?'],
            ['title' => 'Reporte', 'question' => 'Hazme un reporte de los 50 productos más vendidos de este mes.'],
        ];
    }

    public function getBudget(): AiAgentBudget
    {
        return app(AiAgentBudget::class);
    }

    private function conversation(): ?AiAgentConversation
    {
        return $this->conversationId === null
            ? null
            : $this->user()->aiAgentConversations()->find($this->conversationId);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        return $user;
    }
}
