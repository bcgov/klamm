<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\StyleSheetResource\Pages;
use App\Http\Middleware\CheckRole;
use App\Models\FormBuilding\StyleSheet;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;
use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Tables;

class StyleSheetResource extends Resource
{
    protected static ?string $model = StyleSheet::class;
    protected static ?string $navigationLabel = 'Style Sheets';
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $label = 'Style Sheet Template';

    protected static ?string $navigationGroup = 'Form Building';

    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['formVersion.form', 'formVersions.form'])
            ->where('type', 'template');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextInput::make('filename')
                            ->label('Filename')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextArea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(5),
                        Select::make('formVersions')
                            ->label('Attach to Form Versions')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->relationship('formVersions', 'version_number')
                            ->getOptionLabelFromRecordUsing(function (\App\Models\FormBuilding\FormVersion $ver) {
                                return "[{$ver->form->form_id}] {$ver->form->form_title} - v{$ver->version_number}";
                            }),
                        Section::make('Info')
                            ->collapsible()
                            ->collapsed()
                            ->schema(
                                [
                                    Placeholder::make('')
                                        ->content("Styles are used to add custom styling to forms.
                             Target ids will be programmatically identified in your code if you use the correct naming conventions.
                             Use #target_id for target elements.
                              These will be replaced with the actual IDs of the form elements at runtime in the order they are selected.
                              Example: #target_id becomes [id='123-456-78910']"),
                                ]
                            ),

                        CustomMonacoEditor::make('content')
                            ->label('Script Content')
                            ->language('css')
                            ->theme('vs-dark')
                            ->live()
                            ->reactive()
                            ->height('475px')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColumnGroup::make('Stylesheet', [
                    TextColumn::make('filename')
                        ->label('Filename')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('description')
                        ->label('Description')
                        ->toggleable()
                        ->sortable()
                        ->wrap()
                        ->lineClamp(2)
                        ->limit(400)
                        ->tooltip(function (TextColumn $column): ?string {
                            $state = $column->getState();
                            if (strlen($state) <= $column->getCharacterLimit()) {
                                return null;
                            }
                            return $state;
                        })
                        ->searchable(),
                    TextColumn::make('formVersions_count')
                        ->label('Attached Versions')
                        ->counts('formVersions')
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('forms_list')
                        ->label('Attached Forms')
                        ->state(function ($record) {
                            return $record->formVersions
                                ->pluck('form')
                                ->filter()
                                ->unique('id')
                                ->map(function ($f) {
                                    return "[{$f->form_id}] {$f->form_title}";
                                })
                                ->implode(', ');
                        })
                        ->wrap()
                        ->limit(200)
                        ->toggleable(),
                    TextColumn::make('formVersions_list')
                        ->label('Versions')
                        ->state(function ($record) {
                            return $record->formVersions->map(function ($v) {
                                return "[{$v->form->form_id}] {$v->form->form_title} v{$v->version_number}";
                            })->implode(', ');
                        })
                        ->wrap()
                        ->limit(200)
                        ->toggleable()
                        ->toggledHiddenByDefault(true),
                ]),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn() => Gate::allows('admin')),
            ])
            ->actions([
                ViewAction::make('view')
                    ->label('View StyleSheet')
                    ->url(fn($record) => route('filament.forms.resources.style-sheets.view', [
                        'record' => $record->id,
                    ])),
                EditAction::make('edit')
                    ->label('Edit StyleSheet')
                    ->url(fn($record) => route('filament.forms.resources.style-sheets.edit', [
                        'record' => $record->id,
                    ])),
                DeleteAction::make(),
                RestoreAction::make()
                    ->visible(fn($record) => Gate::allows('admin') && $record->trashed()),
            ])
            ->bulkActions([
                //
            ])
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
            'index' => Pages\ListStyleSheets::route('/'),
            'create' => Pages\CreateStyleSheets::route('/create'),
            'edit' => Pages\EditStyleSheets::route('/{record}/edit'),
            'view' => Pages\ViewStyleSheets::route('/{record}'),
        ];
    }
}
