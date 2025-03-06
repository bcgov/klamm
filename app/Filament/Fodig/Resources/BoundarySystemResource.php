<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemResource\RelationManagers;
use App\Models\BoundarySystem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemResource extends Resource
{
    protected static ?string $model = BoundarySystem::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('interface_name')
                    ->required(),
                Forms\Components\Textarea::make('interface_description')
                    ->required(),
                Forms\Components\Select::make('boundary_system_source_system_id')
                    ->required()
                    ->relationship('sourceSystem', 'name')
                    ->label('Source System'),
                Forms\Components\Select::make('boundary_system_target_system_id')
                    ->required()
                    ->relationship('targetSystem', 'name')
                    ->label('Target System'),
                Forms\Components\Select::make('boundary_system_mode_of_transfer_id')
                    ->relationship('boundarySystemModeOfTransfer', 'name')
                    ->label('Mode of Transfer'),
                Forms\Components\Select::make('boundary_system_file_format_id')
                    ->relationship('boundarySystemFileFormat', 'name')
                    ->label('File Format'),
                Forms\Components\Select::make('boundary_system_frequency_id')
                    ->relationship('boundarySystemFrequency', 'name')
                    ->label('Frequency'),
                Forms\Components\Textarea::make('date_time')
                    ->label('Date and Time of Transfer'),
                Forms\Components\TextInput::make('source_point_of_contact')
                    ->label('Source Point of Contact'),
                Forms\Components\TextInput::make('target_point_of_contact')
                    ->label('Target Point of Contact'),
                Forms\Components\Select::make('boundary_system_process')
                    ->multiple()
                    ->preload()
                    ->relationship('boundarySystemProcess', 'name')
                    ->label('Process'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('interface_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('interface_description')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sourceSystem.name')->searchable()->sortable()
                    ->label('Source System'),
                Tables\Columns\TextColumn::make('targetSystem.name')->searchable()->sortable()
                    ->label('Target System'),
                Tables\Columns\TextColumn::make('boundarySystemModeOfTransfer.name')->searchable()->sortable()
                    ->label('Mode of Transfer'),
                Tables\Columns\TextColumn::make('boundarySystemFileFormat.name')->searchable()->sortable()
                    ->label('File Format'),
                Tables\Columns\TextColumn::make('boundarySystemFrequency.name')->searchable()->sortable()
                    ->label('Frequency'),
                Tables\Columns\TextColumn::make('date_time')->searchable()->sortable()
                    ->label('Date and Time of Transfer'),
                Tables\Columns\TextColumn::make('source_point_of_contact')->searchable()->sortable()
                    ->label('Source Point of Contact'),
                Tables\Columns\TextColumn::make('target_point_of_contact')->searchable()->sortable()
                    ->label('Target Point of Contact'),
                Tables\Columns\TagsColumn::make('boundarySystemProcess.name')->searchable()->sortable()
                    ->label('Process'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListBoundarySystems::route('/'),
            'create' => Pages\CreateBoundarySystem::route('/create'),
            'view' => Pages\ViewBoundarySystem::route('/{record}'),
            'edit' => Pages\EditBoundarySystem::route('/{record}/edit'),
        ];
    }
}
