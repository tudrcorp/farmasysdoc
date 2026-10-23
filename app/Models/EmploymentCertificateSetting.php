<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plantilla única de la constancia de trabajo (una sola fila), cargada por el administrador.
 */
class EmploymentCertificateSetting extends Model
{
    public const string DEFAULT_TITLE = 'Constancia de trabajo';

    public const string DEFAULT_BODY = "Quien suscribe, en representación de la empresa, hace constar por medio de la presente que el(la) ciudadano(a) {nombre}, titular de la cédula de identidad N.° {cedula}, presta sus servicios en nuestra empresa desde el {fecha_ingreso}, desempeñando el cargo de {cargo} en la sucursal {sucursal}, con una antigüedad de {antiguedad}, devengando un {tipo_sueldo} mensual de {sueldo}.\n\nConstancia que se expide a petición de la parte interesada en {ciudad}, a los {fecha}.";

    /**
     * Variables que el administrador puede usar en la plantilla.
     *
     * @var array<string, string>
     */
    public const array PLACEHOLDERS = [
        '{nombre}' => 'Nombre completo del empleado',
        '{cedula}' => 'Cédula de identidad',
        '{cargo}' => 'Cargo',
        '{sucursal}' => 'Sucursal',
        '{fecha_ingreso}' => 'Fecha de ingreso',
        '{antiguedad}' => 'Tiempo en la empresa',
        '{tipo_sueldo}' => '"sueldo integral" o "sueldo de ley"',
        '{sueldo}' => 'Monto del sueldo elegido',
        '{ciudad}' => 'Ciudad de emisión',
        '{fecha}' => 'Fecha de emisión en letras',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'city',
        'signer_name',
        'signer_title',
        'signature_path',
        'stamp_path',
        'footer',
    ];

    public static function current(): self
    {
        return self::query()->oldest('id')->first()
            ?? self::query()->create(['title' => self::DEFAULT_TITLE, 'body' => self::DEFAULT_BODY]);
    }

    public function isConfigured(): bool
    {
        return filled($this->body) && filled($this->signer_name);
    }
}
