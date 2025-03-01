<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessComponentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'siebelFields';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400)
                    ->label('Field Name'),

                Forms\Components\Select::make('table_id')
                    ->relationship('table', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Table')
                    ->nullable(),

                Forms\Components\TextInput::make('table_column')
                    ->maxLength(400)
                    ->label('Table Column')
                    ->nullable(),

                Forms\Components\Section::make('Multi-Value Link Config')
                    ->schema([
                        Forms\Components\TextInput::make('multi_value_link')
                            ->maxLength(400)
                            ->nullable(),

                        Forms\Components\TextInput::make('multi_value_link_field')
                            ->maxLength(400)
                            ->nullable(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Join Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('join')
                            ->maxLength(400)
                            ->nullable(),

                        Forms\Components\TextInput::make('join_column')
                            ->maxLength(400)
                            ->nullable(),
                    ])
                    ->collapsible(),

                Forms\Components\Textarea::make('calculated_value')
                    ->maxLength(1000)
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('table.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('table_column')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('multi_value_link')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('multi_value_link_field')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('join')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('join_column')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('calculated_value')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->calculated_value;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['business_component_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
