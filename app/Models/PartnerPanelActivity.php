<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora del panel dale · Aliados: cada acción sobre usuarios, permisos y afiliados de un aliado,
 * hecha por su equipo («aliado») o por Farmadoc («farmadoc»). Solo se inserta, nunca se edita.
 */
class PartnerPanelActivity extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_PARTNER = 'aliado';

    public const ACTOR_FARMADOC = 'farmadoc';

    public const ACTOR_SYSTEM = 'sistema';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'user_id',
        'actor_name',
        'actor_type',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'subject_label',
        'properties',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public static function actions(): array
    {
        return [
            'user_created' => ['label' => 'Usuario creado', 'color' => 'success', 'icon' => 'heroicon-m-user-plus'],
            'user_updated' => ['label' => 'Usuario modificado', 'color' => 'info', 'icon' => 'heroicon-m-pencil-square'],
            'permissions_changed' => ['label' => 'Permisos cambiados', 'color' => 'warning', 'icon' => 'heroicon-m-shield-check'],
            'admin_granted' => ['label' => 'Administrador designado', 'color' => 'primary', 'icon' => 'heroicon-m-star'],
            'admin_revoked' => ['label' => 'Administrador retirado', 'color' => 'gray', 'icon' => 'heroicon-m-star'],
            'user_suspended' => ['label' => 'Acceso suspendido', 'color' => 'danger', 'icon' => 'heroicon-m-no-symbol'],
            'user_reactivated' => ['label' => 'Acceso reactivado', 'color' => 'success', 'icon' => 'heroicon-m-check-circle'],
            'user_removed' => ['label' => 'Usuario retirado', 'color' => 'danger', 'icon' => 'heroicon-m-user-minus'],
            'access_sent' => ['label' => 'Acceso enviado', 'color' => 'gray', 'icon' => 'heroicon-m-paper-airplane'],
            'affiliate_deactivated' => ['label' => 'Afiliado dado de baja', 'color' => 'danger', 'icon' => 'heroicon-m-user-minus'],
            'affiliate_reactivated' => ['label' => 'Afiliado reincorporado', 'color' => 'success', 'icon' => 'heroicon-m-user-plus'],
            'affiliates_imported' => ['label' => 'Carga de afiliados', 'color' => 'info', 'icon' => 'heroicon-m-arrow-up-tray'],
            'affiliate_enrolled' => ['label' => 'Afiliado incorporado', 'color' => 'success', 'icon' => 'heroicon-m-user-plus'],
            'fund_recharged' => ['label' => 'Fondo recargado', 'color' => 'success', 'icon' => 'heroicon-m-banknotes'],
        ];
    }

    public function actionLabel(): string
    {
        return self::actions()[$this->action]['label'] ?? $this->action;
    }

    public function actorTypeLabel(): string
    {
        return match ($this->actor_type) {
            self::ACTOR_PARTNER => 'Su equipo',
            self::ACTOR_FARMADOC => 'Farmadoc',
            default => 'Sistema',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }
}
