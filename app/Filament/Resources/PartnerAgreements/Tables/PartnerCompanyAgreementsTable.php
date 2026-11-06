<?php

namespace App\Filament\Resources\PartnerAgreements\Tables;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Filament\Resources\PartnerAgreements\Actions\PartnerAgreementActions;
use App\Filament\Resources\PartnerAgreements\Schemas\PartnerCompanyAgreementInfolist;
use App\Models\Branch;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerCompanyAgreementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['partnerCompany:id,legal_name,trade_name', 'approver:id,name']))
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('partnerCompany.legal_name')
                    ->label('Compañía aliada')
                    ->description(fn (PartnerCompanyAgreement $record): ?string => $record->partnerCompany?->trade_name)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (PartnerAgreementType $state, PartnerCompanyAgreement $record): string => $state->label().($record->is_automatic ? ' (auto)' : ''))
                    ->badge()
                    ->color(fn (PartnerAgreementType $state): string => $state->color()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (PartnerAgreementStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (PartnerAgreementStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('starts_on')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (PartnerCompanyAgreement $record): ?string => PartnerCompanyAgreementInfolist::daysLeftLabel($record))
                    ->color(fn (PartnerCompanyAgreement $record): ?string => $record->status === PartnerAgreementStatus::Active
                        ? PartnerCompanyAgreementInfolist::daysLeftColor($record)
                        : null),
                IconColumn::make('auto_renews')
                    ->label('Auto-renov.')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('payment_modality')
                    ->label('Modalidad')
                    ->formatStateUsing(fn (PartnerCompanyPaymentModality $state): string => $state->label())
                    ->badge()
                    ->color(fn (PartnerCompanyPaymentModality $state): string => $state->color()),
                TextColumn::make('credit_limit_usd')
                    ->label('Límite')
                    ->money('USD')
                    ->placeholder('No aplica')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('discount_percentage')
                    ->label('Descuento')
                    ->formatStateUsing(fn ($state): string => filled($state) ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%' : '—')
                    ->alignCenter()
                    ->toggleable(),
                IconColumn::make('signed_document_path')
                    ->label('Contrato')
                    ->state(fn (PartnerCompanyAgreement $record): bool => filled($record->signed_document_path))
                    ->boolean()
                    ->trueIcon(Heroicon::PaperClip)
                    ->falseIcon(Heroicon::ExclamationTriangle)
                    ->falseColor('warning')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('approver.name')
                    ->label('Aprobó')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ends_on')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('partner_company_id')
                    ->label('Compañía aliada')
                    ->options(fn (): array => BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())->orderBy('legal_name')->pluck('legal_name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('partnerCompany.branches', fn (Builder $branches): Builder => $branches->whereKey((int) $data['value']))
                        : $query),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PartnerAgreementStatus::options())
                    ->multiple(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(PartnerAgreementType::options())
                    ->multiple(),
                SelectFilter::make('payment_modality')
                    ->label('Modalidad')
                    ->options(PartnerCompanyPaymentModality::options())
                    ->multiple(),
                SelectFilter::make('expires_within')
                    ->label('Vigentes que vencen en')
                    ->options([7 => '7 días', 15 => '15 días', 30 => '30 días', 60 => '60 días', 90 => '90 días'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->expiringWithin((int) $data['value'])
                        : $query),
                TernaryFilter::make('auto_renews')
                    ->label('Renovación automática'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn (PartnerCompanyAgreement $record): bool => $record->status->isEditable()),
                PartnerAgreementActions::viewContract()->iconButton(),
            ])
            ->emptyStateHeading('Sin convenios')
            ->emptyStateDescription('Registre el convenio inicial de cada aliado; luego gestione aquí renovaciones, modificaciones y vencimientos.')
            ->emptyStateIcon(Heroicon::DocumentCheck);
    }
}
