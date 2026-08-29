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
                    ->color(fn ($state): string => match ($state->value) {
                        'paid' => 'success',
                        'due' => 'warning',
                        'refunded' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('due_on')
                    ->label('Due')
                    ->date('j M Y')
                    ->sortable()
                    ->color(fn ($record): string => $record->status->value === 'due' && $record->due_on?->isPast()
                        ? 'danger'
                        : 'gray'),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
