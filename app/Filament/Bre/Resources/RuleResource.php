<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\RuleResource\Pages;
use App\Filament\Bre\Resources\RuleResource\RelationManagers;
use App\Models\BRERule;
use App\Models\Rule;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Infolist;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuleResource extends Resource
{
    use InteractsWithForms;
    protected static ?string $model = BRERule::class;
    protected static ?string $navigationLabel = 'BRE Rules';
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    // protected static ?string $navigationGroup = 'Rules';

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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('label'),
                TextEntry::make('description'),
                TextEntry::make('internal_description'),
                TextEntry::make('breInputs.name')
                    ->badge('success'),
                TextEntry::make('breOutputs.name')
                    ->badge('success'),
                TextEntry::make('parentRules.name')
                    ->badge('success'),
                TextEntry::make('childRules.name')
                    ->badge('success'),
                TextEntry::make('related_icm_cdw_fields.name')
                    ->badge('success')
                    ->default(function ($record) {
                        if ($record instanceof BRERule) {
                            return $record->getRelatedIcmCDWFields();
                        }
                        return [];
                    })
                    ->label('ICM CDW Fields used by the inputs and outputs of this Rule')
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
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Outputs')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
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
                Tables\Columns\TextColumn::make('related_icm_cdw_fields')
                    ->label('ICM CDW Fields used')
                    ->default(function ($record) {
                        if ($record instanceof BRERule) {
                            return $record->getRelatedIcmCDWFields();
                        }
                        return [];
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
            ->defaultSort('name')
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
            'index' => Pages\ListRules::route('/'),
            'create' => Pages\CreateRule::route('/create'),
            'view' => Pages\ViewRule::route('/{record}'),
            'edit' => Pages\EditRule::route('/{record}/edit'),
        ];
    }
}
