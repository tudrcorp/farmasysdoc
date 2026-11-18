<?php

namespace App\Models;

use App\Enums\PosHeldSaleSource;
use App\Enums\PosHeldSaleStatus;
use Database\Factories\PosHeldSaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Venta pausada en la caja: guarda el carrito (productos, cantidades, cliente y descuentos)
 * para que el cajero atienda a otro cliente y la retome después. No aparta existencia.
 */
class PosHeldSale extends Model
{
    /** @use HasFactory<PosHeldSaleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'status',
        'source',
        'whatsapp_conversation_id',
        'client_id',
        'label',
        'note',
        'form_data',
        'item_lines',
        'items_count',
        'estimated_total_usd',
        'paused_at',
        'last_reminded_at',
        'manager_notified_at',
        'completed_at',
        'sale_id',
        'discarded_at',
        'discarded_by_user_id',
        'discard_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PosHeldSaleStatus::class,
            'source' => PosHeldSaleSource::class,
            'form_data' => 'array',
            'item_lines' => 'array',
            'items_count' => 'integer',
            'estimated_total_usd' => 'decimal:2',
            'paused_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'manager_notified_at' => 'datetime',
            'completed_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<WhatsappConversation, $this>
     */
    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discarded_by_user_id');
    }

    /**
     * @param  Builder<PosHeldSale>  $query
     * @return Builder<PosHeldSale>
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', PosHeldSaleStatus::Paused->value);
    }

    /**
     * Ventas en pausa que el usuario puede ver: el cajero las suyas y los pedidos de WhatsApp de su sucursal,
     * el gerente las de sus sucursales y el administrador todas.
     *
     * @param  Builder<PosHeldSale>  $query
     * @return Builder<PosHeldSale>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        if (! $user->isManager()) {
            $branchIds = self::cashierBranchIds($user);

            return $query->where(function (Builder $scoped) use ($user, $branchIds): void {
                $scoped->where($scoped->qualifyColumn('user_id'), $user->getKey());

                if ($branchIds !== []) {
                    $scoped->orWhere(function (Builder $whatsapp) use ($branchIds): void {
                        $whatsapp->where($whatsapp->qualifyColumn('source'), PosHeldSaleSource::Whatsapp->value)
                            ->whereIn($whatsapp->qualifyColumn('branch_id'), $branchIds);
                    });
                }
            });
        }

        $branchIds = $user->restrictedBranchIdsForQueries();

        return $query->where(function (Builder $scoped) use ($user, $branchIds): void {
            $scoped->where('user_id', $user->getKey());

            if ($branchIds !== []) {
                $scoped->orWhereIn('branch_id', $branchIds);
            }
        });
    }

    /**
     * Pedidos de WhatsApp para retiro en tienda: los retoma cualquier cajero de la sucursal.
     *
     * @param  Builder<PosHeldSale>  $query
     * @return Builder<PosHeldSale>
     */
    public function scopeWhatsappForBranches(Builder $query, User $user): Builder
    {
        $branchIds = self::cashierBranchIds($user);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('source', PosHeldSaleSource::Whatsapp->value)
            ->whereIn('branch_id', $branchIds);
    }

    public function isFromWhatsapp(): bool
    {
        return $this->source === PosHeldSaleSource::Whatsapp;
    }

    /**
     * Sucursales donde el usuario cobra: la de su usuario y las de su rol.
     *
     * @return list<int>
     */
    public static function cashierBranchIds(User $user): array
    {
        $ids = $user->restrictedBranchIdsForQueries();

        if (filled($user->branch_id)) {
            $ids[] = (int) $user->branch_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function minutesPaused(): int
    {
        return $this->paused_at !== null
            ? (int) max(0, $this->paused_at->diffInMinutes(now()))
            : 0;
    }
}
