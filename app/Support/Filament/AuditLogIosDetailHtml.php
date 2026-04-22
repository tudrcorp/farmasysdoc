<?php

namespace App\Support\Filament;

use App\Models\AuditLog;
use App\Support\Audit\AuditLogEventPresentation;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

final class AuditLogIosDetailHtml
{
    public static function build(AuditLog $log): HtmlString
    {
        $event = (string) ($log->event ?? '');
        $eventLabel = AuditLogEventPresentation::label($event);
        $pillClass = 'farmadoc-sale-sheet__pill farmadoc-sale-sheet__pill--'.self::pillVariant($event);

        $when = $log->created_at instanceof Carbon
            ? $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            : '—';

        $uid = filled($log->uid) ? e((string) $log->uid) : '—';

        $alert = self::importanceBanner($event, $eventLabel);

        $roles = $log->roles_snapshot;
        $rolesList = self::rolesHtml($roles);

        $props = $log->properties;
        $propsBlock = self::propertiesHtml($props);

        $audType = filled($log->auditable_type) ? e((string) $log->auditable_type) : '—';
        $audBase = filled($log->auditable_type) ? e(class_basename((string) $log->auditable_type)) : '—';

        $html = '<div class="farmadoc-sale-sheet farmadoc-audit-detail" role="region" aria-label="Detalle de traza de auditoría">';

        $html .= $alert;

        $html .= '<header class="farmadoc-sale-sheet__summary farmadoc-audit-detail__hero" aria-labelledby="farmadoc-audit-uid-title">'
            .'<div class="farmadoc-sale-sheet__summary-top">'
            .'<div class="farmadoc-sale-sheet__summary-id">'
            .'<span id="farmadoc-audit-uid-title" class="farmadoc-sale-sheet__eyebrow">Identificador único (UID)</span>'
            .'<p class="farmadoc-audit-detail__uid">'.$uid.'</p>'
            .'<p class="farmadoc-audit-detail__meta-line">ID interno: <strong>'.e((string) $log->id).'</strong> · Registrado: <strong>'.e($when).'</strong></p>'
            .'</div>'
            .'<span class="'.$pillClass.'" role="status">'.e($eventLabel).'</span>'
            .'</div>'
            .'<p class="farmadoc-audit-detail__tech-event">Código de evento: <code class="farmadoc-audit-detail__code">'.e($event !== '' ? $event : '—').'</code></p>'
            .'</header>';

        $html .= self::section(
            'Qué ocurrió (narrativa)',
            'Texto legible generado al momento del evento; priorizar esta lectura para entender la acción.',
            self::dlRows([
                ['Descripción', filled($log->description) ? nl2br(e((string) $log->description)) : '<span class="farmadoc-audit-detail__empty">Sin descripción</span>'],
            ]),
        );

        $html .= self::section(
            'Usuario y contexto de identidad',
            'Quién ejecutó la acción y qué roles tenía en ese instante (instantánea, no el estado actual del usuario).',
            self::dlRows([
                ['ID de usuario (BD)', $log->user_id !== null ? e((string) $log->user_id) : '<span class="farmadoc-audit-detail__empty">Sin usuario (p. ej. login fallido o sistema)</span>'],
                ['Correo en el log', filled($log->user_email) ? e((string) $log->user_email) : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['Roles capturados', $rolesList],
            ]),
        );

        $html .= self::section(
            'Entidad de negocio enlazada',
            'Modelo afectado cuando el evento proviene de datos (created / updated / deleted). Vacío en trazas solo HTTP o autenticación sin modelo.',
            self::dlRows([
                ['Clase completa (FQCN)', '<span class="farmadoc-audit-detail__break">'.$audType.'</span>'],
                ['Nombre corto (clase)', $audBase],
                ['ID del registro', filled($log->auditable_id) ? e((string) $log->auditable_id) : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['Etiqueta legible', filled($log->auditable_label) ? e((string) $log->auditable_label) : '<span class="farmadoc-audit-detail__empty">—</span>'],
            ]),
        );

        $url = filled($log->url) ? e((string) $log->url) : '<span class="farmadoc-audit-detail__empty">—</span>';
        $ua = filled($log->user_agent) ? e((string) $log->user_agent) : '<span class="farmadoc-audit-detail__empty">—</span>';

        $html .= self::section(
            'Petición HTTP y entorno',
            'Contexto técnico de la petición: útil para correlacionar con proxies, WAF o revisiones de seguridad.',
            self::dlRows([
                ['Panel', filled($log->panel_id) ? e((string) $log->panel_id) : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['Método HTTP', filled($log->http_method) ? e((string) $log->http_method) : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['Nombre de ruta', filled($log->route_name) ? '<code class="farmadoc-audit-detail__code">'.e((string) $log->route_name).'</code>' : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['URL completa', '<span class="farmadoc-audit-detail__break">'.$url.'</span>'],
                ['Dirección IP', filled($log->ip_address) ? e((string) $log->ip_address) : '<span class="farmadoc-audit-detail__empty">—</span>'],
                ['User-Agent', '<pre class="farmadoc-audit-detail__pre-inline">'.$ua.'</pre>'],
            ]),
        );

        $html .= self::section(
            'Payload y metadatos técnicos (JSON)',
            'Cambios de atributos, parámetros o contexto adicional. Puede incluir datos truncados si el payload era demasiado grande.',
            '<div class="farmadoc-audit-detail__json-wrap">'.$propsBlock.'</div>',
        );

        $html .= '<footer class="farmadoc-sale-sheet__footer farmadoc-audit-detail__footer">'
            .'Esta traza es inmutable y refleja el estado capturado en el momento del evento. Para investigaciones, combine UID, marca de tiempo, IP y payload con los registros de su infraestructura.'
            .'</footer>'
            .'</div>';

        return new HtmlString($html);
    }

    private static function importanceBanner(string $event, string $eventLabel): string
    {
        $e = strtolower(trim($event));

        $level = match ($e) {
            'login_failed', 'deleted' => 'critical',
            'login', 'logout', 'created' => 'high',
            'updated', 'http_request' => 'attention',
            'page_view' => 'routine',
            default => 'neutral',
        };

        if ($level === 'routine' || $level === 'neutral') {
            return '<aside class="farmadoc-audit-alert farmadoc-audit-alert--info" role="note">'
                .'<p class="farmadoc-audit-alert__title">Trazabilidad de actividad</p>'
                .'<p class="farmadoc-audit-alert__text">Evento <strong>'.e($eventLabel).'</strong>. '
                .($level === 'routine'
                    ? 'Consulta frecuente en el panel; use el payload y la ruta si necesita reconstruir la navegación.'
                    : 'Revise usuario, IP y payload para el contexto completo.')
                .'</p></aside>';
        }

        $class = match ($level) {
            'critical' => 'farmadoc-audit-alert farmadoc-audit-alert--critical',
            'high' => 'farmadoc-audit-alert farmadoc-audit-alert--high',
            'attention' => 'farmadoc-audit-alert farmadoc-audit-alert--attention',
            default => 'farmadoc-audit-alert farmadoc-audit-alert--info',
        };

        $title = match ($level) {
            'critical' => 'Acción crítica para auditoría',
            'high' => 'Evento relevante de seguridad o de datos',
            'attention' => 'Cambio o petición que conviene revisar',
            default => 'Información',
        };

        $hint = match ($e) {
            'login_failed' => 'Posible acceso no autorizado: verifique correo intentado, IP y cabeceras.',
            'deleted' => 'Eliminación de datos: confirme autorización y copias de respaldo según política.',
            'login' => 'Inicio de sesión correcto: correlacione con IP y horario de turnos.',
            'logout' => 'Cierre de sesión: útil para cadena de custodia de la sesión.',
            'created' => 'Alta de datos: valide usuario y payload frente a procedimiento operativo.',
            'updated' => 'Modificación de datos: el JSON detalla atributos tocados.',
            'http_request' => 'Acción no GET en el panel: revise método, ruta y cuerpo en payload si aplica.',
            default => 'Revise descripción y payload.',
        };

        return '<aside class="'.$class.'" role="alert">'
            .'<p class="farmadoc-audit-alert__title">'.e($title).'</p>'
            .'<p class="farmadoc-audit-alert__text">'.e($hint).'</p>'
            .'</aside>';
    }

    private static function pillVariant(string $event): string
    {
        return match (AuditLogEventPresentation::badgeColor($event)) {
            'success' => 'ok',
            'danger' => 'danger',
            'warning' => 'warn',
            'info', 'gray' => 'muted',
            default => 'muted',
        };
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private static function dlRows(array $pairs): string
    {
        $out = '<dl class="farmadoc-sale-sheet__dl">';
        foreach ($pairs as [$label, $value]) {
            $out .= '<div class="farmadoc-sale-sheet__dl-row">'
                .'<dt>'.e($label).'</dt>'
                .'<dd>'.$value.'</dd>'
                .'</div>';
        }
        $out .= '</dl>';

        return $out;
    }

    private static function section(string $title, ?string $subtitle, string $body): string
    {
        $sub = filled($subtitle)
            ? '<p class="farmadoc-sale-sheet__section-sub">'.e($subtitle).'</p>'
            : '';

        return '<section class="farmadoc-sale-sheet__section" aria-label="'.e($title).'">'
            .'<div class="farmadoc-sale-sheet__section-head">'
            .'<h2 class="farmadoc-sale-sheet__section-title">'.e($title).'</h2>'
            .$sub
            .'</div>'
            .'<div class="farmadoc-sale-sheet__section-card">'.$body.'</div>'
            .'</section>';
    }

    private static function rolesHtml(mixed $roles): string
    {
        if (! is_array($roles) || $roles === []) {
            return '<span class="farmadoc-audit-detail__empty">Sin roles en el log</span>';
        }

        $items = '';
        foreach ($roles as $key => $value) {
            if (is_int($key)) {
                $items .= '<li>'.e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)).'</li>';
            } else {
                $items .= '<li><strong>'.e((string) $key).'</strong>: '.e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)).'</li>';
            }
        }

        return '<ul class="farmadoc-audit-detail__roles">'.$items.'</ul>';
    }

    private static function propertiesHtml(mixed $props): string
    {
        if ($props === null || $props === '' || $props === []) {
            return '<p class="farmadoc-audit-detail__empty">Sin propiedades adicionales en este registro.</p>';
        }

        if (! is_array($props)) {
            if (is_string($props)) {
                $decoded = json_decode($props, true);

                return is_array($decoded)
                    ? self::propertiesHtml($decoded)
                    : '<pre class="farmadoc-audit-detail__pre">'.e((string) $props).'</pre>';
            }

            return '<p class="farmadoc-audit-detail__empty">(Formato de propiedades no reconocido)</p>';
        }

        try {
            $json = json_encode($props, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable) {
            return '<p class="farmadoc-audit-detail__empty">No se pudo serializar el payload.</p>';
        }

        return '<pre class="farmadoc-audit-detail__pre" tabindex="0">'.e($json).'</pre>';
    }
}
