<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappAgent\StoreWhatsappCartItemRequest;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationItem;
use Illuminate\Http\JsonResponse;

/**
 * Carrito que arma el asistente; el analista lo revisa antes de crear el pedido.
 */
class WhatsappAgentCartController extends Controller
{
    public function store(StoreWhatsappCartItemRequest $request, WhatsappConversation $conversation): JsonResponse
    {
        $data = $request->validated();
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;

        $existing = $productId !== null
            ? $conversation->items()->where('product_id', $productId)->first()
            : null;

        if ($existing instanceof WhatsappConversationItem) {
            $existing->update([
                'quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? $existing->notes,
            ]);
            $item = $existing;
        } else {
            $item = $conversation->items()->create([
                'product_id' => $productId,
                'requested_name' => $data['requested_name'],
                'quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json([
            'saved' => true,
            'item_id' => (int) $item->getKey(),
            'cart' => $this->cart($conversation),
        ]);
    }

    public function destroy(WhatsappConversation $conversation, int $item): JsonResponse
    {
        $conversation->items()->whereKey($item)->delete();

        return response()->json([
            'deleted' => true,
            'cart' => $this->cart($conversation),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cart(WhatsappConversation $conversation): array
    {
        return $conversation->items()
            ->with('product:id,name')
            ->get()
            ->map(fn (WhatsappConversationItem $item): array => [
                'item_id' => (int) $item->getKey(),
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'requested_name' => $item->requested_name,
                'quantity' => (float) $item->quantity,
            ])
            ->all();
    }
}
