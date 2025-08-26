<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;
use App\Models\FormBuilding\FormElement;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class ElementTemplateManagementResource extends Resource
{
    protected static ?string $model = FormElement::class;
    protected static ?string $modelLabel = 'Element Template';
    protected static ?string $pluralModelLabel = 'Element Templates';
    protected static ?string $slug = 'element-template-management';
    protected static ?string $navigationLabel = 'Element Template Management';
    protected static ?string $navigationGroup = 'Form Building';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u && $u->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();
        return $u && $u->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_template', true)
            ->with(['tags', 'dataBindings']);
    }

    // check if an element is parented
    public static function isParented(FormElement $r): bool
    {
        $parentId = $r->getAttribute('parent_id');
        if (! is_null($parentId) && (int) $parentId !== -1) {
            return true;
        }
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Field name')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('elementable_type')->label('Element type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null)->sortable(),
                Tables\Columns\TextColumn::make('reference_id')->label('Reference ID')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('uuid')->label('Reference UUID')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TagsColumn::make('tags.name')->label('Tags')->limit(3)->separator(','),
                Tables\Columns\TextColumn::make('description')->label('Description')->lineClamp(2)->toggleable()->searchable(),
                Tables\Columns\TextColumn::make('form_version_id')->label('Form version')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('data_path_preview')->label('Data path')
                    ->state(fn (FormElement $r) => optional($r->dataBindings->first())->path)
                    ->copyable()->toggleable(isToggledHiddenByDefault: true),
                // hidden-by-default fields(some were above for displaying orders)
                Tables\Columns\IconColumn::make('visible_web')->label('Visible (Web)')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('visible_pdf')->label('Visible (PDF)')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_required')->label('Required')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_read_only')->label('Read‑only')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('save_on_submit')->label('Save on Submit')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\IconColumn::make('is_template')->label('Is template')->boolean()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source_element_id')->label('Source element ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('custom_visibility')->label('Custom Visibility')->wrap()->toggleable(isToggledHiddenByDefault: true)->searchable(),
                Tables\Columns\TextColumn::make('custom_read_only')->label('Custom Read Only')->wrap()->toggleable(isToggledHiddenByDefault: true)->searchable(),
            ])
            ->filters([
                SelectFilter::make('elementable_type')->label('Element type')->options(function () {
                    return FormElement::query()
                        ->where('is_template', true)
                        ->whereNotNull('elementable_type')
                        ->distinct()
                        ->pluck('elementable_type')
                        ->mapWithKeys(fn ($t) => [$t => class_basename($t)])
                        ->toArray();
                }),
                SelectFilter::make('tags')->relationship('tags', 'name')->label('Tags')->multiple(),
                TernaryFilter::make('visible_web')->label('Visible on Web')->boolean(),
                TernaryFilter::make('visible_pdf')->label('Visible on PDF')->boolean(),
                TernaryFilter::make('is_required')->label('Required')->boolean(),
                TernaryFilter::make('is_read_only')->label('Read‑only')->boolean(),
                TernaryFilter::make('save_on_submit')->label('Save on Submit')->boolean(),
                TernaryFilter::make('is_template')->label('Is Template')->boolean(),
            ])
            // edit and untemplate action buttons
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (FormElement $r) => ! static::isParented($r)),
                Tables\Actions\Action::make('untemplate')
                    ->label('Untemplate')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FormElement $r) => (bool) $r->is_template)
                    ->action(function (FormElement $r): void {
                        $r->is_template = false;
                        $r->save();
                        Notification::make()->title('Element untemplated')->success()->send();
                    }),
            ])
            ->bulkActions([
                // TODO add bulk action if needed
            ])
            ->emptyStateIcon('heroicon-o-rectangle-group')
            ->emptyStateHeading('No Element Templates')
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListElementTemplateManagement::route('/'),
            'create' => Pages\CreateElementTemplateManagement::route('/create'),
            'view'   => Pages\ViewElementTemplateManagement::route('/{record}'),
            'edit'   => Pages\EditElementTemplateManagement::route('/{record}/edit'),
        ];
    }
}
