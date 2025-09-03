<?php

namespace App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;

use App\Filament\Forms\Resources\ElementTemplateManagementResource;
use App\Models\FormBuilding\FormElement;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Toggle;
use App\Helpers\GeneralTabHelper;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateElementTemplateManagement extends CreateRecord
{
    protected static string $resource = ElementTemplateManagementResource::class;

    protected static ?string $title = 'New Element Template';

    public function form(Form $form): Form
    {
        // Utilize general tab, but hide the “start from template” dropdown.
        $schema = GeneralTabHelper::getCreateSchema(
            shouldShowTooltipsCallback: fn() => (bool) (auth()->user()?->tooltips_enabled ?? false),
            includeTemplateSelector: false,
            disabledCallback: null
        );

        // Keep is_template ON, locked and hidden
        $schema = $this->tweakIsTemplateField($schema, hide: true);

        return $form->schema($schema);
    }

    // Admins only
    protected function authorizeAccess(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('admin'), 403);
    }

    /**
     * Normalize common multi-select payloads into a flat array of IDs.
     * Accepts: [1,2], [{'id'=>1},{'id'=>2}], Eloquent\Collection of models/arrays, null.
     */
    private function normalizeIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            if ($value->isEmpty())
                return [];
            // Collection of models or arrays
            if (is_object($value->first()) && method_exists($value->first(), 'getKey')) {
                return $value->map(fn($m) => $m->getKey())->all();
            }
            return $value->map(fn($v) => is_array($v) ? ($v['id'] ?? $v['value'] ?? $v) : $v)->all();
        }

        if (is_array($value)) {
            if ($value === [])
                return [];
            // Array of models/arrays/ids
            return array_values(array_map(function ($v) {
                if (is_object($v) && method_exists($v, 'getKey'))
                    return $v->getKey();
                if (is_array($v))
                    return $v['id'] ?? $v['value'] ?? null;
                return $v;
            }, $value));
        }

        if (is_null($value) || $value === '')
            return [];

        // Single model/array/id
        if (is_object($value) && method_exists($value, 'getKey'))
            return [$value->getKey()];
        if (is_array($value))
            return [Arr::get($value, 'id', Arr::get($value, 'value'))];

        return [(int) $value];
    }

    /**
     * Create & persist a template element (with tags/options/bindings) in a transaction.
     * Ensures form_version_id is NULL and is_template is TRUE.
     */
    protected function handleRecordCreation(array $data): FormElement
    {
        return DB::transaction(function () use ($data) {
            // Pull relation/aux data out of the base payload
            $tagIds = $this->normalizeIds($data['tags'] ?? []);
            unset($data['tags']);

            $dataBindings = $data['dataBindings'] ?? [];
            unset($data['dataBindings']);

            $elementType = $data['elementable_type'] ?? null;
            $elementableData = $data['elementable_data'] ?? [];
            unset($data['elementable_data']);

            // Extract options (common for select/radios)
            $optionsData = null;
            if (isset($elementableData['options'])) {
                $optionsData = $elementableData['options'];
                unset($elementableData['options']);
            }

            // Enforce template invariants
            $data['is_template'] = true;
            $data['form_version_id'] = null;
            $data['source_element_id'] = $data['source_element_id'] ?? null;

            // Create elementable first if provided
            if ($elementType && class_exists($elementType)) {
                $clean = array_filter($elementableData, fn($v) => $v !== null);
                $elementable = $elementType::create($clean);
                $data['elementable_type'] = $elementType;
                $data['elementable_id'] = $elementable->getKey();
            }

            /** @var FormElement $formElement */
            $formElement = FormElement::create($data);

            // Sync tags to pivot
            if (!empty($tagIds)) {
                $formElement->tags()->sync($tagIds);
            }

            // Persist options / data bindings if your model supports helpers
            if ($optionsData !== null && method_exists($formElement, 'syncOptions')) {
                $formElement->syncOptions($optionsData);
            }
            if (!empty($dataBindings) && method_exists($formElement, 'syncDataBindings')) {
                $formElement->syncDataBindings($dataBindings);
            }

            return $formElement;
        });
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Element template created')
            ->success()
            ->send();

        // $this->redirect(ElementTemplateManagementResource::getUrl('index'));
    }

    // After create, go to the view page for the new record
    protected function getRedirectUrl(): string
    {
        return ElementTemplateManagementResource::getUrl('view', ['record' => $this->record]);
    }

    // Ensure the is_template toggle in the reused schema is ON and fixed
    private function tweakIsTemplateField(array $components, bool $hide): array
    {
        $map = function (Component $c) use (&$map, $hide): ?Component {
            if (method_exists($c, 'getChildComponents')) {
                $children = $c->getChildComponents();
                $new = [];
                foreach ($children as $child) {
                    if ($child instanceof Toggle && $child->getName() === 'is_template') {
                        if ($hide) {
                            continue;
                        }
                        $child = $child->default(true)->disabled()->dehydrated(true);
                    } else {
                        $child = $map($child);
                    }
                    if ($child)
                        $new[] = $child;
                }
                if (method_exists($c, 'childComponents'))
                    $c = $c->childComponents($new);
                elseif (method_exists($c, 'schema'))
                    $c = $c->schema($new);
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

        // If helper didn’t include the field, add a hidden dehydrated true so it persists.
        $has = $this->schemaHasField($out, 'is_template');
        if (!$has) {
            $out[] = Toggle::make('is_template')->hidden()->default(true)->dehydrated(true);
        }

        return $out;
    }

    private function schemaHasField(array $components, string $name): bool
    {
        $check = function (Component $c) use (&$check, $name): bool {
            if (method_exists($c, 'getName') && $c->getName() === $name)
                return true;
            if (method_exists($c, 'getChildComponents')) {
                foreach ($c->getChildComponents() as $ch)
                    if ($check($ch))
                        return true;
            }
            return false;
        };
        foreach ($components as $c)
            if ($check($c))
                return true;
        return false;
    }
}
