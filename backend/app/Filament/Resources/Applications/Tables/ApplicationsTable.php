<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\OfferingStatus;
use App\Models\Application;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Registration\ProfileCompleteness;
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
                        'registered' => 'success',
                        'paid' => 'info',
                        'awaiting_payment', 'profile_incomplete' => 'warning',
                        'rejected', 'withdrawn' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('funding_source')
                    ->label('Paid for by')
                    ->badge()
                    ->placeholder('Not asked')
                    ->formatStateUsing(fn ($state): string => $state->shortLabel())
                    ->color(fn ($state): string => $state === FundingSource::SELF ? 'gray' : 'info')
                    ->description(fn (Application $record): ?string => $record->hasOpenFundingMatter()
                        ? 'Funding '.strtolower($record->funding_status?->label() ?? 'pending')
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                // What is still owed, once the money is in. Computed from the
                // learner record, so it cannot claim complete while a field is
                // empty.
                TextColumn::make('profile')
                    ->label('Profile')
                    ->state(fn (Application $record): string => $record->learner === null
                        ? '—'
                        : app(ProfileCompleteness::class)->percent($record->learner).'%')
                    ->badge()
                    ->color(fn (Application $record): string => match (true) {
                        $record->learner === null => 'gray',
                        app(ProfileCompleteness::class)->isComplete($record->learner) => 'success',
                        app(ProfileCompleteness::class)->blocking($record->learner) !== [] => 'danger',
                        default => 'warning',
                    })
                    ->description(function (Application $record): ?string {
                        if ($record->learner === null) {
                            return null;
                        }

                        $missing = app(ProfileCompleteness::class)->missing($record->learner);

                        if ($missing === []) {
                            return 'Complete';
                        }

                        return count($missing) <= 2
                            ? 'Needs '.strtolower(implode(' and ', $missing))
                            : count($missing).' items outstanding';
                    }),

                TextColumn::make('applied_at')
                    ->label('Started')
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
            ->emptyStateHeading('No registrations yet')
            ->emptyStateDescription('Registrations arrive on their own from kcs.edu.za and from campaign lead forms.')
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => ApplicationStatus::options()),

                SelectFilter::make('funding_source')
                    ->label('Paid for by')
                    ->options(fn (): array => FundingSource::options()),
            ])
            ->recordActions([
                // Admissions in one click: this raises the enrolment and the
                // invoices the offering says are owed. The offering is chosen
                // here because the price lives there, not in this decision.
                // The rung between a captured lead and a registration of their
                // own. Without it, "contacted" is a state nothing can reach.
                Action::make('recordContact')
                    ->label('Mark contacted')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->visible(fn (Application $record): bool => $record->status === ApplicationStatus::LEAD)
                    ->requiresConfirmation()
                    ->modalHeading('Record that you have spoken to this person')
                    ->modalDescription('This moves them out of the new-lead queue. It does not start a registration or invoice anything.')
                    ->modalSubmitActionLabel('Mark as contacted')
                    ->action(function (Application $record): void {
                        $record->update([
                            'status' => ApplicationStatus::CONTACTED,
                            'first_contacted_at' => $record->first_contacted_at ?? now(),
                        ]);

                        Notification::make()
                            ->title('Marked as contacted')
                            ->success()
                            ->send();
                    }),

                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    // Anything below awaiting_payment: a lead, a contact,
                    // or a started registration. Above that the money is
                    // already committed and accepting again would undo it.
                    ->visible(fn (Application $record): bool => $record->status->isDecidable()
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
