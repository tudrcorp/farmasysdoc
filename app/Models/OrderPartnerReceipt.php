<?php

namespace App\Models;

use Database\Factories\OrderPartnerReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderPartnerReceipt extends Model
{
    /** @use HasFactory<OrderPartnerReceiptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'partner_company_id',
        'external_number',
        'external_status',
        'external_status_label',
        'service_order_number',
        'delivery_note_number',
        'receipt_number',
        'received_at',
        'received_by',
        'location',
        'is_complete',
        'observations',
        'medications',
        'pdf_path',
        'external_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'external_updated_at' => 'datetime',
            'is_complete' => 'boolean',
            'medications' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    public function medicationsSummary(): string
    {
        $lines = [];

        foreach ($this->medications ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $name = filled($line['nombre'] ?? null) ? (string) $line['nombre'] : 'Medicamento';
            $ordered = filled($line['cantidad_pedida'] ?? null) ? (string) $line['cantidad_pedida'] : '—';
            $received = filled($line['cantidad_recibida'] ?? null) ? (string) $line['cantidad_recibida'] : '—';
            $lines[] = e($name).' · pedido '.e($ordered).' · recibido '.e($received);
        }

        return $lines === [] ? '—' : implode('<br>', $lines);
    }

    public function fileName(): string
    {
        $number = filled($this->receipt_number) ? (string) $this->receipt_number : (string) $this->external_number;

        return 'nota-recibido-'.$number.'.pdf';
    }

    public function download(): ?StreamedResponse
    {
        $path = (string) $this->pdf_path;

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return response()->streamDownload(function () use ($path): void {
            $stream = Storage::disk('local')->readStream($path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $this->fileName(), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
