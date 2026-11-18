<?php

namespace Database\Seeders;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Models\Formulary;
use App\Models\Plan;
use App\Services\Partners\PlanCostStructureSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Modalidades de la propuesta corporativa: Conecta, Flexible y Cápita.
 *
 * Idempotente: solo crea los planes que falten (por código) y carga el baremo de Cápita
 * (subplanes Agudos e Integral) si todavía no tiene tramos. Servicios y aliados se asignan desde el panel.
 */
class CorporatePlansSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, description: string, notes: string, monthly_premium_usd: ?string, premium_mode: PlanPremiumMode}>
     */
    private const PLANS = [
        [
            'code' => 'PLN-CONECTA',
            'name' => 'Conecta · Beneficio sin afiliación',
            'description' => 'Acceso con identificación corporativa a sedes y canales digitales. Descuentos corporativos y atención directa al usuario.',
            'notes' => 'Para ampliar beneficios sin costo de afiliación para la organización.',
            'monthly_premium_usd' => null,
            'premium_mode' => PlanPremiumMode::Retail,
        ],
        [
            'code' => 'PLN-FLEXIBLE',
            'name' => 'Flexible · Fondo para consumo autorizado',
            'description' => 'Fondo inicial ajustable, debitado por solicitudes aprobadas. Acceso a Dale, alertas de saldo y reportes periódicos.',
            'notes' => 'Para financiar medicamentos con control sobre el monto fondeado. El monto autorizado se indica al asignar el plan a cada aliado.',
            'monthly_premium_usd' => null,
            'premium_mode' => PlanPremiumMode::ConsumptionFund,
        ],
        [
            'code' => 'PLN-CAPITA',
            'name' => 'Cápita · Tarifa por beneficiario',
            'description' => 'Tarifa mensual por persona asociada a un listado definido. El alcance y las condiciones se establecen en el convenio.',
            'notes' => 'Para presupuestar una cobertura farmacéutica previamente acordada. Servicio sujeto a pago anticipado.',
            'monthly_premium_usd' => null,
            'premium_mode' => PlanPremiumMode::AffiliateBands,
        ],
    ];

    /**
     * Baremo comercial de Cápita (USD por afiliado al mes): [mín, máx, Agudos, Integral].
     *
     * @var list<array{0: int, 1: int|null, 2: string, 3: string}>
     */
    private const CAPITA_BAREMO = [
        [500, 999, '4.99', '10.99'],
        [1000, 4999, '4.79', '10.49'],
        [5000, 19999, '4.49', '9.99'],
        [20000, 49999, '4.29', '9.49'],
        [50000, 99999, '3.99', '8.99'],
        [100000, null, '3.79', '8.49'],
    ];

    /**
     * Subplanes de Cápita con el catálogo propio de cada uno.
     *
     * @var array<string, array{name: string, description: string, formulary: string}>
     */
    private const CAPITA_SUBPLANS = [
        'agudos' => ['name' => 'Plan Agudos', 'description' => 'Patologías agudas.', 'formulary' => 'CAT-AGUDOS'],
        'integral' => ['name' => 'Plan Integral', 'description' => 'Patologías agudas y crónicas.', 'formulary' => 'CAT-CRONICOS'],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            Plan::query()->firstOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'notes' => $plan['notes'],
                    'status' => PlanStatus::Draft,
                    'premium_mode' => $plan['premium_mode'],
                    'monthly_premium_usd' => $plan['monthly_premium_usd'],
                    'monthly_premium_ves' => $plan['monthly_premium_usd'],
                ],
            );
        }

        $this->seedCapitaCostStructure();
    }

    /**
     * Carga el baremo y los subplanes de Cápita solo si todavía no tiene tramos.
     */
    private function seedCapitaCostStructure(): void
    {
        $capita = Plan::query()->where('code', 'PLN-CAPITA')->first();

        if ($capita === null || $capita->affiliateBands()->exists()) {
            return;
        }

        if ($capita->partnerAssignments()->exists() && ! $capita->usesAffiliateBands()) {
            return;
        }

        $capita->forceFill([
            'premium_mode' => PlanPremiumMode::AffiliateBands,
            'has_subplans' => true,
            'monthly_premium_usd' => null,
            'monthly_premium_ves' => null,
        ])->save();

        $keys = [];
        $subplans = [];
        foreach (self::CAPITA_SUBPLANS as $slug => $subplan) {
            $keys[$slug] = (string) Str::uuid();
            $formularyId = Formulary::query()->where('code', $subplan['formulary'])->value('id');

            $subplans[] = [
                'key' => $keys[$slug],
                'name' => $subplan['name'],
                'description' => $subplan['description'],
                'services' => [],
                'formularies' => $formularyId === null ? [] : [$formularyId],
            ];
        }

        $bands = array_map(fn (array $row): array => [
            'min_affiliates' => $row[0],
            'max_affiliates' => $row[1],
            'prices' => [$keys['agudos'] => $row[2], $keys['integral'] => $row[3]],
        ], self::CAPITA_BAREMO);

        app(PlanCostStructureSynchronizer::class)->sync($capita, $subplans, $bands);
    }
}
