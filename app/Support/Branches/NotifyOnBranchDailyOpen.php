<?php

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\User;
use App\Support\Notifications\UltramsgWhatsAppClient;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyOnBranchDailyOpen
{
    public function __construct(
        private readonly UltramsgWhatsAppClient $ultramsgWhatsAppClient,
        private readonly BranchDailyOperationRecipients $recipients,
    ) {}

    public function notify(User $actor, Branch $branch, BranchDailyOperation $operation): void
    {
        if (! $this->ultramsgWhatsAppClient->isEnabled()) {
            Log::notice('UltraMsg deshabilitado: no se envía WhatsApp de apertura de sucursal', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
            ]);

            return;
        }

        $phones = $this->recipients->phonesForBranch($branch);

        if ($phones === []) {
            Log::notice('Apertura de sucursal: sin teléfonos de administradores o gerentes para WhatsApp', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
            ]);

            return;
        }

        $message = $this->buildMessage($actor, $branch, $operation);

        foreach ($phones as $phone) {
            try {
                $this->ultramsgWhatsAppClient->sendTextMessage($phone, $message);
            } catch (Throwable $exception) {
                Log::warning('Apertura de sucursal: error al enviar WhatsApp', [
                    'phone' => $phone,
                    'branch_id' => $branch->getKey(),
                    'operation_id' => $operation->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildMessage(User $actor, Branch $branch, BranchDailyOperation $operation): string
    {
        $actorName = filled($actor->name) ? (string) $actor->name : (string) ($actor->email ?? 'Gerencia');
        $openedAt = $operation->opened_at ?? now();
        $openedAtLabel = $openedAt->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return implode("\n", [
            'APERTURA DE SUCURSAL',
            (string) config('app.name'),
            '',
            'La sucursal está siendo aperturada.',
            '',
            '[ GESTION ]',
            'Sucursal:'.$branch->name,
            'Codigo:'.(string) ($branch->code ?? '—'),
            'Apertura:'.$openedAtLabel,
            'Responsable:'.$actorName,
            '',
            'Reporte automatico al aperturar la sucursal.',
        ]);
    }
}
