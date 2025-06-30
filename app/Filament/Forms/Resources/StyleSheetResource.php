<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\StyleSheetResource\Pages;
use App\Http\Middleware\CheckRole;
use App\Models\FormVersion;
use App\Models\StyleSheet;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StyleSheetResource extends Resource
{
    protected static ?string $model = StyleSheet::class;
    protected static ?string $navigationLabel = 'Style Sheets';
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('formVersion.form');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn($record) => route('filament.forms.resources.form-versions.edit', [
                'record' => $record->formVersion->id,
            ]))
            ->columns([
                ColumnGroup::make('Stylesheet', [
                    IconColumn::make('type')
                        ->label('Type')
                        ->icon(fn($state) => match ($state) {
                            'web' => 'heroicon-o-globe-alt',
                            'pdf' => 'heroicon-o-document-text',
                            default => 'heroicon-o-question-mark-circle',
                        })
                        ->color(fn($state) => $state === 'web' ? 'primary' : 'danger')
                        ->tooltip(fn($record) => StyleSheet::formatType($record->pivot?->type))
                        ->sortable(),
                ]),
                ColumnGroup::make('Form', [
                    TextColumn::make('formVersion.form.form_id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.form.form_title')
                        ->label('Title')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.form.ministry.short_name')
                        ->label('Ministry')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                ]),
                ColumnGroup::make('Form Version', [
                    TextColumn::make('formVersion.version_number')
                        ->label('Version')
                        ->toggleable()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('formVersion.status')
                        ->label('Status')
                        ->badge()
                        ->color(fn($state) => FormVersion::getStatusColour($state))
                        ->getStateUsing(fn($record) => $record->formVersion->getFormattedStatusName())
                        ->toggleable()
                        ->sortable(),
                ])

            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make('view_form_version')
                    ->label('View Form Version')
                    ->url(fn($record) => route(
                        'filament.forms.resources.form-versions.view',
                        ['record' => $record->formVersion->id]
                    ))
                    ->openUrlInNewTab(),
                EditAction::make('edit_form_version')
                    ->label('Edit Form Version')
                    ->visible(fn($record) => (in_array($record->formVersion->status, ['draft', 'testing'])))
                    ->url(fn($record) => route(
                        'filament.forms.resources.form-versions.edit',
                        ['record' => $record->formVersion->id]
                    ))
                    ->openUrlInNewTab()
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
        ];
    }
}
