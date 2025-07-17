<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormScriptResource\Pages;
use App\Http\Middleware\CheckRole;
use App\Models\FormBuilding\FormScript;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;

class FormScriptResource extends Resource
{
    protected static ?string $model = FormScript::class;
    protected static ?string $navigationLabel = 'Scripts';
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $label = 'Script Template';

    protected static ?string $navigationGroup = 'Form Building';

    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('formVersion.form')
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

                        Section::make('Info')
                            ->collapsible()
                            ->collapsed()
                            ->schema(
                                [
                                    Placeholder::make('')
                                        ->content("Scripts are used to add custom JavaScript functionality to forms.
                             Source ids and target ids will be programmatically identified in your code if you use the correct naming conventions.
                             Use #{source_id} for source elements and #{target_id} for target elements.
                              These will be replaced with the actual IDs of the form elements at runtime in the order they are selected.
                                 Example: `document.getElementById(`#{source_id}` becomes document.getElementById(`123-456-78910`)."),
                                ]
                            ),
                        CustomMonacoEditor::make('content')
                            ->label('Script Content')
                            ->language('javascript')
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
                ColumnGroup::make('Script', [
                    TextColumn::make('filename')
                        ->label('Script Name')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('description')
                        ->label('Description')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make('view')
                    ->label('View Script')
                    ->url(fn($record) => route('filament.forms.resources.form-scripts.view', ['record' => $record->id])),
                EditAction::make('edit')
                    ->label('Edit Script')
                    ->url(fn($record) => route('filament.forms.resources.form-scripts.edit', ['record' => $record->id])),

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
            'index' => Pages\ListFormScripts::route('/'),
            'create' => Pages\CreateFormScripts::route('/create'),
            'edit' => Pages\EditFormScripts::route('/{record}/edit'),
            'view' => Pages\ViewFormScripts::route('/{record}'),
        ];
    }
}
