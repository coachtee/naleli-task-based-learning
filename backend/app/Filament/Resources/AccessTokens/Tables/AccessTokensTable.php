<?php

namespace App\Filament\Resources\AccessTokens\Tables;

use App\Enums\TokenStatus;
use App\Models\AccessToken;
use App\Services\Tokens\AccessTokenIssuer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccessTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.id')
                    ->searchable(),
                TextColumn::make('enrolment.id')
                    ->searchable(),
                TextColumn::make('token_hash')
                    ->searchable(),
                TextColumn::make('token_prefix')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('issued_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('revoked_reason')
                    ->searchable(),
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
                // A lost phone loses access without the learner losing theirs:
                // revoke here, then issue a replacement from the enrolment.
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (AccessToken $record): bool => $record->status !== TokenStatus::REVOKED)
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->placeholder('Lost phone, issued in error, learner withdrew')
                            ->required()
                            ->maxLength(160),
                    ])
                    ->action(function (AccessToken $record, array $data): void {
                        app(AccessTokenIssuer::class)->revoke($record, $data['reason']);

                        Notification::make()
                            ->title('Token revoked')
                            ->body('The device using it loses access on its next sync.')
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
