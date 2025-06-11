<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\StyleSheetResource\Pages;
use App\Filament\Forms\Resources\StyleSheetResource\RelationManagers\FormVersionRelationManager;
use App\Http\Middleware\CheckRole;
use App\Models\FormVersion;
use App\Models\StyleSheet;
use Dotswan\FilamentCodeEditor\Fields\CodeEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StyleSheetResource extends Resource
{
    protected static ?string $model = StyleSheet::class;
    protected static ?string $navigationLabel = 'Style Sheets';
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Form Building';

    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin', 'form-developer');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('formVersions.form');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                TextInput::make('name')
                    ->columnSpanFull()
                    ->disabled(fn($record) => $record?->formVersions()->where('status', ['approved', 'published'])->exists())
                    ->required(),
                CodeEditor::make('css_content')
                    ->columnSpanFull()
                    ->required()
                    ->disabled(fn($record) => $record?->formVersions()->where('status', ['approved', 'published'])->exists())
                    ->darkModeTheme('material-dark')
                    ->lightModeTheme('basic-light'),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->disabled(fn($record) => $record?->formVersions()->where('status', ['approved', 'published'])->exists()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('formVersions')
                    ->label('Form Versions')
                    ->badge()
                    ->getStateUsing(
                        fn($record) =>
                        $record->formVersions
                            ->map(fn($version) => [
                                'label' => $version->form->form_id
                                    . ' v' . $version->version_number
                                    . ' (' . $version->getFormattedStatusName() . ')',
                                'status' => $version->getFormattedStatusName(),
                            ])
                            ->toArray()
                    )
                    ->formatStateUsing(
                        fn($state) =>
                        collect($state['label'])->implode(', ')
                    )
                    ->color(fn($state) => FormVersion::getStatusColour($state['status']))
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) {
                        return ! $record?->formVersions()->whereIn('status', ['approved', 'published'])->exists();
                    }),
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
            FormVersionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStyleSheets::route('/'),
            'create' => Pages\CreateStyleSheet::route('/create'),
            'edit' => Pages\EditStyleSheet::route('/{record}/edit'),
        ];
    }
}
