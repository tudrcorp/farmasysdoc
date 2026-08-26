<?php

namespace App\Support\Sales;

/**
 * Dinero realmente cobrado en una venta, separado por moneda.
 * Nunca mezcla VES convertido a USD ni al revés.
 */
final readonly class SaleCollectedMoney
{
    public function __construct(
        public float $pagoMovilVes = 0.0,
        public float $posVes = 0.0,
        public ?int $posTerminalId = null,
        public float $transferVes = 0.0,
        public float $efectivoVes = 0.0,
        public float $efectivoUsd = 0.0,
        public float $transferUsd = 0.0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function usdTotal(): float
    {
        return round($this->efectivoUsd + $this->transferUsd, 2);
    }

    public function vesTotal(): float
    {
        return round($this->pagoMovilVes + $this->posVes + $this->transferVes + $this->efectivoVes, 2);
    }

    public function add(self $other): self
    {
        $posTerminalId = $this->posTerminalId;
        if ($other->posVes > 0.00001 && $other->posTerminalId !== null) {
            $posTerminalId = $other->posTerminalId;
        }

        return new self(
            pagoMovilVes: round($this->pagoMovilVes + $other->pagoMovilVes, 2),
            posVes: round($this->posVes + $other->posVes, 2),
            posTerminalId: $posTerminalId,
            transferVes: round($this->transferVes + $other->transferVes, 2),
            efectivoVes: round($this->efectivoVes + $other->efectivoVes, 2),
            efectivoUsd: round($this->efectivoUsd + $other->efectivoUsd, 2),
            transferUsd: round($this->transferUsd + $other->transferUsd, 2),
        );
    }
}
