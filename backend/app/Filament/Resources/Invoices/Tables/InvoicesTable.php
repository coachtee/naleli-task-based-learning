<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Payments\PaymentProviderRegistry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.id')
                    ->searchable(),
                TextColumn::make('enrolment.id')
                    ->searchable(),
                TextColumn::make('sequence')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('amount_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('due_on')
                    ->date()
                    ->sortable(),
                IconColumn::make('activates_enrolment')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // The one operation Phase 1 exists to prove. Manual today,
                // and it stays after the gateways arrive: cash and counter
                // payments do not stop.
                Action::make('confirmPayment')
                    ->label('Confirm payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::DUE)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Invoice $record): string => "Confirm payment of R{$record->amount_rands}")
                    ->modalDescription('This activates the enrolment if this is the activating invoice, and issues an access token when identity is verified.')
                    ->schema([
                        Select::make('provider')
                            ->label('Received via')
                            ->options(fn (): array => app(PaymentProviderRegistry::class)->options())
                            ->default('manual')
                            ->required(),
                        TextInput::make('reference')
                            ->label('Reference')
                            ->helperText('Bank statement line, receipt number, or leave blank.')
                            ->maxLength(120),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $result = app(EnrolmentActivator::class)->confirmInvoiceManually(
                            invoice: $record,
                            providerKey: $data['provider'],
                            reference: $data['reference'] ?? null,
                            actor: auth()->user(),
                        );

                        $body = $result['already_settled']
                            ? 'This invoice was already settled — nothing changed.'
                            : 'Enrolment and entitlements updated.';

                        if ($result['plain_token'] !== null) {
                            $body .= " Access token: {$result['plain_token']} (shown once).";
                        }

                        Notification::make()
                            ->title($result['already_settled'] ? 'Already paid' : 'Payment confirmed')
                            ->body($body)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
