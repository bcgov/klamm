<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\ICMCDWFieldResource\Pages;
use App\Filament\Bre\Resources\ICMCDWFieldResource\RelationManagers;
use App\Models\ICMCDWField;
use App\Filament\Bre\Resources\FieldResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ICMCDWFieldResource extends Resource
{
    protected static ?string $model = ICMCDWField::class;

    protected static ?string $navigationLabel = 'ICM CDW Fields';
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'ICM Data';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('field'),
                Forms\Components\TextInput::make('panel_type'),
                Forms\Components\TextInput::make('entity'),
                Forms\Components\TextInput::make('path'),
                Forms\Components\TextInput::make('subject_area'),
                Forms\Components\TextInput::make('applet'),
                Forms\Components\TextInput::make('datatype'),
                Forms\Components\TextInput::make('field_input_max_length'),
                Forms\Components\TextInput::make('ministry'),
                Forms\Components\TextInput::make('cdw_ui_caption'),
                Forms\Components\TextInput::make('cdw_table_name'),
                Forms\Components\TextInput::make('cdw_column_name'),
                Forms\Components\Select::make('breFields')
                    ->label('Related BRE Fields:')
                    ->multiple()
                    ->relationship('breFields', 'name'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')
                    ->label('Name'),
                TextEntry::make('field')
                    ->label('Field'),
                TextEntry::make('panel_type')
                    ->label('Panel Type'),
                TextEntry::make('entity'),
                TextEntry::make('path'),
                TextEntry::make('subject_area'),
                TextEntry::make('applet'),
                TextEntry::make('datatype'),
                TextEntry::make('field_input_max_length'),
                TextEntry::make('ministry'),
                TextEntry::make('cdw_ui_caption'),
                TextEntry::make('cdw_table_name'),
                TextEntry::make('cdw_column_name'),
                TextEntry::make('breFields.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breFields->map(function ($field) {
                                return sprintf(
                                    '<a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
                                    <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                    <span class="grid">
                                    <span class="truncate">%s</span>
                                    </span>
                                    </span>
                                    </a>',
                                    FieldResource::getUrl('view', ['record' => $field->name]),
                                    e($field->name)
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related BRE Fields')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('field')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('panel_type')
                    ->label('Panel Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('entity')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subject_area')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('applet')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('datatype')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('field_input_max_length')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ministry')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_ui_caption')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_table_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cdw_column_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breFields.name')
                    ->label('Related BRE Fields')
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
                SelectFilter::make('panel_type')
                    ->label('Panel Type')
                    ->multiple()
                    ->options(ICMCDWField::all()->pluck('panel_type', 'panel_type')->unique())
                    ->attribute(('panel_type')),
                SelectFilter::make('entity')
                    ->label('Entity')
                    ->multiple()
                    ->options(ICMCDWField::all()->pluck('entity', 'entity')->unique())
                    ->attribute(('entity')),
                SelectFilter::make('subject_area')
                    ->label('Subject Area')
                    ->multiple()
                    ->options(ICMCDWField::all()->pluck('subject_area', 'subject_area')->unique())
                    ->attribute(('subject_area')),
                SelectFilter::make('breFields')
                    ->label('Related BRE Fields:')
                    ->relationship('breFields', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->attribute(('breFields.name')),
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
            'index' => Pages\ListICMCDWFields::route('/'),
            'create' => Pages\CreateICMCDWField::route('/create'),
            'view' => Pages\ViewICMCDWField::route('/{record}'),
            'edit' => Pages\EditICMCDWField::route('/{record}/edit'),
        ];
    }
}
