<?php

namespace App\Enums;

/**
 * Instituciones con código de identificación bancaria habitual en Venezuela para Pago Móvil / transferencias.
 * El valor del backed enum es el código de 4 dígitos enviado a la API de conciliación (bancoOrigen).
 */
enum VenezuelanPagoMovilBank: string
{
    case BancoDeVenezuela = '0102';
    case BancoVenezolanoDeCredito = '0104';
    case BancoMercantil = '0105';
    case BbvaProvincial = '0108';
    case Bancaribe = '0114';
    case BancoExterior = '0115';
    case BancoCaroni = '0128';
    case Banesco = '0134';
    case BancoSofitasa = '0137';
    case BancoPlaza = '0138';
    case Bangente = '0146';
    case BancoFondoComun = '0151';
    case CienPorCientoBanco = '0156';
    case DelSur = '0157';
    case BancoDelTesoro = '0163';
    case BancoAgricolaDeVenezuela = '0166';
    case Bancrecer = '0168';
    case MiBanco = '0169';
    case BancoActivo = '0171';
    case Bancamiga = '0172';
    case BancoInternacionalDeDesarrollo = '0173';
    case Banplus = '0174';
    case BancoBicentenario = '0175';
    case Banfanb = '0177';
    case N58BancoDigital = '0178';
    case BancoNacionalDeCredito = '0191';
    case InstitutoMunicipalCreditoPopular = '0601';

    /**
     * Nombre corto para mostrar en listas (sin repetir el código; va junto a {@see self::optionLabel()}).
     */
    public function bankName(): string
    {
        return match ($this) {
            self::BancoDeVenezuela => 'Banco de Venezuela',
            self::BancoVenezolanoDeCredito => 'Banco Venezolano de Crédito',
            self::BancoMercantil => 'Banco Mercantil',
            self::BbvaProvincial => 'BBVA Provincial',
            self::Bancaribe => 'Bancaribe',
            self::BancoExterior => 'Banco Exterior',
            self::BancoCaroni => 'Banco Caroní',
            self::Banesco => 'Banesco',
            self::BancoSofitasa => 'Banco Sofitasa',
            self::BancoPlaza => 'Banco Plaza',
            self::Bangente => 'Bangente',
            self::BancoFondoComun => 'Banco Fondo Común',
            self::CienPorCientoBanco => '100% Banco',
            self::DelSur => 'DelSur Banco Universal',
            self::BancoDelTesoro => 'Banco del Tesoro',
            self::BancoAgricolaDeVenezuela => 'Banco Agrícola de Venezuela',
            self::Bancrecer => 'Bancrecer',
            self::MiBanco => 'Mi Banco',
            self::BancoActivo => 'Banco Activo',
            self::Bancamiga => 'Bancamiga',
            self::BancoInternacionalDeDesarrollo => 'Banco Internacional de Desarrollo',
            self::Banplus => 'Banplus',
            self::BancoBicentenario => 'Banco Bicentenario del Pueblo',
            self::Banfanb => 'BANFANB',
            self::N58BancoDigital => 'N58 Banco Digital',
            self::BancoNacionalDeCredito => 'Banco Nacional de Crédito',
            self::InstitutoMunicipalCreditoPopular => 'Instituto Municipal de Crédito Popular',
        };
    }

    /**
     * Etiqueta tipo: 0102 · Banco de Venezuela
     */
    public function optionLabel(): string
    {
        return $this->value.' · '.$this->bankName();
    }

    /**
     * @return array<string, string> mapa código => etiqueta para Filament Select::options()
     */
    public static function optionsForSelect(): array
    {
        $cases = self::cases();
        usort($cases, static fn (self $a, self $b): int => strcmp($a->value, $b->value));

        $out = [];
        foreach ($cases as $case) {
            $out[$case->value] = $case->optionLabel();
        }

        return $out;
    }
}
