<?php

use App\Enums\ConvenioType;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyDocumentType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\SupplierReturnStatus;

test('los enums exponen etiquetas en español y opciones coherentes', function () {
    expect(SaleStatus::Draft->label())->toBe('Borrador')
        ->and(count(SaleStatus::options()))->toBe(count(SaleStatus::cases()));

    expect(OrderStatus::Pending->label())->toBe('Pendiente')
        ->and(count(OrderStatus::options()))->toBe(count(OrderStatus::cases()));

    expect(PurchaseStatus::Ordered->label())->toBe('Pedido al proveedor')
        ->and(count(PurchaseStatus::options()))->toBe(count(PurchaseStatus::cases()));

    expect(ProductType::Medication->label())->toBe('Medicamento')
        ->and(count(ProductType::options()))->toBe(count(ProductType::cases()));

    expect(ConvenioType::Eps->label())->toBe('EPS')
        ->and(count(ConvenioType::options()))->toBe(count(ConvenioType::cases()));

    expect(InventoryMovementType::Purchase->label())->toBe('Compra')
        ->and(count(InventoryMovementType::options()))->toBe(count(InventoryMovementType::cases()));

    expect(PettyCashFundStatus::PendingApproval->label())->toBe('Por aprobar')
        ->and(count(PettyCashFundStatus::options()))->toBe(count(PettyCashFundStatus::cases()));

    expect(PettyCashCurrency::Usd->label())->toBe('Dólares (USD)')
        ->and(count(PettyCashCurrency::options()))->toBe(count(PettyCashCurrency::cases()));

    expect(PartnerCompanyPaymentModality::Credit->label())->toBe('Crédito')
        ->and(PartnerCompanyPaymentModality::Cash->usesCreditLimit())->toBeFalse()
        ->and(PartnerCompanyPaymentModality::Integral->usesCreditLimit())->toBeTrue();

    expect(PartnerCompanyClientType::HealthCenter->label())->toBe('Clínica / centro de salud')
        ->and(count(PartnerCompanyClientType::options()))->toBe(count(PartnerCompanyClientType::cases()));

    expect(PartnerAgreementStatus::Active->label())->toBe('Vigente')
        ->and(count(PartnerAgreementStatus::options()))->toBe(count(PartnerAgreementStatus::cases()));

    expect(PartnerAgreementType::Renewal->label())->toBe('Renovación')
        ->and(PartnerAgreementType::Initial->requiresPreviousAgreement())->toBeFalse();

    expect(SupplierReturnStatus::PendingReview->label())->toBe('Por revisar')
        ->and(count(SupplierReturnStatus::options()))->toBe(count(SupplierReturnStatus::cases()));

    expect(PartnerCompanyDocumentType::CommercialRegistry->label())->toBe('Registro Mercantil')
        ->and(count(PartnerCompanyDocumentType::options()))->toBe(count(PartnerCompanyDocumentType::cases()));
});

test('tryLabel resuelve instancias y cadenas', function () {
    expect(InventoryMovementType::tryLabel(InventoryMovementType::Sale))->toBe('Venta')
        ->and(InventoryMovementType::tryLabel('venta'))->toBe('Venta')
        ->and(InventoryMovementType::tryLabel('unknown'))->toBe('unknown');
});
