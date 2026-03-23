<?php

namespace App\Enums;

/**
 * Tipo de convenio o cobertura asociada al pedido (alianzas con aseguradoras, EPS, etc.).
 */
enum ConvenioType: string
{
    case Particular = 'particular';
    case PrivateInsurance = 'private_insurance';
    case Eps = 'eps';
    case PrepaidMedicine = 'prepaid_medicine';
    case Corporate = 'corporate';
    case Other = 'other';
}
