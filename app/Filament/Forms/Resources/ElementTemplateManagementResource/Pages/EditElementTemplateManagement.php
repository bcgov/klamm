<?php

namespace App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;

use App\Filament\Forms\Resources\ElementTemplateManagementResource;
use App\Helpers\GeneralTabHelper;
use App\Models\FormBuilding\FormElement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Actions\Action;

class EditElementTemplateManagement extends EditRecord
{
    protected static string $resource = ElementTemplateManagementResource::class;

    protected static ?string $title = 'Edit Element Template';

    public function form(Form $form): Form
    {
        $schema = GeneralTabHelper::getCreateSchema(
            shouldShowTooltipsCallback: fn () => (bool) (auth()->user()?->tooltips_enabled ?? false),
            includeTemplateSelector: false,
            disabledCallback: null
        );

        // Keep is_template ON, locked and hidden
        $schema = $this->tweakIsTemplateField($schema, hide: true);

        // UUID
        $schema[] = Grid::make(12)->schema([
            TextInput::make('_reference_uuid_display')
                ->label('Reference UUID')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function ($state, ?FormElement $record) {
                    if (! $record) return '';
                    return $record->reference_uuid
                        ?? $record->uuid
                        ?? $record->public_id
                        ?? data_get($record, 'meta.reference_uuid')
                        ?? (string) $record->getAttribute('id');
                })
                ->extraInputAttributes(['x-ref' => 'uuidField'])
                ->suffixAction(
                    Action::make('copy_uuid')
                        ->icon('heroicon-o-clipboard')
                        ->tooltip('Copy Reference UUID')
                        ->extraAttributes(['x-data' => '{}', 'x-on:click' => 'navigator.clipboard.writeText($refs.uuidField.value)'])
                )
                ->columnSpan(12),
            TextInput::make('_data_path_display')
                ->label('Data path')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(function ($state, ?FormElement $record) {
                    if (! $record) return '';
                    return optional($record->dataBindings)
                        ? $record->dataBindings->pluck('path')->filter()->unique()->join(', ')
                        : '';
                })
                ->extraInputAttributes(['x-ref' => 'dataPath'])
                ->suffixAction(
                    Action::make('copy_path')
                        ->icon('heroicon-o-clipboard')
                        ->tooltip('Copy data path')
                        ->extraAttributes([
                            'x-data' => '{}',
                            'x-on:click' => 'navigator.clipboard.writeText($refs.dataPath.value)',
                        ])
                )
                ->columnSpan(12),
        ]);

        return $form->schema($schema);
    }


    // Admin only
    protected function authorizeAccess(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('admin'), 403);

        $record = $this->getRecord();

        $isParented =
            ! is_null($record->form_version_id) ||
            (property_exists($record, 'parent_id') && $record->parent_id !== null && $record->parent_id !== -1);

        abort_if($isParented, 403, 'This template is parented on a form and cannot be edited.');
    }

    // Keep invariants intact
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_template']       = true;
        $data['form_version_id']   = null;
        $data['source_element_id'] = $data['source_element_id'] ?? null;

        return $data;
    }

    /**
     * Persist updates + sync relations (tags / options / data bindings).
     */
    protected function handleRecordUpdate($record, array $data): FormElement
    {
        /** @var FormElement $record */

        // Extract relation/aux data from payload
        $tagIds          = $data['tags']           ?? [];
        unset($data['tags']);

        $dataBindings    = $data['dataBindings']   ?? [];
        unset($data['dataBindings']);

        $newType         = $data['elementable_type'] ?? null;
        $elementableData = $data['elementable_data'] ?? [];
        unset($data['elementable_data']);

        // Options (common for selects/radios)
        $optionsData = null;
        if (isset($elementableData['options'])) {
            $optionsData = $elementableData['options'];
            unset($elementableData['options']);
        }

        // If type changed, create a new elementable and repoint
        if ($newType && class_exists($newType) && $record->elementable_type !== $newType) {
            $clean = array_filter($elementableData, fn ($v) => $v !== null);
            $newElementable  = $newType::create($clean);

            $data['elementable_type'] = $newType;
            $data['elementable_id']   = $newElementable->getKey();
        } elseif ($record->elementable && ! empty($elementableData)) {
            // Type unchanged → update existing elementable
            $record->elementable->fill(array_filter($elementableData, fn ($v) => $v !== null));
            $record->elementable->save();
        }

        // Update main row
        $record->fill($data);
        $record->save();

        // Sync tags
        if (is_array($tagIds)) {
            $record->tags()->sync($tagIds);
        }

        // Persist options/data bindings if helpers exist
        if ($optionsData !== null && method_exists($record, 'syncOptions')) {
            $record->syncOptions($optionsData);
        }
        if (! empty($dataBindings) && method_exists($record, 'syncDataBindings')) {
            $record->syncDataBindings($dataBindings);
        }

        return $record;
    }

    // After save, go to View page
    protected function getRedirectUrl(): string
    {
        return ElementTemplateManagementResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return parent::getCancelFormAction()
            ->url(ElementTemplateManagementResource::getUrl('index'))
            ->color('secondary');
    }

    protected function afterSave(): void
    {
        Notification::make()->title('Template updated')->success()->send();
    }

    // Lock and/or hide the is_template toggle in the reused schema
    private function tweakIsTemplateField(array $components, bool $hide): array
    {
        $map = function (Component $c) use (&$map, $hide): ?Component {
            if (method_exists($c, 'getChildComponents')) {
                $children = $c->getChildComponents();
                $new = [];
                foreach ($children as $child) {
                    if ($child instanceof Toggle && $child->getName() === 'is_template') {
                        if ($hide) continue;
                        $child = $child->default(true)->disabled()->dehydrated(true);
                    } else {
                        $child = $map($child);
                    }
                    if ($child) $new[] = $child;
                }
                if (method_exists($c, 'childComponents'))   $c = $c->childComponents($new);
                elseif (method_exists($c, 'schema'))        $c = $c->schema($new);
            } else {
                if ($c instanceof Toggle && $c->getName() === 'is_template') {
                    return $hide ? Toggle::make('is_template')->hidden()->default(true)->dehydrated(true)
                                 : $c->default(true)->disabled()->dehydrated(true);
                }
            }
            return $c;
        };

        $out = [];
        foreach ($components as $comp) {
            $out[] = $map($comp);
        }

        // Ensure value persists even if helper omitted it.
        if (! $this->schemaHasField($out, 'is_template')) {
            $out[] = Toggle::make('is_template')->hidden()->default(true)->dehydrated(true);
        }

        return $out;
    }

    private function schemaHasField(array $components, string $name): bool
    {
        $check = function (Component $c) use (&$check, $name): bool {
            if (method_exists($c, 'getName') && $c->getName() === $name) return true;
            if (method_exists($c, 'getChildComponents')) {
                foreach ($c->getChildComponents() as $ch) if ($check($ch)) return true;
            }
            return false;
        };
        foreach ($components as $c) if ($check($c)) return true;
        return false;
    }

    // Resolve UUID for display
    private function resolveReferenceUuid(): ?string
    {
        /** @var FormElement|null $record */
        $record = $this->getRecord();

        if (! $record) return null;

        return $record->reference_uuid
            ?? $record->uuid
            ?? $record->public_id
            ?? data_get($record, 'meta.reference_uuid')
            ?? (string) $record->getAttribute('id');
    }
}
