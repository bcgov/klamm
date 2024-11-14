<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\RuleResource\Pages;
use App\Filament\Bre\Resources\RuleResource\RelationManagers;
use App\Models\BRERule;
use App\Models\ICMCDWField;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class RuleResource extends Resource
{
    use InteractsWithForms;
    protected static ?string $model = BRERule::class;
    protected static ?string $navigationLabel = 'BRE Rules';
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    private static string $badgeTemplate = '
        <a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
            <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                <span class="grid">
                    <span class="truncate">%s</span>
                </span>
            </span>
        </a>';

    private static function formatBadge(string $url, string $text): string
    {
        return sprintf(static::$badgeTemplate, $url, e($text));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
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
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breInputs->map(function ($input) {
                                return static::formatBadge(
                                    FieldResource::getUrl('view', ['record' => $input->name]),
                                    $input->name
                                );
                            })->join('')
                        );
                    })
                    ->html(),
                TextEntry::make('breOutputs.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breOutputs->map(function ($output) {
                                return static::formatBadge(
                                    FieldResource::getUrl('view', ['record' => $output->name]),
                                    $output->name
                                );
                            })->join('')
                        );
                    })
                    ->html(),
                TextEntry::make('parentRules.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->parentRules->map(function ($parent) {
                                return static::formatBadge(
                                    RuleResource::getUrl('view', ['record' => $parent->name]),
                                    $parent->name
                                );
                            })->join('')
                        );
                    })
                    ->html(),
                TextEntry::make('childRules.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->childRules->map(function ($child) {
                                return static::formatBadge(
                                    RuleResource::getUrl('view', ['record' => $child->name]),
                                    $child->name
                                );
                            })->join('')
                        );
                    })
                    ->html(),
                TextEntry::make('related_icm_cdw_fields')
                    ->state(function (BRERule $record) {
                        return $record->getICMCDWFieldObjects();
                    })
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->getICMCDWFieldObjects()->map(function ($field) {
                                return static::formatBadge(
                                    ICMCDWFieldResource::getUrl('view', ['record' => $field->id]),
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('ICM CDW Fields used by the inputs and outputs of this Rule')
                    ->columnSpanFull()
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
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breInputs.name')
                    ->label('Inputs')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Outputs')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('parentRules.name')
                    ->label('Parent Rules')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('childRules')
                    ->label('Child Rules')
                    ->formatStateUsing(function ($record) {
                        return $record->childRules->pluck('name')->join(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('related_icm_cdw_fields')
                    ->label('ICM CDW Fields used')
                    ->default(function ($record) {
                        if ($record instanceof BRERule) {
                            return $record->getRelatedIcmCDWFields();
                        }
                        return [];
                    })
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
            'view' => Pages\ViewRule::route('/{record:name}'),
            'edit' => Pages\EditRule::route('/{record:name}/edit'),
        ];
    }
}
