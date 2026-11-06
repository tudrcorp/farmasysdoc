<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Supplier extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'legal_name',
        'trade_name',
        'tax_id',
        'seniat_retention_percent',
        'email',
        'phone',
        'mobile_phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'contact_name',
        'contact_email',
        'contact_phone',
        'payment_terms',
        'negotiation_terms',
        'payment_days',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_days' => 'integer',
            'seniat_retention_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Código interno visible en listados: prefijo PROV- + id con al menos 4 dígitos (relleno con ceros).
     */
    public static function formatCode(int|string $id): string
    {
        return 'PROV-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Nombre preferente para listados y UI (comercial si existe; si no, razón social).
     */
    public function displayName(): string
    {
        if (filled($this->trade_name)) {
            return (string) $this->trade_name;
        }

        return filled($this->legal_name) ? (string) $this->legal_name : '—';
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Compras registradas con este proveedor (relación 1:N).
     *
     * El correlativo de factura del proveedor es propio de cada uno: el mismo N° de factura puede existir
     * en otro proveedor, pero no se duplica para el mismo {@see Purchase::$supplier_id}.
     *
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Cuentas bancarias del proveedor (una o varias).
     *
     * @return HasMany<SupplierBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Negociación y días de pago son atribución del Administrador: si otro usuario autenticado
     * intenta cambiarlos (por cualquier vía), se conservan los valores anteriores.
     */
    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $user = Auth::user();

            if (! $user instanceof User || $user->isAdministrator()) {
                return;
            }

            foreach (['negotiation_terms', 'payment_days'] as $attribute) {
                if ($supplier->isDirty($attribute)) {
                    $supplier->setAttribute($attribute, $supplier->exists ? $supplier->getOriginal($attribute) : null);
                }
            }
        });
    }

    public const int DEFAULT_PAYMENT_DAYS = 30;

    /**
     * Días para pagar sus facturas: los negociados por el Administrador o 30 si no se cargaron.
     */
    public function effectivePaymentDays(): int
    {
        return $this->payment_days ?? self::DEFAULT_PAYMENT_DAYS;
    }

    public function paymentDaysLabel(): string
    {
        return match (true) {
            $this->payment_days === null => 'Sin definir (30 días por defecto)',
            $this->payment_days === 0 => 'Contado',
            $this->payment_days === 1 => '1 día',
            default => $this->payment_days.' días',
        };
    }
}
