<?php

namespace App\Filament\Resources\Enrolments\Tables;

use App\Enums\EnrolmentStatus;
use App\Models\Enrolment;
use App\Services\Tokens\AccessTokenIssuer;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrolmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.id')
                    ->searchable(),
                TextColumn::make('programme.name')
                    ->searchable(),
                TextColumn::make('intake.id')
                    ->searchable(),
                TextColumn::make('application.id')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
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
                // Normally issued automatically when the activating invoice
                // settles. This covers the case the automatic path parks:
                // paid first, identity produced afterwards.
                Action::make('issueToken')
                    ->label('Issue token')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn (Enrolment $record): bool => $record->status === EnrolmentStatus::ACTIVE
                        && $record->accessTokens()->count() === 0)
                    ->requiresConfirmation()
                    ->modalDescription('The token is shown once. Copy it before closing this message.')
                    ->action(function (Enrolment $record): void {
                        try {
                            $issued = app(AccessTokenIssuer::class)->issue($record, auth()->user());
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Cannot issue yet')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Access token issued')
                            ->body("{$issued['plain']} — shown once, copy it now.")
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
