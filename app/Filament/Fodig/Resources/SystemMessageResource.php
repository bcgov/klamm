<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SystemMessageResource\Pages;
use Filament\Forms\Components\Select;
use App\Models\SystemMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemMessageResource extends Resource
{
    protected static ?string $model = SystemMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Error Lookup Tool';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('error_code')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('error_message')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('icm_error_solution')
                    ->label('ICM error solution')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('explanation')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('fix')
                    ->columnSpanFull(),
                Select::make('error_entity_id')
                    ->relationship('errorEntity', 'name'),
                Select::make('error_data_group_id')
                    ->relationship('errorDataGroup', 'name'),
                Select::make('error_integration_state_id')
                    ->relationship('errorIntegrationState', 'name'),
                Select::make('error_actor_id')
                    ->relationship('errorActor', 'name'),
                Select::make('error_source_id')
                    ->relationship('errorSource', 'name'),
                Forms\Components\Toggle::make('service_desk'),
                Forms\Components\Toggle::make('limited_data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('error_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorEntity.name')
                    ->label('Error Entity')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorDataGroup.name')
                    ->label('Error Data Group')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorIntegrationState.name')
                    ->label('Error Integration State')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorActor.name')
                    ->label('Error Actor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorSource.name')
                    ->label('Error Source')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('service_desk')
                    ->boolean(),
                Tables\Columns\IconColumn::make('limited_data')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('error_entity_id')
                    ->label('Error Entity')
                    ->relationship('errorEntity', 'name'),
                Tables\Filters\SelectFilter::make('error_data_group_id')
                    ->label('Error Data Group')
                    ->relationship('errorDataGroup', 'name'),
                Tables\Filters\SelectFilter::make('error_integration_state_id')
                    ->label('Error Integration State')
                    ->relationship('errorIntegrationState', 'name'),
                Tables\Filters\SelectFilter::make('error_actor_id')
                    ->label('Error Actor')
                    ->relationship('errorActor', 'name'),
                Tables\Filters\SelectFilter::make('error_source_id')
                    ->label('Error Source')
                    ->relationship('errorSource', 'name'),
                Tables\Filters\TernaryFilter::make('service_desk')
                    ->label('Service Desk'),
                Tables\Filters\TernaryFilter::make('limited_data')
                    ->label('Limited Data'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportAll')
                    ->label('Export All')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $records = SystemMessage::with([
                            'errorEntity',
                            'errorDataGroup',
                            'errorIntegrationState',
                            'errorActor',
                            'errorSource'
                        ])->get();

                        $data = [
                            'last-updated' => now()->format('F jS, Y'),
                            'popular-pages' => [],
                            'errors' => $records->map(function ($record) {
                                return [
                                    'Entity' => $record->errorEntity?->name ?? '',
                                    'Datagroup' => $record->errorDataGroup?->name ?? '',
                                    'ErrorCode' => $record->error_code,
                                    'ErrorMessage' => $record->error_message,
                                    'SourceSystem' => $record->errorSource?->name ?? '',
                                    'ActionBy' => $record->errorActor?->name ?? '',
                                    'ICMErrorSolution' => $record->icm_error_solution ?? '',
                                    'Fix' => $record->fix ?? '',
                                    'Explanation' => $record->explanation ?? '',
                                    'ServiceDesk' => (bool) $record->service_desk,
                                    'LimitedData' => (bool) $record->limited_data,
                                ];
                            }),
                        ];

                        return response()->streamDownload(function () use ($data) {
                            echo json_encode($data, JSON_PRETTY_PRINT);
                        }, 'errors.json', [
                            'Content-Type' => 'application/json',
                        ]);
                    })
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemMessages::route('/'),
            'create' => Pages\CreateSystemMessage::route('/create'),
            'view' => Pages\ViewSystemMessage::route('/{record}'),
            'edit' => Pages\EditSystemMessage::route('/{record}/edit'),
        ];
    }
}
