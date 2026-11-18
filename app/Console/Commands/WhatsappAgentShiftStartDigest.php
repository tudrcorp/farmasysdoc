<?php

namespace App\Console\Commands;

use App\Enums\WhatsappConversationStatus;
use App\Models\WhatsappConversation;
use App\Services\WhatsappAgent\WhatsappAnalystNotifier;
use App\Support\WhatsappAgent\WhatsappAnalystSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WhatsappAgentShiftStartDigest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp-agent:shift-start-digest';

    /**
     * @var string
     */
    protected $description = 'Al iniciar la jornada de analistas, avisa una vez en la campana cuántos pedidos de WhatsApp quedaron pendientes fuera de horario.';

    public function handle(WhatsappAnalystNotifier $notifier): int
    {
        if (! WhatsappAnalystSchedule::current()->isWithinHours()) {
            $this->components->info('Fuera del horario de analistas.');

            return self::SUCCESS;
        }

        $cacheKey = 'whatsapp-agent:shift-digest:'.now('America/Caracas')->toDateString();

        if (Cache::has($cacheKey)) {
            return self::SUCCESS;
        }

        $pending = WhatsappConversation::query()
            ->where('status', WhatsappConversationStatus::PendingNextShift->value)
            ->count();

        $notifier->shiftStartDigest($pending);

        Cache::put($cacheKey, true, now()->addDay());

        $this->components->info('Pendientes de la jornada: '.$pending.'.');

        return self::SUCCESS;
    }
}
