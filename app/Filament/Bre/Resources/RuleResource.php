<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\RuleResource\Pages;
use App\Filament\Bre\Resources\RuleResource\RelationManagers;
use App\Models\BRERule;
use App\Models\Rule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuleResource extends Resource
{
    protected static ?string $model = BRERule::class;
    protected static ?string $navigationLabel = 'BRE Rules';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Rules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('label'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('internal_description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('input_fields')
                    ->multiple()
                    ->relationship('breInputs', 'name'),
                Forms\Components\Select::make('output_fields')
                    ->multiple()
                    ->relationship('breOutputs', 'name'),
                Forms\Components\Select::make('parent_rule_id')
                    ->multiple()
                    ->relationship('parentRules', 'name'),
                Forms\Components\Select::make('child_rules')
                    ->multiple()
                    ->relationship('childRules', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('breInputs.name')
                    ->label('Inputs')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Outputs')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parentRules.name')
                    ->label('Parent Rules')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('childRules')
                    ->label('Child Rules')
                    ->formatStateUsing(function ($record) {
                        return $record->childRules->pluck('name')->join(', ');
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListRules::route('/'),
            'create' => Pages\CreateRule::route('/create'),
            'view' => Pages\ViewRule::route('/{record}'),
            'edit' => Pages\EditRule::route('/{record}/edit'),
        ];
    }
}
