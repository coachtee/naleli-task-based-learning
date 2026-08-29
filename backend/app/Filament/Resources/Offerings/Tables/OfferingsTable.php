<?php

namespace App\Filament\Resources\Offerings\Tables;

use App\Enums\OfferingStatus;
use App\Models\Offering;
use App\Services\Billing\FeeSchedule;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfferingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium')
                    // The commercial terms sit under the name rather than in
                    // their own column: a price that wraps onto four lines is
                    // harder to read than one that sits where you look first.
                    ->description(fn ($record): string => $record->code.'  ·  '.$record->terms()),

                TextColumn::make('billing_model')
                    ->label('Billed as')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'fixed_block' => 'primary',
                        'subscription' => 'gray',
                        default => 'info',
                    }),

                TextColumn::make('price_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => 'R'.number_format($state / 100, 2))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('access_duration_days')
                    ->label('Access')
                    ->formatStateUsing(fn ($state, $record): string => $record->accessMonths())
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'open' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('No offerings')
            ->emptyStateDescription('An offering is how a programme is sold: its price, billing model and access duration.')
            ->filters([
                //
            ])
            ->recordActions([
                // Opening an offering is the moment a price becomes real, so
                // it is a deliberate act with the invoice shape shown first.
                // A block priced at zero, or one that would bill the wrong
                // number of times, is refused here rather than discovered by
                // a learner at the payment page.
                Action::make('openForSale')
                    ->label('Open for sale')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Offering $record): bool => $record->status === OfferingStatus::DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Offering $record): string => "Open {$record->name}")
                    ->modalDescription(function (Offering $record): string {
                        if ($record->price_cents === 0) {
                            return 'This offering is priced at R0.00. Set a price before opening it.';
                        }

                        try {
                            $lines = app(FeeSchedule::class)->linesFor($record);
                        } catch (DomainException $e) {
                            return $e->getMessage();
                        }

                        $count = count($lines);
                        $shape = $count === 1
                            ? 'one invoice'
                            : "{$count} invoices";

                        return "{$record->terms()} — learners will be billed as {$shape}.";
                    })
                    ->action(function (Offering $record): void {
                        if ($record->price_cents === 0) {
                            Notification::make()
                                ->title('Set a price first')
                                ->body('An offering priced at R0.00 cannot be opened for sale.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $lines = app(FeeSchedule::class)->linesFor($record);
                            app(FeeSchedule::class)->assertConsistent($record, $lines);
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot open this offering')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => OfferingStatus::OPEN]);

                        Notification::make()
                            ->title('Open for sale')
                            ->body("{$record->terms()} · ".count($lines).' invoice(s) per enrolment.')
                            ->success()
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
