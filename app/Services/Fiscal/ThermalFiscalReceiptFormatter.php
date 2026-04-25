<?php

namespace App\Services\Fiscal;

use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Finance\DefaultVatRate;
use Illuminate\Support\Str;

/**
 * Genera texto plano (y envoltura ESC/POS básica) tipo factura fiscal VE para impresoras térmicas
 * (HKA, ACLAS, etc.). Es una **simulación de layout** para pruebas; el cumplimiento fiscal
 * definitivo depende del firmware/driver y de SENIAT.
 */
final class ThermalFiscalReceiptFormatter
{
    public function format(Sale $sale): string
    {
        $width = max(24, min(48, (int) config('fiscal.thermal_line_width', 42)));
        $rate = $this->resolveVesUsdRate($sale);
        $branch = $sale->branch;
        $client = $sale->client;

        $lines = [];

        $lines[] = $this->centerHeader('SENIAT', $width);
        // $lines[] = '';
        $lines[] = $this->centerHeaderRow('RIF:', (string) ($branch?->tax_id ?? '—'), $width);
        $lines[] = $this->centerHeader((string) ($branch?->legal_name ?? $branch?->name ?? 'RAZÓN SOCIAL'), $width);
        $lines = array_merge($lines, $this->wrapUpperCentered((string) ($branch?->address ?? ''), $width));
        if (filled($branch?->city) || filled($branch?->state)) {
            $lines[] = $this->centerHeader(trim(implode(' ', array_filter([$branch?->city, $branch?->state]))), $width);
        }
        // $lines[] = '';
        $lines[] = $this->leftRow('RIF/CI:', Str::upper($this->clientDocument($client)));
        $lines[] = $this->leftRow('R.S.:', Str::upper((string) ($client?->name ?? 'MOSTRADOR')));
        $lines[] = $this->leftRow('DIRECCION:', Str::upper((string) ($client?->address ?? '')));
        $lines[] = $this->leftRow('TELEFONO:', (string) ($client?->phone ?? ''));
        $lines[] = $this->leftRow('VENDEDOR:', '01');
        $lines[] = $this->leftRow('EMITIDO POR:', Str::upper((string) config('fiscal.emitido_por', 'FARMASYS')));
        // $lines[] = '';
        $lines[] = $this->centerHeader('FACTURA', $width);
        $lines[] = $this->row('FACTURA:', $this->invoiceNumber($sale), $width);
        $soldAt = $sale->sold_at ?? $sale->created_at;
        $dateStr = $soldAt?->format('d-m-Y') ?? now()->format('d-m-Y');
        $timeStr = $soldAt?->format('H:i') ?? now()->format('H:i');
        $lines[] = $this->row('FECHA: '.$dateStr, 'HORA: '.$timeStr, $width);
        $lines[] = str_repeat('-', $width);

        foreach ($sale->items as $index => $item) {
            $lines = array_merge($lines, $this->formatItemLines($item, $index + 1, $rate, $width));
            $lines[] = str_repeat('-', $width);
        }

        $subtotalUsd = (float) $sale->subtotal;
        $discountUsd = (float) $sale->discount_total;
        $taxUsd = (float) $sale->tax_total;
        $igtfUsd = (float) ($sale->igtf_total ?? 0);

        $subtotalBs = $this->toBs($subtotalUsd, $rate);
        $discountBs = $this->toBs($discountUsd, $rate);
        $taxBs = $this->toBs($taxUsd, $rate);
        $igtfBs = $this->toBs($igtfUsd, $rate);
        $totalBs = $this->toBs((float) $sale->total, $rate);

        $taxPercent = $this->dominantTaxPercent($sale);
        $taxTag = $this->taxTagLabel($taxPercent);

        $lines[] = $this->row('SUBTTL', $this->bs($subtotalBs), $width);
        if ($discountUsd > 0.00001) {
            $lines[] = $this->row('DESCUENTO', $this->bs($discountBs), $width);
        }
        $lines[] = str_repeat('-', $width);
        $netMerchUsd = max(0.0, round($subtotalUsd - $discountUsd, 2));
        $netMerchBs = $this->toBs($netMerchUsd, $rate);
        $lines[] = $this->row('BASE NETA', $this->bs($netMerchBs), $width);
        if ($taxUsd > 0.00001) {
            $lines[] = $this->row('IVA '.$taxTag, $this->bs($taxBs), $width);
        }
        if ($igtfUsd > 0.00001) {
            $invoiceBeforeIgtf = max(0.0, $netMerchUsd + $taxUsd);
            $igtfPct = $invoiceBeforeIgtf > 0.00001
                ? round($igtfUsd / $invoiceBeforeIgtf * 100, 2)
                : 0.0;
            $igtfTag = 'G '.rtrim(rtrim(number_format($igtfPct, 2, ',', '.'), '0'), ',').'%';
            $lines[] = $this->row('IGTF '.$igtfTag, $this->bs($igtfBs), $width);
        }
        $lines[] = str_repeat('-', $width);
        $lines[] = $this->row('TOTAL', $this->bs($totalBs), $width);
        $lines[] = $this->row($this->paymentLabel($sale->payment_method), $this->bs($totalBs), $width);
        $bcvStored = (float) ($sale->bcv_ves_per_usd ?? 0);
        if ($bcvStored > 0) {
            $lines[] = $this->leftRow('TASA BCV:', '1 USD = Bs '.number_format($bcvStored, 6, ',', '.'));
        }
        $lines[] = '';
        $lines[] = $this->row((string) config('fiscal.mh_footer', 'MH'), (string) config('fiscal.printer_serial', 'ZZP0000000'), $width);

        return implode("\n", $lines)."\n";
    }

