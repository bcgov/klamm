<?php

namespace App\Filament\Fodig\Resources\SiebelFieldResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Fodig\Resources\SiebelFieldResource;

class ReferencedByRelationManager extends RelationManager
{
    protected static string $relationship = 'referencedBy';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Referenced By Fields';

    protected static ?string $description = 'These are Siebel Fields that reference this field in their calculated value';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Select::make('business_component_id')
                    ->relationship('businessComponent', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('table_id')
                    ->relationship('table', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('table_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link_field')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('calculated_value')
                    ->maxLength(400)
                    ->columnSpanFull()
                    ->nullable(),
                Forms\Components\Toggle::make('is_referenced')
                    ->label('Is Referenced By Other Fields')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('has_field_references')
                    ->label('Has Field References')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('has_list_of_values')
                    ->label('Has List of Values')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Calculated Value: Variable used by These Fields')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessComponent.name')
                    ->label('Business Component')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table_column')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('multi_value_link')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('multi_value_link_field')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('join')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('join_column')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('calculated_value')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_referenced')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\IconColumn::make('has_field_references')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\IconColumn::make('has_list_of_values')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\AttachAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Preview'),
                Tables\Actions\Action::make('view_field')
                    ->label('View Field')
                    ->url(fn($record) => SiebelFieldResource::getUrl('view', ['record' => $record->id]))
                    ->icon('heroicon-o-eye')
                    ->openUrlInNewTab(),
                // Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
