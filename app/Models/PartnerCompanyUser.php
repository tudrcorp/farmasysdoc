<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación explícita: una compañía aliada tiene N usuarios del panel vinculados mediante esta tabla.
 * Cada `user_id` solo puede aparecer una vez (un usuario pertenece a una sola compañía aliada en este esquema).
 *
 * @property-read PartnerCompany $partnerCompany
 * @property-read User $user
 */
class PartnerCompanyUser extends Model
{
    protected $table = 'partner_company_users';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
