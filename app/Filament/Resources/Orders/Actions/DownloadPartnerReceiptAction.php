<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\OrderPartnerReceipt;
use App\Models\OrderService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPartnerReceiptAction
{
    public static function make(): Action
    {
        return Action::make('downloadPartnerReceipt')
            ->label('Descargar nota de recibido')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->visible(fn (object $livewire): bool => self::receipt($livewire) instanceof OrderPartnerReceipt)
            ->action(function (object $livewire): ?StreamedResponse {
                $receipt = self::receipt($livewire);

                return $receipt instanceof OrderPartnerReceipt ? $receipt->download() : null;
            });
    }

    private static function receipt(object $livewire): ?OrderPartnerReceipt
    {
        $order = self::orderFrom($livewire);
        $receipt = $order?->partnerReceipt;

        if (! $receipt instanceof OrderPartnerReceipt || blank($receipt->pdf_path)) {
            return null;
        }

        return $receipt;
    }

    private static function orderFrom(object $livewire): ?Order
    {
        if (method_exists($livewire, 'order')) {
            $order = $livewire->order();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if (! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        if ($record instanceof Order) {
            return $record;
        }

        if ($record instanceof OrderService) {
            $order = $record->order;

            return $order instanceof Order ? $order : null;
        }

        return null;
    }
}
