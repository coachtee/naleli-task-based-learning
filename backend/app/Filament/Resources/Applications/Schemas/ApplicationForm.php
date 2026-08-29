<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\FundingSource;
use App\Enums\FundingStatus;
use App\Models\Intake;
use App\Models\Learner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Who and what')
                    ->description('The three things a registration needs before anything else can happen.')
                    ->columns(2)
                    ->schema([
                        Select::make('learner_id')
                            ->label('Learner')
                            ->relationship('learner', 'learner_ref')
                            ->getOptionLabelFromRecordUsing(fn (Learner $record): string => trim(
                                "{$record->learner_ref} — {$record->first_name} {$record->last_name}",
                            ))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('programme_id')
                            ->label('Programme')
                            ->relationship('programme', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('intake_id')
                            ->label('Intake')
                            ->relationship('intake', 'label')
                            ->getOptionLabelFromRecordUsing(fn (Intake $record): string => $record->label)
                            ->searchable()
                            ->preload(),

                        Select::make('status')
                            ->label('Stage')
                            ->options(ApplicationStatus::options())
                            ->default(ApplicationStatus::REGISTRATION_STARTED->value)
                            ->required(),
                    ]),

                Section::make('How it is being paid for')
                    ->description('Asked once, here. Choosing funding never interrupts a registration — it records an intention, and the detail follows later.')
                    ->columns(2)
                    ->schema([
                        Select::make('funding_source')
                            ->label('Paid for by')
                            ->options(FundingSource::options())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $source = $state instanceof FundingSource ? $state : FundingSource::tryFrom((string) $state);

                                $set('funding_status', $source?->needsFundingWork()
                                    ? FundingStatus::PENDING->value
                                    : FundingStatus::NOT_REQUIRED->value);
                            }),

                        Select::make('funding_status')
                            ->label('Funding status')
                            ->options(FundingStatus::options()),

                        Textarea::make('funding_note')
                            ->label('Note')
                            ->placeholder('Sponsor name, reference number, what is still outstanding.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Where it came from')
                    ->description('Provenance. Set automatically for anything arriving from the website or a campaign.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('source')
                            ->label('Came from')
                            ->options(fn (): array => collect(ApplicationSource::cases())
                                ->mapWithKeys(fn (ApplicationSource $c): array => [$c->value => $c->label()])
                                ->all())
                            ->required(),

                        TextInput::make('campaign')
                            ->label('Campaign or referral')
                            ->placeholder('Facebook — February intake'),

                        TextInput::make('source_form_id')
                            ->label('Form')
                            ->numeric(),

                        TextInput::make('source_reference')
                            ->label('Submission reference'),

                        Textarea::make('payload')
                            ->label('Raw submission')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Timeline')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        DateTimePicker::make('applied_at')
                            ->label('Registered interest')
                            ->required(),

                        DateTimePicker::make('first_contacted_at')
                            ->label('First contacted'),

                        DateTimePicker::make('decided_at')
                            ->label('Accepted'),

                        DateTimePicker::make('registered_at')
                            ->label('Fully registered'),

                        TextInput::make('decision_note')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
