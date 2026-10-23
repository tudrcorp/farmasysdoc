<?php

namespace App\Filament\Resources\PettyCashFunds\Support;

use Filament\Forms\Components\FileUpload;

final class PettyCashUploads
{
    public static function paymentProof(bool $required = true): FileUpload
    {
        $upload = FileUpload::make('payment_proof_path')
            ->label('Comprobante de pago')
            ->helperText('Captura o PDF del pago. JPG, PNG, WebP o PDF. Máximo 5 MB.')
            ->disk('public')
            ->directory('petty-cash/payment-proofs')
            ->visibility('public')
            ->acceptedFileTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf',
            ])
            ->maxSize(5120)
            ->downloadable()
            ->openable()
            ->columnSpanFull();

        if ($required) {
            $upload->required();
        }

        return $upload;
    }

    public static function expenseReceipt(): FileUpload
    {
        return FileUpload::make('receipt_path')
            ->label('Foto de factura o ticket')
            ->helperText('JPG, PNG o WebP. Máximo 5 MB.')
            ->image()
            ->required()
            ->disk('public')
            ->directory('petty-cash/expense-receipts')
            ->visibility('public')
            ->maxSize(5120)
            ->downloadable()
            ->openable()
            ->columnSpanFull();
    }
}
