<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * What to do next, on the first screen.
 *
 * A dashboard that opens on charts makes a registrar hunt for their work. This
 * opens on the actual queue — applications that need a human — oldest first,
 * because the longest wait is the one costing an enrolment.
 */
class WorkQueue extends TableWidget
{
    protected static ?string $heading = 'Needs a decision';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Application::query()
                    ->with(['learner', 'programme', 'intake'])
                    ->whereIn('status', [
                        ApplicationStatus::LEAD,
                        ApplicationStatus::CONTACTED,
                        ApplicationStatus::REGISTRATION_STARTED,
                        ApplicationStatus::PROFILE_INCOMPLETE,
                    ])
                    ->orderBy('applied_at'),
            )
            ->columns([
                TextColumn::make('learner.learner_ref')
                    ->label('Learner')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Application $record): string => trim(
                        "{$record->learner->first_name} {$record->learner->last_name}",
                    ) ?: '—'),

                TextColumn::make('programme.name')
                    ->label('Programme')
                    ->placeholder('Not assigned')
                    ->description(fn (Application $record): ?string => $record->intake?->label),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => match ($state) {
                        ApplicationStatus::PROFILE_INCOMPLETE => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('applied_at')
                    ->label('Waiting')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->url(fn (Application $record): string => route('filament.admin.resources.applications.edit', $record))
                    ->icon('heroicon-m-arrow-right')
                    ->iconButton(),
            ])
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('Every application has been actioned.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
