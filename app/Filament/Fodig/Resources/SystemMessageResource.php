<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SystemMessageResource\Pages;
use App\Filament\Fodig\Resources\SystemMessageResource\RelationManagers;
use App\Models\SystemMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SystemMessageResource extends Resource
{
    protected static ?string $model = SystemMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'Successor System';

    protected static ?string $label = 'All Messages';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Message Type'),
                Tables\Columns\TextColumn::make('message_copy')->limit(50),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->url(function ($record) {
                        $id = $record->id;

                        if (strpos($id, 'MIS-') === 0) {
                            $originalId = substr($id, 4);
                            return route('filament.fodig.resources.m-i-s-integration-errors.edit', $originalId);
                        } elseif (strpos($id, 'ICMErr-') === 0) {
                            $originalId = substr($id, 7);
                            return route('filament.fodig.resources.i-c-m-error-messages.edit', $originalId);
                        } elseif (strpos($id, 'ICMSys-') === 0) {
                            $originalId = substr($id, 7);
                            return route('filament.fodig.resources.i-c-m-system-messages.edit', $originalId);
                        }

                        return '#';
                    }),
            ])
            ->bulkActions([
                //
            ])->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Message Type')
                    ->options([
                        'MISIntegrationError' => 'MIS Integration Error',
                        'ICMErrorMessage' => 'ICM Error Message',
                        'ICMSystemMessage' => 'ICM System Message',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->where('type', $data['value']);
                        }
                    }),
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemMessages::route('/'),
        ];
    }
}
