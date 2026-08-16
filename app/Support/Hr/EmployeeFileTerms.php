<?php

namespace App\Support\Hr;

final class EmployeeFileTerms
{
    public const VERSION = '2026-08-15';

    public static function title(): string
    {
        return 'Términos y condiciones — Firma y huella';
    }

    /**
     * @return list<string>
     */
    public static function paragraphs(): array
    {
        $app = config('app.name');

        return [
            'Por medio del presente, el trabajador presta su consentimiento informado, libre y expreso para el tratamiento de su firma digitalizada y de la imagen de su huella dactilar como datos de identificación biométrica y de suscripción documental.',
            'El responsable del tratamiento es la empresa empleadora. La aplicación informática '.$app.' actúa como medio técnico para capturar, custodiar y reproducir dichos elementos en documentos oficiales de la relación laboral.',
            'Finalidades: (a) autenticar la identidad del trabajador; (b) suscribir y emitir recibos de pago de salario y demás conceptos laborales; (c) conservar evidencia de la entrega y aceptación de documentos de ley; (d) atender requerimientos de autoridades administrativas o judiciales.',
            'El trabajador declara que la firma y la huella registradas son las suyas, que conoce el uso que se les dará y que autoriza su incorporación en los documentos que la empresa genere a través de esta aplicación. El tratamiento se realizará con medidas de seguridad razonables y no se usará para fines comerciales ajenos a la relación de trabajo.',
        ];
    }

    public static function acceptanceLabel(): string
    {
        return 'He leído y acepto estos términos y condiciones. Autorizo a la empresa y a esta aplicación a usar mi firma y mi huella con fines legales y laborales.';
    }
}