    /**
     * Envuelve el texto en comandos ESC/POS mínimos (inicializar + corte). UTF-8 puede requerir
     * conversión a CP850 según modelo; pruebe en su hardware.
     */
    public function wrapEscPos(string $plainText): string
    {
        $init = "\x1B\x40";
        $text = str_replace("\r\n", "\n", $plainText);
        $feed = "\n\n\n";
        $cut = "\x1D\x56\x00";

        return $init.$text.$feed.$cut;
    }

    private function resolveVesUsdRate(Sale $sale): float
    {
        $stored = (float) ($sale->bcv_ves_per_usd ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $totalUsd = (float) $sale->total;
        $ves = (float) ($sale->payment_ves ?? 0);

        if ($totalUsd > 0.00001 && $ves > 0) {
            return $ves / $totalUsd;
        }

        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
    }

    private function toBs(float $usdAmount, float $rate): float
    {
        return round($usdAmount * $rate, 2);
    }

    private function bs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    private function invoiceNumber(Sale $sale): string
    {
        if (preg_match('/(\d+)/', (string) $sale->sale_number, $m)) {
            $digits = preg_replace('/\D/', '', $m[1]);

            return str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);
        }

        return str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    private function formatItemLines(SaleItem $item, int $lineIndex, float $rate, int $width): array
    {
        $code = str_pad((string) min(9999, max(1, $lineIndex)), 4, '0', STR_PAD_LEFT);
        $name = Str::upper((string) ($item->product_name_snapshot ?? 'PRODUCTO'));
        $tag = ((float) $item->tax_amount > 0.00001) ? 'G' : 'E';
        $desc = $code.'/'.$name.' ('.$tag.')';
        $wrapped = $this->wrapUpper($desc, $width);
        $lineTotalBs = $this->toBs((float) $item->line_total, $rate);
        $priceStr = $this->bs($lineTotalBs);

        if ($wrapped === []) {
            return [$this->row('', $priceStr, $width)];
        }

        if (count($wrapped) === 1) {
            return [$this->row($wrapped[0], $priceStr, $width)];
        }

        $out = [];
        foreach ($wrapped as $i => $line) {
            if ($i === count($wrapped) - 1) {
                $out[] = $this->row($line, $priceStr, $width);
            } else {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function dominantTaxPercent(Sale $sale): float
    {
        $tax = (float) $sale->tax_total;

        if ($tax <= 0.00001) {
            return 0.0;
        }

        $netMerch = max(0.0, round((float) $sale->subtotal - (float) $sale->discount_total, 2));

        if ($netMerch <= 0.00001) {
            return DefaultVatRate::percent();
        }

        return round($tax / $netMerch * 100, 2);
    }

    private function taxTagLabel(float $percent): string
    {
        $p = number_format($percent, 2, ',', '.');

        return 'G '.$p.'%';
    }

    private function clientDocument(?Client $client): string
    {
        if (! $client) {
            return '—';
        }

        $doc = trim((string) ($client->document_number ?? ''));

        return $doc !== '' ? $doc : '—';
    }

    private function paymentLabel(?string $method): string
    {
        return match ($method) {
            'efectivo_usd' => 'EFECTIVO USD',
            'efectivo_ves' => 'EFECTIVO VES',
            'transfer_ves' => 'TRANSF. VES',
            'pago_movil' => 'PAGO MOVIL',
            'zelle' => 'ZELLE',
            'mixed' => 'PAGO MULTIPLE',
            'transfer_usd' => 'T. TRANSFER USD',
            default => 'PAGO: '.Str::upper((string) ($method ?? '—')),
        };
    }

    /**
     * Solo encabezado (SENIAT, razón social, dirección sucursal): centrado y mayúsculas.
     */
    private function centerHeader(string $text, int $width): string
    {
        $t = Str::upper(trim($text));
        if ($t === '') {
            return '';
        }
        if (mb_strlen($t) >= $width) {
            return mb_substr($t, 0, $width);
        }

        $pad = (int) floor(($width - mb_strlen($t)) / 2);

        return str_repeat(' ', max(0, $pad)).$t;
    }

    /**
     * Etiqueta + valor en una línea centrada (encabezado únicamente).
     */
    private function centerHeaderRow(string $left, string $right, int $width): string
    {
        $left = Str::upper(trim($left));
        $right = Str::upper(trim($right));
        if ($left === '' && $right === '') {
            return '';
        }
        if ($left === '') {
            return $this->centerHeader($right, $width);
        }
        if ($right === '') {
            return $this->centerHeader($left, $width);
        }

        return $this->centerHeader($left.' '.$right, $width);
    }

    /**
     * Dirección de sucursal en el encabezado: mayúsculas, envuelta y cada línea centrada.
     *
     * @return list<string>
     */
    private function wrapUpperCentered(string $text, int $width): array
    {
        $wrapped = $this->wrapUpper($text, $width);
        $out = [];
        foreach ($wrapped as $line) {
            if (trim($line) === '') {
                continue;
            }
            $out[] = $this->centerHeader($line, $width);
        }

        return $out;
    }

    /**
     * Etiqueta y valor seguidos, alineados a la izquierda (sin relleno entre columnas).
     */
    private function leftRow(string $label, string $value): string
    {
        return trim($label).' '.trim($value);
    }

    private function row(string $left, string $right, int $width): string
    {
        $left = trim($left);
        $right = trim($right);
        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space >= 1) {
            return $left.str_repeat(' ', $space).$right;
        }

        return $left.' '.$right;
    }

    /**
     * @return list<string>
     */
    private function wrapUpper(string $text, int $width): array
    {
        $text = Str::upper(trim($text));
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }

            if ($word === '') {
                continue;
            }

            $try = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($try) <= $width) {
                $current = $try;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines !== [] ? $lines : [''];
    }
}
