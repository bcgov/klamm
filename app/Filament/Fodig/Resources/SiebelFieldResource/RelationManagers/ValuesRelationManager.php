<?php

namespace App\Filament\Fodig\Resources\SiebelFieldResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Fodig\Resources\SiebelValueResource;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $recordTitleAttribute = 'value';

    protected static ?string $title = 'List of Values (LOV)';

    protected static ?string $description = 'These are values referenced in this field\'s calculated value';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->maxLength(50),
                Forms\Components\TextInput::make('display_value'),
                Forms\Components\Toggle::make('changed'),
                Forms\Components\Toggle::make('translate'),
                Forms\Components\Toggle::make('multilingual'),
                Forms\Components\TextInput::make('language_independent_code')
                    ->maxLength(50),
                Forms\Components\TextInput::make('parent_lic')
                    ->maxLength(50),
                Forms\Components\TextInput::make('high')
                    ->maxLength(300),
                Forms\Components\TextInput::make('low')
                    ->maxLength(300),
                Forms\Components\TextInput::make('order')
                    ->numeric(),
                Forms\Components\Toggle::make('active'),
                Forms\Components\TextInput::make('language_name')
                    ->maxLength(200),
                Forms\Components\TextInput::make('replication_level')
                    ->maxLength(25),
                Forms\Components\TextInput::make('target_low')
                    ->numeric(),
                Forms\Components\TextInput::make('target_high')
                    ->numeric(),
                Forms\Components\TextInput::make('weighting_factor')
                    ->numeric(),
                Forms\Components\Textarea::make('description')
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Calculated Value: Siebel LOV Values Used')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_value')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language_independent_code')
                    ->searchable()
                    ->sortable(),
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
                Tables\Actions\Action::make('view_value')
                    ->label('View Value')
                    ->url(fn($record) => SiebelValueResource::getUrl('view', ['record' => $record->id]))
                    ->icon('heroicon-o-eye')
                    ->openUrlInNewTab(),
                // Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DetachBulkAction::make(),
                // ]),
            ]);
    }
}
