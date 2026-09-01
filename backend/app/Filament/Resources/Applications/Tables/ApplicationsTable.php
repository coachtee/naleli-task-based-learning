<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\OfferingStatus;
use App\Models\Application;
use App\Models\Offering;
use App\Services\Enrolment\ApplicationAcceptor;
use App\Services\Messaging\PaymentMessage;
use App\Services\Payments\Providers\PayAtGoProvider;
use App\Services\Registration\ProfileCompleteness;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

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

                // Registering a learner used to be three actions on two
                // screens: accept against an "offering", then mint a Pay@
                // reference on the right invoice, then send it. Nothing told a
                // registrar the last two were needed, so learners sat at
                // awaiting_payment with no reference anyone had issued. It is
                // one button now, and the offering is not a question: every
                // programme has exactly one open one.
                Action::make('registerAndSend')
                    ->label('Register & send payment link')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (Application $record): bool => $record->status->isDecidable()
                        && $record->programme_id !== null
                        && self::soleOpenOffering($record) !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Register this learner and raise the fees')
                    ->modalDescription(fn (Application $record): string => 'Creates the enrolment, raises '
                        .self::soleOpenOffering($record)?->terms().', and issues the Pay@ reference for the '
                        .'registration fee. Nothing is charged until the learner pays at a till.')
                    ->modalSubmitActionLabel('Register and issue the reference')
                    ->action(function (Application $record): void {
                        $offering = self::soleOpenOffering($record);

                        try {
                            $enrolment = app(ApplicationAcceptor::class)
                                ->accept($record, $offering, auth()->user());
                        } catch (DomainException $e) {
                            Notification::make()->title('Could not register this learner')
                                ->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }

                        $invoice = $enrolment->activatingInvoice();
                        $reference = null;

                        try {
                            app(PayAtGoProvider::class)->createCheckout($invoice);
                            $reference = $invoice->fresh()->payat_source_reference;
                        } catch (Throwable $e) {
                            // The enrolment and the invoices are real either
                            // way; only the reference is missing, and Invoices
                            // can issue one on its own.
                            report($e);
                        }

                        $link = app(PaymentMessage::class)->whatsAppLinkFor($invoice->fresh());

                        $body = $reference !== null
                            ? "Registered. Pay@ reference {$reference} for R".number_format($invoice->amount_cents / 100, 2).'.'
                            : 'Registered and invoiced, but Pay@ did not issue a reference. '
                              .'Use "Create Pay@ reference" on the invoice to try again.';

                        Notification::make()
                            ->title($record->learner->learner_ref.' is registered')
                            ->body($body.($link === null ? ' No usable WhatsApp number on file.' : ''))
                            ->success()
                            ->persistent()
                            ->actions(array_values(array_filter([
                                $link === null ? null : NotificationAction::make('whatsapp')
                                    ->label('Send it on WhatsApp')
                                    ->url($link, shouldOpenInNewTab: true)
                                    ->button(),
                            ])))
                            ->send();
                    }),

                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    // Anything below awaiting_payment: a lead, a contact,
                    // or a started registration. Above that the money is
                    // already committed and accepting again would undo it.
                    // Only when the offering is a real choice. With one open
                    // offering the single button above does the whole job.
                    ->visible(fn (Application $record): bool => $record->status->isDecidable()
                        && $record->programme_id !== null
                        && self::soleOpenOffering($record) === null)
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

    /**
     * The one offering a programme is actually sold under, or null when it is
     * a genuine choice.
     *
     * Every 2027 programme has exactly one open offering, so asking a
     * registrar to pick "the offering" was presenting internal vocabulary as a
     * decision. Where more than one is open the question is real, and the
     * Accept action asks it properly.
     */
    private static function soleOpenOffering(Application $application): ?Offering
    {
        if ($application->programme_id === null) {
            return null;
        }

        $open = Offering::query()
            ->where('programme_id', $application->programme_id)
            ->where('status', OfferingStatus::OPEN)
            ->get();

        return $open->count() === 1 ? $open->first() : null;
    }
}
