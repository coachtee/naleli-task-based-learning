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
                TextColumn::make('programme.name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('billing_model')
                    ->badge()
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deposit_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('instalment_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('access_duration_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('activation_rule')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('available_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('available_until')
                    ->date()
                    ->sortable(),
                TextColumn::make('sort_order')
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
