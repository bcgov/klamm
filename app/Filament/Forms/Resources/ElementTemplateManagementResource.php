<?php

namespace App\Filament\Forms\Resources;

use App\Helpers\GeneralTabHelper;
use App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;
use App\Models\FormBuilding\FormElement;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        return !is_null($r->form_version_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Field name')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('elementable_type')->label('Element type')
                    ->formatStateUsing(function (?string $state) {
                        if (!$state)
                            return null;

                        return FormElement::getElementTypeName($state)
                            ?? class_basename($state);
                    })->sortable(),
                Tables\Columns\TextColumn::make('reference_id')->label('Reference ID')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('uuid')->label('Reference UUID')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TagsColumn::make('tags.name')->label('Tags')->limit(3)->separator(','),
                Tables\Columns\TextColumn::make('description')->label('Description')->lineClamp(2)->toggleable()->searchable(),
                Tables\Columns\TextColumn::make('form_version_id')->label('Form version')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('data_path_preview')->label('Data path')
                    ->state(fn(FormElement $r) => optional($r->dataBindings->first())->path)
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
                    // Canonical label map from the model
                    $labels = FormElement::getAvailableElementTypes(); // [FQCN => 'Text Input', ...]
        
                    // Only show types that are present among templates
                    $typesInDb = FormElement::query()
                        ->where('is_template', true)
                        ->whereNotNull('elementable_type')
                        ->distinct()
                        ->pluck('elementable_type')
                        ->toArray();

                    $options = array_intersect_key($labels, array_flip($typesInDb));

                    // Fallback for any unknown keys
                    foreach ($typesInDb as $fqcn) {
                        if (!isset($options[$fqcn])) {
                            $options[$fqcn] = class_basename($fqcn);
                        }
                    }

                    asort($options);
                    return $options; // [FQCN => 'Text Input', ...]
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
                Tables\Actions\Action::make('openBuilder')
                    ->label('To Builder')
                    ->icon('heroicon-o-cube-transparent')
                    ->visible(fn(FormElement $r) => filled($r->form_version_id))
                    ->url(fn(FormElement $r) => url("/forms/form-versions/{$r->form_version_id}/build"))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn(FormElement $r) => !static::isParented($r)),
                Tables\Actions\Action::make('untemplate')
                    ->label('Untemplate')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color(fn(FormElement $r) => is_null($r->form_version_id) ? 'danger' : 'warning')
                    ->requiresConfirmation()
                    ->modalHeading('Untemplate this element?')
                    ->modalDescription('If this element is not parented, it will be permanently deleted. If it is parented, it will stay but no longer marked as a template.')
                    // show only if currently a template (parented or not)
                    ->visible(fn(FormElement $r) => (bool) $r->is_template)
                    ->action(function (FormElement $r): void {
                        // If NOT parented (form_version_id is NULL): delete the template entirely
                        if (is_null($r->form_version_id)) {
                            DB::transaction(function () use ($r) {
                                // detach pivots & delete dependents
                                $r->tags()->detach();
                                $r->dataBindings()->delete();

                                // If store element-specific config on the morph target, remove it too
                                if (method_exists($r, 'elementable') && $r->elementable) {
                                    // If the child also uses SoftDeletes and you want true removal:
                                    method_exists($r->elementable, 'forceDelete')
                                        ? $r->elementable->forceDelete()
                                        : $r->elementable->delete();
                                }

                                // 2) HARD delete the template to release db spaces
                                method_exists($r, 'forceDelete') ? $r->forceDelete() : $r->delete();
                            });

                            Notification::make()
                                ->title('Template deleted')
                                ->success()
                                ->send();

                            return;
                        }

                        // If parented: just flip is_template to false (keep the record)
                        $r->is_template = false;
                        $r->save();

                        Notification::make()
                            ->title('Template untemplated')
                            ->body('This template is used on a form, so it was marked as non-template instead of being deleted.')
                            ->success()
                            ->send();
                    })->successRedirectUrl(url()->current()),
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
            'index' => Pages\ListElementTemplateManagement::route('/'),
            'create' => Pages\CreateElementTemplateManagement::route('/create'),
            'view' => Pages\ViewElementTemplateManagement::route('/{record}'),
            'edit' => Pages\EditElementTemplateManagement::route('/{record}/edit'),
        ];
    }

    // Helper to fetch the readable labels from the same source the dropdown uses.
    private static function getElementTypeLabels(): array
    {
        // Try a couple of common method names on your helper:
        if (method_exists(GeneralTabHelper::class, 'getElementTypeOptions')) {
            // Expected shape: [ FQCN => 'Text Input', ... ]
            return GeneralTabHelper::getElementTypeOptions();
        }
        if (method_exists(GeneralTabHelper::class, 'elementTypeOptions')) {
            return GeneralTabHelper::elementTypeOptions();
        }

        // Fallback: empty map; callers will fallback to class_basename
        return [];
    }
}
