<?php

namespace App\Filament\Fodig\Resources\SiebelBusinessComponentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelAppletsRelationManager extends RelationManager
{
    protected static string $relationship = 'siebelApplets';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Applets';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->searchable()
                    ->maxLength(400)
                    ->label('Applet Name'),

                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->preload()
                    ->label('Class')
                    ->nullable(),

                Forms\Components\Section::make('Applet Properties')
                    ->schema([
                        Forms\Components\Toggle::make('hide_for_all')
                            ->label('Hide For All Users')
                            ->nullable(),

                        Forms\Components\Toggle::make('popup')
                            ->nullable(),

                        Forms\Components\Toggle::make('scripted')
                            ->nullable(),

                        Forms\Components\TextInput::make('type')
                            ->maxLength(100)
                            ->nullable(),

                        Forms\Components\TextInput::make('container_web_page')
                            ->maxLength(400)
                            ->nullable(),

                        Forms\Components\TextInput::make('height')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('width')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('HTML Properties')
                    ->schema([
                        Forms\Components\TextInput::make('html_height')
                            ->maxLength(100)
                            ->nullable(),

                        Forms\Components\TextInput::make('html_width')
                            ->maxLength(100)
                            ->nullable(),

                        Forms\Components\TextInput::make('html_iconic_button_method')
                            ->maxLength(200)
                            ->nullable(),

                        Forms\Components\TextInput::make('html_number_of_rows')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Textarea::make('comments')
                    ->maxLength(1000)
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('object_locked')
                    ->label('Locked')
                    ->nullable(),

                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->preload()
                    ->label('Project')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('class.name')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BooleanColumn::make('popup')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BooleanColumn::make('scripted')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BooleanColumn::make('hide_for_all')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('height')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('width')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('project.name')
                    ->sortable()
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
