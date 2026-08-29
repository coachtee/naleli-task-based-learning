<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Enums\ApplicationStatus;
use App\Enums\OfferingStatus;
use App\Models\Application;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.learner_ref')
                    ->label('Learner')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Application $record): string => trim(
                        "{$record->learner?->first_name} {$record->learner?->last_name}"
                    ) ?: 'Name not supplied'),

                TextColumn::make('programme.name')
                    ->label('Programme')
                    ->searchable()
                    ->placeholder('Not matched to a programme')
                    ->description(fn (Application $record): ?string => $record->intake?->label),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'enrolled' => 'success',
                        'paid' => 'info',
                        'awaiting_payment', 'awaiting_identity' => 'warning',
                        'rejected', 'withdrawn' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->date('j M Y')
                    ->sortable()
                    // How long someone has been waiting is the thing that
                    // decides who gets attended to next.
                    ->description(fn (Application $record): ?string => $record->applied_at?->diffForHumans()),

                TextColumn::make('source')
                    ->label('Came from')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->description(fn (Application $record): ?string => $record->source_reference !== null
                        ? "Submission {$record->source_reference}"
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('applied_at', 'desc')
            ->emptyStateHeading('No applications yet')
            ->emptyStateDescription('Applications arrive automatically from the form on kcs.edu.za.')
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(ApplicationStatus::cases())
                        ->mapWithKeys(fn (ApplicationStatus $case): array => [$case->value => $case->label()])
                        ->all()),
            ])
            ->recordActions([
                // Admissions in one click: this raises the enrolment and the
                // invoices the offering says are owed. The offering is chosen
                // here because the price lives there, not in this decision.
                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    // Only an undecided application. awaiting_identity sits
                    // after payment, so accepting there would send a learner
                    // who has already paid back to awaiting_payment.
                    ->visible(fn (Application $record): bool => $record->status === ApplicationStatus::APPLIED
                        && $record->programme_id !== null)
                    ->modalHeading('Accept this application')
                    ->modalDescription('This creates a pending enrolment and raises the invoices for the offering you choose. Nothing is charged yet.')
                    ->modalSubmitActionLabel('Accept and raise invoices')
                    ->schema([
                        Select::make('offering_id')
                            ->label('Offering')
                            ->options(fn (Application $record): array => Offering::query()
                                ->where('programme_id', $record->programme_id)
                                ->where('status', OfferingStatus::OPEN)
                                ->get()
                                ->mapWithKeys(fn (Offering $o): array => [$o->id => "{$o->name} — {$o->terms()}"])
                                ->all())
                            // Most programmes have exactly one open offering,
                            // so the common path is a confirmation, not a
                            // choice.
                            ->default(fn (Application $record) => Offering::query()
                                ->where('programme_id', $record->programme_id)
                                ->where('status', OfferingStatus::OPEN)
                                ->count() === 1
                                    ? Offering::query()
                                        ->where('programme_id', $record->programme_id)
                                        ->where('status', OfferingStatus::OPEN)
                                        ->value('id')
                                    : null)
                            ->helperText('Only offerings that are open can be sold. Confirm the price and open it first if the one you want is missing.')
                            ->required(),
                    ])
                    ->action(function (Application $record, array $data): void {
                        try {
                            $enrolment = app(ApplicationAcceptor::class)->accept(
                                application: $record,
                                offering: Offering::findOrFail($data['offering_id']),
                                actor: auth()->user(),
                            );
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Could not accept this application')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $total = $enrolment->invoices()->sum('amount_cents');

                        Notification::make()
                            ->title('Accepted')
                            ->body('R'.number_format($total / 100, 2).' invoiced across '
                                .$enrolment->invoices()->count().' invoice(s). Confirm the first payment to activate access.')
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
