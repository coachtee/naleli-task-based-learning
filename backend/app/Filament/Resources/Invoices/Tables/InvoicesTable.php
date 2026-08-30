<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Enrolment\EnrolmentActivator;
use App\Services\Payments\PayAtGo\PayAtGoClient;
use App\Services\Payments\PaymentProviderRegistry;
use App\Services\Payments\Providers\PayAtGoProvider;
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
use Throwable;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.learner_ref')
                    ->label('Learner')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record): ?string => $record->enrolment?->programme?->name),

                TextColumn::make('description')
                    ->searchable()
                    ->description(fn ($record): string => "Invoice {$record->sequence}"),

                TextColumn::make('amount_cents')
                    ->label('Amount')
                    // Cents are how money is stored; rands are how it is read.
                    ->formatStateUsing(fn (int $state): string => 'R'.number_format($state / 100, 2))
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('activates_enrolment')
                    ->label('Activates')
                    ->boolean()
                    ->trueIcon('heroicon-o-bolt')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip('Settling this invoice is what turns the enrolment on.'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    // An invoice that is late reads red at a glance rather
                    // than needing the due date to be read and compared.
                    ->color(fn ($state, Invoice $record): string => match (true) {
                        $state->value === 'due' && $record->due_on?->isPast() => 'danger',
                        $state->value === 'paid' => 'success',
                        $state->value === 'due' => 'warning',
                        $state->value === 'refunded' => 'info',
                        default => 'gray',
                    })
                    ->description(fn (Invoice $record): ?string => match (true) {
                        $record->due_on === null => null,
                        $record->status->value === 'due' && $record->due_on->isPast() => 'Overdue '.$record->due_on->format('j M Y'),
                        $record->status->value === 'due' => 'Due '.$record->due_on->format('j M Y'),
                        default => null,
                    }),

                // What the learner quotes at the till. Copyable because that
                // is what a registrar actually does with it: paste it into a
                // WhatsApp message.
                TextColumn::make('payat_source_reference')
                    ->label('Pay@ reference')
                    ->placeholder('Not issued')
                    ->copyable()
                    ->copyMessage('Pay@ reference copied')
                    ->url(fn (Invoice $record): ?string => $record->payat_payment_link)
                    ->openUrlInNewTab()
                    ->description(fn (Invoice $record): ?string => $record->payat_requested_at?->format('j M Y'))
                    ->toggleable(),

                TextColumn::make('due_on')
                    ->label('Due')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nothing invoiced')
            ->emptyStateDescription('Invoices are raised when an application is accepted against an offering.')
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
                // Mint the payable reference. Separate from confirming
                // payment on purpose: this is the school ASKING for money,
                // and it is the only Pay@ call that costs anything to get
                // wrong, so it is always deliberate and never automatic.
                Action::make('createPayAtReference')
                    ->label('Create Pay@ reference')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::DUE
                        && $record->payat_account_number === null
                        && app(PayAtGoClient::class)->isConfigured())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Invoice $record): string => "Create a Pay@ reference for R{$record->amount_rands}")
                    ->modalDescription('The learner can then pay this amount in cash at any Pay@ till. Nothing is charged until they do.')
                    ->action(function (Invoice $record): void {
                        try {
                            $intent = app(PayAtGoProvider::class)->createCheckout($record);
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Pay@ would not create the reference')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Pay@ reference created')
                            ->body('Reference '.($record->fresh()->payat_source_reference ?? $intent?->providerReference).
                                ' — send the learner the link on the invoice row.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // The safety net for a webhook that never arrives. Reads the
                // reference back from Pay@ and settles on what Pay@ says, not
                // on what anyone typed.
                Action::make('checkPayAt')
                    ->label('Check Pay@')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Invoice $record): bool => $record->payat_account_number !== null
                        && $record->status === InvoiceStatus::DUE)
                    ->action(function (Invoice $record): void {
                        try {
                            $result = app(PayAtGoProvider::class)->reconcile($record);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not reach Pay@')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        if ($result === null) {
                            Notification::make()->title('Pay@ has no record of this reference')->warning()->send();

                            return;
                        }

                        if (! $result->isSettled()) {
                            $paid = number_format($result->amountCents / 100, 2);

                            Notification::make()
                                ->title('Not paid yet')
                                ->body("Pay@ reports R{$paid} received against R{$record->amount_rands}.")
                                ->warning()
                                ->send();

                            return;
                        }

                        $settled = app(EnrolmentActivator::class)->settle($result, $record, auth()->user());

                        $body = 'Enrolment and entitlements updated.';

                        if ($settled['plain_token'] !== null) {
                            $body .= " Access token: {$settled['plain_token']} (shown once).";
                        }

                        Notification::make()
                            ->title($settled['already_settled'] ? 'Already recorded' : 'Paid at Pay@')
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
