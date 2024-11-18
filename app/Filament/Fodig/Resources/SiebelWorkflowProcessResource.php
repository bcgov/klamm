<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelWorkflowProcessResource\Pages;
use App\Filament\Fodig\Resources\SiebelWorkflowProcessResource\RelationManagers;
use App\Models\SiebelWorkflowProcess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelWorkflowProcessResource extends Resource
{
    protected static ?string $model = SiebelWorkflowProcess::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('auto_persist')
                    ->required(),
                Forms\Components\TextInput::make('process_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('simulate_workflow_process')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('status')
                    ->maxLength(40),
                Forms\Components\TextInput::make('workflow_mode')
                    ->maxLength(40),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('group')
                    ->maxLength(40),
                Forms\Components\TextInput::make('version')
                    ->numeric(),
                Forms\Components\Textarea::make('description')
                    ->maxLength(500),
                Forms\Components\TextInput::make('error_process_name')
                    ->maxLength(400),
                Forms\Components\TextInput::make('state_management_type')
                    ->maxLength(40),
                Forms\Components\Toggle::make('web_service_enabled'),
                Forms\Components\Toggle::make('pass_by_ref_hierarchy_argument'),
                Forms\Components\TextInput::make('repository_name')
                    ->maxLength(100),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('business_object_id')
                    ->relationship('businessObject', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BooleanColumn::make('auto_persist')
                    ->sortable(),
                Tables\Columns\TextColumn::make('process_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('simulate_workflow_process')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workflow_mode')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_process_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('state_management_type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('web_service_enabled')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('pass_by_ref_hierarchy_argument')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_language_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessObject.name')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListSiebelWorkflowProcesses::route('/'),
            'create' => Pages\CreateSiebelWorkflowProcess::route('/create'),
            'view' => Pages\ViewSiebelWorkflowProcess::route('/{record}'),
            'edit' => Pages\EditSiebelWorkflowProcess::route('/{record}/edit'),
        ];
    }
}
