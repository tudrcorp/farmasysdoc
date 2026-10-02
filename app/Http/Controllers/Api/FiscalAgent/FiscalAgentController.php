<?php

namespace App\Http\Controllers\Api\FiscalAgent;

use App\Enums\FiscalPrinterModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiscalAgent\FiscalAgentClaimRequest;
use App\Http\Requests\FiscalAgent\FiscalAgentHeartbeatRequest;
use App\Http\Requests\FiscalAgent\FiscalAgentResultRequest;
use App\Http\Requests\FiscalAgent\FiscalAgentStartedRequest;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalAgentConflictException;
use App\Services\Fiscal\FiscalAgentQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protocolo HTTP del agente local de máquina fiscal (contrato v1).
 */
class FiscalAgentController extends Controller
{
    public function __construct(private FiscalAgentQueue $queue) {}

    public function claim(FiscalAgentClaimRequest $request): JsonResponse
    {
        $printer = $this->printer($request);
        $capabilities = array_values(array_filter((array) $request->validated('capabilities', []), 'is_string'));

        $next = $this->queue->waitForNext($printer, (int) $request->validated('wait', 0), $capabilities);

        if ($next === null) {
            return response()->json(null, Response::HTTP_NO_CONTENT);
        }

        $document = $next['document'];

        return response()->json([
            'job' => [
                'uuid' => $document->uuid,
                'type' => $document->type->value,
                'simulation' => (bool) $document->simulation,
                'is_test' => (bool) $document->is_test,
                'recovery' => $next['recovery'],
                'attempts' => $document->attempts,
                'printer_counter_before' => $document->printer_counter_before,
                'payload' => $document->payload,
            ],
            'config' => $printer->fresh()?->agentConfig() ?? $printer->agentConfig(),
        ]);
    }

    public function started(FiscalAgentStartedRequest $request, string $uuid): JsonResponse
    {
        $document = $this->document($request, $uuid);

        try {
            $document = $this->queue->markStarted($document, $request->validated('counter_before'));
        } catch (FiscalAgentConflictException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'uuid' => $document->uuid,
            'status' => $document->status->value,
        ]);
    }

    public function result(FiscalAgentResultRequest $request, string $uuid): JsonResponse
    {
        $document = $this->document($request, $uuid);

        try {
            $document = $this->queue->applyResult($document, $request->validated());
        } catch (FiscalAgentConflictException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'uuid' => $document->uuid,
            'status' => $document->status->value,
            'fiscal_number' => $document->fiscal_number,
        ]);
    }

    public function heartbeat(FiscalAgentHeartbeatRequest $request): JsonResponse
    {
        $printer = $this->printer($request);
        $this->queue->recordHeartbeat($printer, $request->validated());

        $model = $printer->model instanceof FiscalPrinterModel ? $printer->model : null;

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'printer' => [
                'name' => $printer->name,
                'model' => $model?->value,
                'protocol' => $model?->protocol(),
                'serial_number' => $printer->serial_number,
                'fiscal_registry' => $printer->fiscal_registry,
                'connection_port' => $printer->connection_port,
            ],
            'pending_jobs' => $printer->pendingDocumentsCount(),
            'config' => $printer->agentConfig(),
        ]);
    }

    private function printer(Request $request): FiscalPrinter
    {
        $printer = $request->attributes->get('fiscalPrinter');
        abort_unless($printer instanceof FiscalPrinter, Response::HTTP_UNAUTHORIZED);

        return $printer;
    }

    private function document(Request $request, string $uuid): FiscalDocument
    {
        return FiscalDocument::query()
            ->where('uuid', $uuid)
            ->where('fiscal_printer_id', $this->printer($request)->id)
            ->firstOr(fn () => abort(response()->json([
                'message' => 'Documento fiscal no encontrado para esta máquina.',
            ], Response::HTTP_NOT_FOUND)));
    }
}
