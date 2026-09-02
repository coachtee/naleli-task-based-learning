<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\TouchChannel;
use App\Enums\TouchOutcome;
use App\Models\Application;
use App\Services\Leads\MetaLeadImporter;
use App\Services\Leads\TouchLog;
use App\Services\Messaging\PaymentMessage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Who to phone today.
 *
 * The first thing on the screen, because it is the first thing that should
 * happen. An ad that costs R191 and produces eighty-five names is only worth
 * anything if somebody rings them, and the reason schools lose those names is
 * never the ad — it is that nobody remembered who was still owed a call.
 *
 * Ordered by how long each has been waiting, so the queue answers the question
 * a person actually has when they sit down: who is next.
 */
class CallQueue extends TableWidget
{
    protected static ?string $heading = 'Call these people';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Application::query()
                    ->with(['learner', 'programme', 'owner'])
                    ->whereIn('status', [ApplicationStatus::LEAD, ApplicationStatus::CONTACTED])
                    // A lead with no next action has been closed. Leaving it
                    // here means it is skipped every morning for ever, which
                    // is how a queue stops being read at all.
                    ->whereNotNull('next_action_at')
                    ->orderBy('next_action_at'),
            )
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Nobody is waiting')
            ->emptyStateDescription('Every lead has been called, or has a date to be called on. Import a new Facebook export to add more.')
            ->columns([
                // One column carrying three facts, not three columns. A
                // Filament row on a phone screen used to run to 1277px in a
                // 390px viewport — reaching the person's own actions meant
                // dragging the table sideways for every one of eighty-nine
                // leads. Folding "who they are", "how they were last tried"
                // and "how urgent" into one flexible column, rather than each
                // claiming a fixed-width column of its own, is what actually
                // fixes that: it wraps instead of forcing a scrollbar.
                TextColumn::make('learner.first_name')
                    ->label('Who')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium')
                    ->wrap()
                    ->formatStateUsing(fn (Application $record): string => trim(
                        "{$record->learner->first_name} {$record->learner->last_name}",
                    ) ?: $record->learner->learner_ref)
                    ->description(function (Application $record): HtmlString {
                        $contact = e($record->learner->phone ?? $record->learner->email ?? 'no contact details');
                        $tried = $record->touch_count === 0
                            ? 'never called'
                            : $record->touch_count.'× tried — '
                                .e($record->leadTouches()->first()?->outcome->label() ?? '');

                        return new HtmlString(
                            $contact.'<br><span style="opacity:.75">'.$tried.'</span>',
                        );
                    }),

                TextColumn::make('next_action_at')
                    ->label('Waiting')
                    ->sortable()
                    // "3 days overdue" is a nudge; "2026-08-30 07:41" is a
                    // date somebody has to do arithmetic on.
                    ->formatStateUsing(fn (Carbon $state): string => $state->isFuture()
                        ? 'in '.$state->diffForHumans(syntax: true)
                        : $state->diffForHumans())
                    ->color(fn (Carbon $state): string => match (true) {
                        $state->lt(now()->subDays(3)) => 'danger',
                        $state->isPast() => 'warning',
                        default => 'gray',
                    })
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('campaign')
                    ->label('Came from')
                    ->placeholder('—')
                    ->limit(28)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Application $record): string => $record->source->label()),

                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Nobody yet')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // Because nobody is going to SSH into a server to run an
                // import. Download the CSV from the Leads Center on a phone,
                // upload it here, start calling.
                Action::make('importLeads')
                    ->label('Import Facebook leads')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalHeading('Import a Leads Center export')
                    ->modalDescription('Download your leads as CSV from Meta, then drop the file here. Importing the same file twice is safe — nobody gets added twice.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('The CSV from Meta')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->storeFiles(false)
                            ->required(),

                        TextInput::make('campaign')
                            ->label('Call this campaign')
                            ->placeholder('Left blank, the ad name from the file is used')
                            ->maxLength(120),

                        Select::make('programme_id')
                            ->label('File them under')
                            ->options(fn (): array => app(MetaLeadImporter::class)->programmeOptions())
                            ->placeholder('The Foundation, where everyone starts'),
                    ])
                    ->modalSubmitActionLabel('Import them')
                    ->action(function (array $data): void {
                        try {
                            $result = app(MetaLeadImporter::class)->importFile(
                                path: $data['file']->getRealPath(),
                                campaign: $data['campaign'] ?: null,
                                programmeId: $data['programme_id'] ? (int) $data['programme_id'] : null,
                            );
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('That file could not be read')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $lines = ["{$result['imported']} new leads, all due to be called now."];

                        if ($result['duplicates'] > 0) {
                            $lines[] = "{$result['duplicates']} were already on the ladder and were left alone.";
                        }

                        if ($result['skipped'] !== []) {
                            $lines[] = count($result['skipped']).' rows had no way to reach anybody: '
                                .implode(' ', array_slice($result['skipped'], 0, 3));
                        }

                        Notification::make()
                            ->title($result['imported'] > 0 ? 'Imported' : 'Nothing new to import')
                            ->body(implode(' ', $lines))
                            ->color($result['imported'] > 0 ? 'success' : 'warning')
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                // One tap, message already written, from the phone in their
                // hand. Never a bulk send — a business number that broadcasts
                // gets banned, and the school runs on that number.
                //
                // Icon-only rather than a labelled button: on a phone this row
                // used to be wider than the screen (1277px into a 390px
                // viewport in the layout that shipped first), so reaching
                // WhatsApp meant dragging the table sideways for every one of
                // eighty-nine leads. WhatsApp is the action used most, so it
                // stays a single tap; the other two move into the menu next
                // to it rather than each claiming their own width.
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconButton()
                    ->tooltip('WhatsApp')
                    ->color('success')
                    ->url(fn (Application $record): ?string => app(PaymentMessage::class)
                        ->leadIntroWhatsAppLink($record->learner, auth()->user()?->name))
                    ->openUrlInNewTab()
                    ->visible(fn (Application $record): bool => app(PaymentMessage::class)
                        ->leadIntroWhatsAppLink($record->learner) !== null)
                    ->after(fn (Application $record) => $this->log(
                        $record, TouchChannel::WHATSAPP, TouchOutcome::SENT_INFO, 'Opened WhatsApp from the queue.',
                    )),

                ActionGroup::make([
                    $this->logCallAction(),
                    $this->registerAction(),
                ])
                    ->label('More')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip('More actions')
                    ->color('gray'),
            ]);
    }

    private function logCallAction(): Action
    {
        return Action::make('logCall')
            ->label('Log a call')
            ->icon('heroicon-o-phone')
            ->modalHeading(fn (Application $record): string => 'How did it go with '
                .trim("{$record->learner->first_name} {$record->learner->last_name}").'?')
            ->modalDescription(fn (Application $record): ?string => $record->leadTouches()->first()?->note)
            ->schema([
                Select::make('channel')
                    ->label('How did you reach out?')
                    ->options(collect(TouchChannel::cases())
                        ->mapWithKeys(fn (TouchChannel $c): array => [$c->value => $c->label()])->all())
                    ->default(TouchChannel::PHONE->value)
                    ->required(),

                Select::make('outcome')
                    ->label('What happened?')
                    ->options(collect(TouchOutcome::cases())
                        ->mapWithKeys(fn (TouchOutcome $o): array => [$o->value => $o->label()])->all())
                    ->required()
                    ->live()
                    ->helperText('This sets when they come back into the queue.'),

                Textarea::make('note')
                    ->label('What did they say?')
                    ->placeholder('The next person to call them will read this.')
                    ->rows(3),

                DatePicker::make('next_action_at')
                    ->label('Call again on')
                    ->helperText('Leave blank to use the usual interval for that outcome.')
                    ->minDate(now()),
            ])
            ->modalSubmitActionLabel('Save it')
            ->action(function (Application $record, array $data): void {
                $this->log(
                    $record,
                    TouchChannel::from($data['channel']),
                    TouchOutcome::from($data['outcome']),
                    $data['note'] ?? null,
                    isset($data['next_action_at']) && $data['next_action_at']
                        ? Carbon::parse($data['next_action_at'])
                        : null,
                );
            });
    }

    private function registerAction(): Action
    {
        return Action::make('register')
            ->label('Register them')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->url(fn (Application $record): string => route('filament.admin.resources.applications.index',
                ['tableSearch' => $record->learner->learner_ref]))
            ->visible(fn (Application $record): bool => $record->leadTouches()
                ->where('outcome', TouchOutcome::WILL_REGISTER)->exists());
    }

    private function log(
        Application $record,
        TouchChannel $channel,
        TouchOutcome $outcome,
        ?string $note = null,
        ?Carbon $nextActionAt = null,
    ): void {
        app(TouchLog::class)->record(
            application: $record,
            channel: $channel,
            outcome: $outcome,
            note: $note,
            by: auth()->user(),
            nextActionAt: $nextActionAt,
        );

        Notification::make()
            ->title('Logged — '.$outcome->label())
            ->body($outcome->nextActionInDays() === null
                ? 'They have left the queue.'
                : 'Back in the queue in '.$outcome->nextActionInDays().' days.')
            ->success()
            ->send();
    }
}
