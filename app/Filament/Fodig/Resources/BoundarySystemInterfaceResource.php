<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemInterfaceResource\Pages;
use App\Models\BoundarySystemInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;


class BoundarySystemInterfaceResource extends Resource
{
    protected static ?string $model = BoundarySystemInterface::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Data Gateway';

    protected static ?string $navigationLabel = 'Interfaces';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Interface Name'),
                Forms\Components\Textarea::make('short_description')
                    ->label('Short Description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('source_system_id')
                    ->label('Source System')
                    ->relationship('sourceSystem', 'name')
                    ->required(),
                Forms\Components\Select::make('target_system_id')
                    ->label('Target System')
                    ->relationship('targetSystem', 'name')
                    ->different('source_system_id')
                    ->required(),
                Forms\Components\Select::make('transaction_frequency')
                    ->label('Transaction Frequency')
                    ->options(BoundarySystemInterface::getTransactionFrequencyOptions())
                    ->nullable()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('transaction_schedule')
                    ->maxLength(255),
                Forms\Components\Select::make('tags')
                    ->label('Tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required()->label('Tag Name'),
                    ]),
                Forms\Components\Select::make('complexity')
                    ->label('Complexity')
                    ->options(BoundarySystemInterface::getComplexityOptions())
                    ->required()
                    ->default('high')
                    ->columnSpanFull(),
                Forms\Components\Select::make('integration_type')
                    ->label('Integration Type')
                    ->options(BoundarySystemInterface::getIntegrationTypeOptions())
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('mode_of_transfer')
                    ->label('Mode of Transfer')
                    ->options(BoundarySystemInterface::getModeOfTransferOptions())
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('protocol')
                    ->label('Protocol')
                    ->options(BoundarySystemInterface::getProtocolOptions())
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_format')
                    ->label('Data Format')
                    ->options(BoundarySystemInterface::getDataFormatOptions())
                    ->required()
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\Select::make('security')
                    ->label('Security')
                    ->options(BoundarySystemInterface::getSecurityOptions())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sourceSystem.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('targetSystem.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_frequency')
                    ->label('Transaction Frequency')
                    ->formatStateUsing(function (?string $state) {
                        return BoundarySystemInterface::getTransactionFrequencyOptions()[$state] ?? $state;
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_description')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_frequency')
                    ->options(BoundarySystemInterface::getTransactionFrequencyOptions())
                    ->label('Transaction Frequency'),
                Tables\Filters\SelectFilter::make('source_system_id')
                    ->relationship('sourceSystem', 'name')
                    ->label('Source System')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('target_system_id')
                    ->relationship('targetSystem', 'name')
                    ->label('Target System')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tags')
                    ->multiple()
                    ->relationship('tags', 'name')
                    ->preload()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListBoundarySystemInterfaces::route('/'),
            'create' => Pages\CreateBoundarySystemInterface::route('/create'),
            'view' => Pages\ViewBoundarySystemInterface::route('/{record}'),
            'edit' => Pages\EditBoundarySystemInterface::route('/{record}/edit'),
        ];
    }
}
