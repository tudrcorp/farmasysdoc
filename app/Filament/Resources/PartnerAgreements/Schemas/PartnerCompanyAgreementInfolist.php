<?php

namespace App\Filament\Resources\PartnerAgreements\Schemas;

use App\Enums\PartnerAgreementEventType;
use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompanyAgreement;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class PartnerCompanyAgreementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Convenio')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Número')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable()
                                    ->size(TextSize::Large),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (PartnerAgreementStatus $state): string => $state->label())
                                    ->badge()
                                    ->color(fn (PartnerAgreementStatus $state): string => $state->color()),
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->formatStateUsing(fn (PartnerAgreementType $state, PartnerCompanyAgreement $record): string => $state->label().($record->is_automatic ? ' (automática)' : ''))
                                    ->badge()
                                    ->color(fn (PartnerAgreementType $state): string => $state->color()),
                                TextEntry::make('partnerCompany.legal_name')
                                    ->label('Compañía aliada')
                                    ->weight(FontWeight::SemiBold)
                                    ->url(fn (PartnerCompanyAgreement $record): string => PartnerCompanyResource::getUrl('view', ['record' => $record->partner_company_id])),
                                TextEntry::make('previousAgreement.number')
                                    ->label('Versión anterior')
                                    ->placeholder('—')
                                    ->url(fn (PartnerCompanyAgreement $record): ?string => $record->previous_agreement_id
                                        ? PartnerCompanyAgreementResource::getUrl('view', ['record' => $record->previous_agreement_id])
                                        : null),
                                TextEntry::make('nextAgreement.number')
                                    ->label('Versión siguiente')
                                    ->placeholder('—')
                                    ->url(fn (PartnerCompanyAgreement $record): ?string => $record->nextAgreement
                                        ? PartnerCompanyAgreementResource::getUrl('view', ['record' => $record->nextAgreement])
                                        : null),
                                TextEntry::make('change_reason')
                                    ->label('Motivo')
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Vigencia')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('starts_on')->label('Inicio')->date('d/m/Y'),
                                TextEntry::make('ends_on')->label('Fin')->date('d/m/Y'),
                                TextEntry::make('days_left')
                                    ->label('Días para vencer')
                                    ->state(fn (PartnerCompanyAgreement $record): ?string => self::daysLeftLabel($record))
                                    ->placeholder('—')
                                    ->badge()
                                    ->color(fn (PartnerCompanyAgreement $record): string => self::daysLeftColor($record)),
                                IconEntry::make('auto_renews')
                                    ->label('Renovación automática')
                                    ->boolean()
                                    ->hint(fn (PartnerCompanyAgreement $record): ?string => $record->auto_renews
                                        ? ($record->renewal_term_months ? "Cada {$record->renewal_term_months} meses" : 'Mismo plazo')
                                        : null),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Condiciones negociadas')
                    ->description('Entre paréntesis, el valor de la versión anterior cuando cambió.')
                    ->icon(Heroicon::Scale)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('payment_modality')
                                    ->label('Modalidad de pago')
                                    ->formatStateUsing(fn (PartnerCompanyPaymentModality $state, PartnerCompanyAgreement $record): string => $state->label()
                                        .self::previousValue($record, fn (PartnerCompanyAgreement $previous): ?string => $previous->payment_modality !== $state ? $previous->payment_modality->label() : null))
                                    ->badge()
                                    ->color(fn (PartnerCompanyPaymentModality $state): string => $state->color()),
                                TextEntry::make('credit_limit_usd')
                                    ->label('Límite de crédito')
                                    ->formatStateUsing(fn ($state, PartnerCompanyAgreement $record): string => self::money($state)
                                        .self::previousValue($record, fn (PartnerCompanyAgreement $previous): ?string => (float) $previous->credit_limit_usd !== (float) $state ? self::money($previous->credit_limit_usd) : null))
                                    ->placeholder('No aplica'),
                                TextEntry::make('discount_percentage')
                                    ->label('Descuento')
                                    ->formatStateUsing(fn ($state, PartnerCompanyAgreement $record): string => self::percent($state)
                                        .self::previousValue($record, fn (PartnerCompanyAgreement $previous): ?string => (float) $previous->discount_percentage !== (float) $state ? self::percent($previous->discount_percentage) : null))
                                    ->placeholder('—'),
                                TextEntry::make('profit_percentage_a')
                                    ->label('Ganancia % A')
                                    ->formatStateUsing(fn ($state): string => self::percent($state))
                                    ->placeholder('—'),
                                TextEntry::make('terms')
                                    ->label('Términos')
                                    ->placeholder('Sin términos registrados.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contrato firmado y aprobación')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('signed_document_name')
                                    ->label('Contrato')
                                    ->state(fn (PartnerCompanyAgreement $record): ?string => filled($record->signed_document_path)
                                        ? ($record->signed_document_name ?: basename((string) $record->signed_document_path))
                                        : null)
                                    ->placeholder('Sin contrato adjunto')
                                    ->url(fn (PartnerCompanyAgreement $record): ?string => $record->signedDocumentUrl())
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::PaperClip)
                                    ->color(fn (PartnerCompanyAgreement $record): ?string => filled($record->signed_document_path) ? 'primary' : 'danger'),
                                TextEntry::make('signed_on')->label('Firmado el')->date('d/m/Y')->placeholder('—'),
                                TextEntry::make('creator.name')->label('Creado por')->placeholder('—'),
                                TextEntry::make('submitter.name')
                                    ->label('Enviado por')
                                    ->placeholder('—')
                                    ->hint(fn (PartnerCompanyAgreement $record): ?string => $record->submitted_at?->format('d/m/Y H:i')),
                                TextEntry::make('approver.name')
                                    ->label('Aprobado por')
                                    ->placeholder(fn (PartnerCompanyAgreement $record): string => $record->is_automatic ? 'Renovación automática' : '—')
                                    ->hint(fn (PartnerCompanyAgreement $record): ?string => $record->approved_at?->format('d/m/Y H:i')),
                                TextEntry::make('rejection_reason')
                                    ->label('Motivo del rechazo')
                                    ->color('danger')
                                    ->visible(fn (PartnerCompanyAgreement $record): bool => filled($record->rejection_reason))
                                    ->hint(fn (PartnerCompanyAgreement $record): ?string => $record->rejecter?->name)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextEntry::make('cancellation_reason')
                                    ->label('Motivo de la anulación')
                                    ->color('danger')
                                    ->visible(fn (PartnerCompanyAgreement $record): bool => filled($record->cancellation_reason))
                                    ->hint(fn (PartnerCompanyAgreement $record): ?string => $record->canceller?->name)
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Bitácora')
                    ->description('Historial inmutable de cambios de estado, avisos y ajustes aplicados al aliado.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->placeholder('Sin eventos.')
                            ->table([
                                TableColumn::make('Fecha')->width('16%'),
                                TableColumn::make('Evento')->width('22%'),
                                TableColumn::make('Estado')->width('22%'),
                                TableColumn::make('Usuario')->width('14%'),
                                TableColumn::make('Detalle'),
                            ])
                            ->schema([
                                TextEntry::make('created_at')->dateTime('d/m/Y H:i'),
                                TextEntry::make('type')
                                    ->formatStateUsing(fn (PartnerAgreementEventType $state): string => $state->label())
                                    ->badge()
                                    ->color(fn (PartnerAgreementEventType $state): string => $state->color()),
                                TextEntry::make('to_status')
                                    ->state(fn ($record): ?string => $record->to_status
                                        ? trim(($record->from_status?->label() ?? '').' → '.$record->to_status->label(), ' →')
                                        : null)
                                    ->placeholder('—'),
                                TextEntry::make('user.name')->placeholder('Sistema'),
                                TextEntry::make('notes')
                                    ->state(fn ($record): ?string => self::eventDetail($record))
                                    ->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function daysLeftLabel(PartnerCompanyAgreement $record): ?string
    {
        if (! in_array($record->status, [PartnerAgreementStatus::Active, PartnerAgreementStatus::Approved], true)) {
            return null;
        }

        $days = $record->daysUntilEnd();

        return match (true) {
            $days < 0 => 'Vencido',
            $days === 0 => 'Vence hoy',
            $days === 1 => '1 día',
            default => $days.' días',
        };
    }

    public static function daysLeftColor(PartnerCompanyAgreement $record): string
    {
        $days = $record->daysUntilEnd();

        return match (true) {
            $days <= 7 => 'danger',
            $days <= 30 => 'warning',
            $days <= 60 => 'info',
            default => 'success',
        };
    }

    private static function eventDetail(mixed $event): ?string
    {
        $parts = array_filter([$event->notes]);
        $meta = (array) ($event->meta ?? []);

        if ($event->type === PartnerAgreementEventType::TermsApplied && array_key_exists('new_balance', $meta)) {
            $parts[] = sprintf(
                'Límite %s → %s · Saldo %s → %s',
                self::money($meta['previous_limit'] ?? null),
                self::money($meta['new_limit'] ?? null),
                self::money($meta['previous_balance'] ?? null),
                self::money($meta['new_balance'] ?? null),
            );
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private static function previousValue(PartnerCompanyAgreement $record, callable $resolver): string
    {
        $previous = $record->previousAgreement;

        if (! $previous instanceof PartnerCompanyAgreement) {
            return '';
        }

        $value = $resolver($previous);

        return $value !== null ? ' (antes: '.$value.')' : '';
    }

    private static function money(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : '$'.number_format((float) $value, 2, ',', '.');
    }

    private static function percent(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }
}
