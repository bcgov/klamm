<?php

namespace App\Livewire;

use App\Models\FormBuilding\FormElement;
use App\Events\FormVersionUpdateEvent;
use App\Models\FormBuilding\FormVersion;
use App\Helpers\DataBindingsHelper;
use App\Helpers\ElementPropertiesHelper;
use App\Helpers\GeneralTabHelper;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Helpers\FormElementHelper;

class FormElementTreeBuilder extends BaseWidget
{
    protected static string $model = FormElement::class;

    protected static int $maxDepth = 5;

    protected ?string $treeTitle = 'Form Elements';

    protected bool $enableTreeTitle = true;

    public $formVersionId;

    public $editable = true;

    // Add properties to store pending data
    protected $pendingElementableData = [];
    protected $pendingElementType = null;

    public function mount($formVersionId = null, $editable = true)
    {
        $this->formVersionId = $formVersionId;
        $this->editable = $editable;
    }

    protected function shouldShowTooltips(): bool
    {
        $user = Auth::user();
        return $user && $user->tooltips_enabled;
    }

    public function getEditFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema(function () {
                            return GeneralTabHelper::getEditSchema(
                                fn() => $this->shouldShowTooltips()
                            );
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            return ElementPropertiesHelper::getEditSchema(
                                $get('elementable_type')
                            );
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            return DataBindingsHelper::getEditSchema(
                                $this->formVersionId,
                                fn() => $this->shouldShowTooltips()
                            );
                        }),
                ])
                ->columnSpanFull(),
        ];
    }

    public function getViewFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema(function () {
                            return GeneralTabHelper::getViewSchema(
                                fn() => $this->shouldShowTooltips()
                            );
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            return ElementPropertiesHelper::getViewSchema(
                                $get('elementable_type')
                            );
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            return DataBindingsHelper::getViewSchema(
                                $this->formVersionId,
                                fn() => $this->shouldShowTooltips()
                            );
                        }),
                ])
                ->columnSpanFull(),
        ];
    }



    protected function getTreeActions(): array
    {
        $actions = [
            ViewAction::make()
                ->modalHeading(fn($record) => sprintf(
                    '[%s] %s',
                    FormElement::getElementTypeName($record->elementable_type),
                    $record->name
                ))
                ->form($this->getViewFormSchema())
                ->fillForm(function ($record) {
                    $data = $record->toArray();

                    // Load polymorphic data for viewing
                    if (!$record->relationLoaded('elementable')) {
                        $record->load('elementable');
                    }

                    if ($record->elementable) {
                        $elementableData = $record->elementable->toArray();
                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                        // Load options for select/radio elements
                        if (method_exists($record->elementable, 'options')) {
                            $record->elementable->load('options');
                            $options = $record->elementable->options->map(function ($option) {
                                return [
                                    'label' => $option->label,
                                    'value' => $option->value,
                                ];
                            })->toArray();
                            $elementableData['options'] = $options;
                        }

                        $data['elementable_data'] = $elementableData;
                    }

                    // Load data bindings
                    if (!$record->relationLoaded('dataBindings')) {
                        $record->load('dataBindings');
                    }

                    return $data;
                }),
        ];

        // Only add Edit and Delete actions if editable
        if ($this->editable) {
            $actions[] = EditAction::make()
                ->modalHeading(fn($record) => sprintf(
                    '[%s] %s',
                    FormElement::getElementTypeName($record->elementable_type),
                    $record->name
                ))
                ->form($this->getEditFormSchema())
                ->fillForm(function ($record) {
                    $data = $record->toArray();

                    // Load polymorphic data for editing
                    if (!$record->relationLoaded('elementable')) {
                        $record->load('elementable');
                    }

                    if ($record->elementable) {
                        $elementableData = $record->elementable->toArray();
                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                        // Load options for select/radio elements
                        if (method_exists($record->elementable, 'options')) {
                            $record->elementable->load('options');
                            $options = $record->elementable->options->map(function ($option) {
                                return [
                                    'label' => $option->label,
                                    'value' => $option->value,
                                ];
                            })->toArray();
                            $elementableData['options'] = $options;
                        }

                        $data['elementable_data'] = $elementableData;
                    }

                    // Ensure elementable_type is available even though the field is disabled
                    $data['elementable_type'] = $record->elementable_type;
                    $data['elementable_type_display'] = $record->elementable_type;

                    // Load data bindings
                    if (!$record->relationLoaded('dataBindings')) {
                        $record->load('dataBindings');
                    }

                    return $data;
                })
                ->action(function ($record, array $data) {
                    $data = $this->mutateFormDataBeforeSave($data);
                    $this->handleRecordUpdate($record, $data);
                });

            $actions[] = DeleteAction::make()
                ->modalHeading(fn($record) => sprintf(
                    'Delete [%s] %s',
                    FormElement::getElementTypeName($record->elementable_type),
                    $record->name
                ));
        }

        return $actions;
    }

    public function getTreeRecordIcon(?\Illuminate\Database\Eloquent\Model $record = null): ?string
    {
        if (!$record) {
            return 'heroicon-o-cube';
        }

        // Different icons based on element type
        $elementType = $record->elementable_type;
        return match (class_basename($elementType)) {
            'TextInputFormElement' => 'heroicon-o-pencil-square',
            'TextareaInputFormElement' => 'heroicon-o-document-text',
            'NumberInputFormElement' => 'heroicon-o-calculator',
            'SelectInputFormElement' => 'heroicon-o-list-bullet',
            'RadioInputFormElement' => 'heroicon-o-radio',
            'CheckboxInputFormElement' => 'heroicon-o-check-circle',
            'ButtonInputFormElement' => 'heroicon-o-cursor-arrow-ripple',
            'ContainerFormElement' => 'heroicon-o-rectangle-group',
            'HTMLFormElement' => 'heroicon-o-code-bracket',
            'TextInfoFormElement' => 'heroicon-o-information-circle',
            'DateSelectInputFormElement' => 'heroicon-o-calendar-days',
            default => 'heroicon-o-cube',
        };
    }

    protected function getTreeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTreeQuery();

        if ($this->formVersionId) {
            $query->where('form_version_id', $this->formVersionId);
        }

        // Eager load relationships to prevent lazy loading issues
        $query->with(['children.children', 'parent', 'elementable']);

        return $query;
    }

    protected function getSortedQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getSortedQuery();

        if ($this->formVersionId) {
            $query->where('form_version_id', $this->formVersionId);
        }

        // Eager load relationships for sorted query as well
        $query->with(['children.children', 'parent', 'elementable']);

        return $query;
    }

    public function getTreeRecordTitle(?\Illuminate\Database\Eloquent\Model $record = null): string
    {
        if (!$record) {
            return '';
        }

        // Load data bindings if not already loaded
        if (!$record->relationLoaded('dataBindings')) {
            $record->load('dataBindings');
        }

        $hasDataBindings = $record->dataBindings->isNotEmpty();
        $bindingIndicator = $hasDataBindings ? '<span class="mr-1">🟢</span>' : '';

        $elementTypeName = FormElement::getElementTypeName($record->elementable_type);
        return sprintf(
            '%s<span class="text-gray-400">[%s]</span> %s',
            $bindingIndicator,
            e($elementTypeName),
            e($record->name)
        );
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load polymorphic data into elementable_data field for editing
        $record = $this->getMountedTreeActionForm()?->getModel();

        if ($record) {
            // Load the polymorphic relationship if not already loaded
            if (!$record->relationLoaded('elementable')) {
                $record->load('elementable');
            }

            if ($record->elementable) {
                $elementableData = $record->elementable->toArray();
                // Remove timestamps and primary key
                unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                // Load options for select/radio elements
                if (method_exists($record->elementable, 'options')) {
                    $record->elementable->load('options');
                    $options = $record->elementable->options->map(function ($option) {
                        return [
                            'label' => $option->label,
                            'value' => $option->value,
                        ];
                    })->toArray();
                    $elementableData['options'] = $options;
                }

                $data['elementable_data'] = $elementableData;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract polymorphic data before main record update
        $elementType = $data['elementable_type'];
        $elementableData = $data['elementable_data'] ?? [];

        // Filter out null values from elementable data to let model defaults apply
        // But convert null values to empty strings for text fields that the user might want to clear
        $textFields = ['labelText', 'placeholder', 'helperText', 'mask', 'content', 'legend', 'repeater_item_label'];
        $numericFields = ['min', 'max', 'step', 'defaultValue', 'maxCount', 'rows', 'cols', 'order'];

        $filteredElementableData = [];
        foreach ($elementableData as $key => $value) {
            if ($value !== null && $value !== '') {
                $filteredElementableData[$key] = $value;
            } elseif (in_array($key, $textFields)) {
                // For text fields, convert null to empty string (user wants to clear the field)
                $filteredElementableData[$key] = '';
            } elseif (in_array($key, $numericFields) && ($value === null || $value === '')) {
                // For numeric fields, convert null or empty string to null to allow nullable fields
                $filteredElementableData[$key] = null;
            }
            // For other fields, skip null values to let model defaults apply
        }

        $elementableData = $filteredElementableData;

        // Remove elementable_data from main form data as it will be handled separately
        unset($data['elementable_data']);

        // Store for use in handleRecordUpdate
        $this->pendingElementableData = $elementableData;
        $this->pendingElementType = $elementType;

        return $data;
    }


    protected function handleRecordUpdate($record, array $data): void
    {
        try {
            // Update the main FormElement first
            $record->update($data);

            // Handle polymorphic relationship
            $elementableData = $this->pendingElementableData;
            $elementType = $this->pendingElementType;

            // Extract options data for select/radio elements before updating the main model
            $optionsData = null;
            if (isset($elementableData['options'])) {
                $optionsData = $elementableData['options'];
                unset($elementableData['options']);
            }

            if ($elementType) {
                // Ensure the record has the elementable relationship loaded
                $record->load('elementable');

                if ($record->elementable && $record->elementable_type === $elementType) {
                    // Update existing polymorphic model of the same type
                    if (!empty($elementableData)) {
                        $record->elementable->update($elementableData);
                    }

                    // Handle options update for existing select/radio elements
                    if ($optionsData !== null && is_array($optionsData)) {
                        $this->updateSelectOptions($record->elementable, $optionsData);
                    }
                } else {
                    // Handle type change or missing polymorphic model
                    if ($record->elementable) {
                        $record->elementable->delete();
                    }

                    // Create new polymorphic model
                    if (class_exists($elementType)) {
                        $elementableModel = $elementType::create($elementableData ?: []);
                        $record->update([
                            'elementable_type' => $elementType,
                            'elementable_id' => $elementableModel->id,
                        ]);
                        // Refresh the relationship
                        $record->load('elementable');

                        // Handle options for new select/radio elements
                        if ($optionsData !== null && is_array($optionsData)) {
                            $this->createSelectOptions($elementableModel, $optionsData);
                        }
                    }
                }
            }

            // Clear pending data
            $this->pendingElementableData = [];
            $this->pendingElementType = null;

            // Fire update event for element modification
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['updated_element' => $record->fresh()->toArray()],
                        'element_updated',
                        false
                    );
                }
            }
        } catch (\InvalidArgumentException $e) {
            // Handle our custom validation exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Cannot Update Element')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            // Re-throw to prevent the update from completing
            throw $e;
        } catch (\Exception $e) {
            // Handle any other exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('An unexpected error occurred while updating the element: ' . $e->getMessage())
                ->persistent()
                ->send();

            throw $e;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->formVersionId) {
            $data['form_version_id'] = $this->formVersionId;
        }

        // Remove template_id as it's only used for prefilling
        unset($data['template_id']);

        // Validate parent can have children if parent_id is set
        if (isset($data['parent_id']) && $data['parent_id'] && $data['parent_id'] !== -1) {
            $parent = FormElement::find($data['parent_id']);
            if ($parent && !$parent->canHaveChildren()) {
                // Send a user-friendly notification
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Cannot Add Here')
                    ->body("Only container elements can have children. '{$parent->name}' (type: {$parent->element_type}) cannot contain child elements.")
                    ->persistent()
                    ->send();

                throw new \InvalidArgumentException("Only container elements can have children. Cannot add child to '{$parent->name}' (type: {$parent->element_type}).");
            }
        }

        // Extract polymorphic data
        $elementType = $data['elementable_type'];
        $elementableData = $data['elementable_data'] ?? [];
        unset($data['elementable_data']);

        // Filter out null values from elementable data to let model defaults apply
        // But convert null values to empty strings for text fields that the user might want to clear
        $textFields = ['labelText', 'placeholder', 'helperText', 'mask', 'content', 'legend', 'repeater_item_label'];
        $numericFields = ['min', 'max', 'step', 'defaultValue', 'maxCount', 'rows', 'cols', 'order'];

        $filteredElementableData = [];
        foreach ($elementableData as $key => $value) {
            if ($value !== null && $value !== '') {
                $filteredElementableData[$key] = $value;
            } elseif (in_array($key, $textFields)) {
                // For text fields, convert null to empty string (user wants to clear the field)
                $filteredElementableData[$key] = '';
            } elseif (in_array($key, $numericFields) && ($value === null || $value === '')) {
                // For numeric fields, convert null or empty string to null to allow nullable fields
                $filteredElementableData[$key] = null;
            }
            // For other fields, skip null values to let model defaults apply
        }

        $elementableData = $filteredElementableData;

        // Store for use after main record creation
        $this->pendingElementableData = $elementableData;
        $this->pendingElementType = $elementType;

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            // Create the polymorphic model first if there's data
            $elementableModel = null;
            $elementType = $this->pendingElementType;
            $elementableData = $this->pendingElementableData;

            // Extract options data for select/radio elements before creating the main model
            $optionsData = null;
            if (isset($elementableData['options'])) {
                $optionsData = $elementableData['options'];
                unset($elementableData['options']);
            }

            if (!empty($elementableData) && class_exists($elementType)) {
                $elementableModel = $elementType::create($elementableData);
            } elseif (class_exists($elementType)) {
                // Create with empty array to trigger model defaults
                $elementableModel = $elementType::create([]);
            }

            // Set the polymorphic relationship data
            if ($elementableModel) {
                $data['elementable_type'] = $elementType;
                $data['elementable_id'] = $elementableModel->id;
            }

            // Create the main FormElement
            $formElement = FormElement::create($data);

            // Handle options for select/radio elements
            if ($elementableModel && $optionsData && is_array($optionsData)) {
                FormElementHelper::createSelectOptions($elementableModel, $optionsData);
            }

            // Clear pending data
            $this->pendingElementableData = [];
            $this->pendingElementType = null;

            // Fire update event for element creation
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['created_element' => $formElement->fresh()->toArray()],
                        'element_created',
                        false
                    );
                }
            }

            return $formElement;
        } catch (\Exception $e) {
            // Clean up any created polymorphic model if main creation fails
            if (isset($elementableModel) && $elementableModel) {
                $elementableModel->delete();
            }
            throw $e;
        }
    }

    /**
     * Get custom CSS classes for tree records
     */
    public function getTreeRecordClasses(?\Illuminate\Database\Eloquent\Model $record = null): string
    {
        if (!$record) {
            return '';
        }

        $classes = [];

        // Add class based on whether element can have children
        if ($record->canHaveChildren()) {
            $classes[] = 'can-have-children';
        } else {
            $classes[] = 'cannot-have-children';
        }

        // Add specific type class
        $elementType = class_basename($record->elementable_type ?? '');
        $classes[] = 'element-type-' . strtolower($elementType);

        return implode(' ', $classes);
    }

    /**
     * Override the tree update method to handle validation gracefully
     */
    public function updateTree(?array $list = null): array
    {
        // Prevent tree updates if not editable
        if (!$this->editable) {
            Notification::make()
                ->warning()
                ->title('Cannot Modify Elements')
                ->body('Form elements cannot be modified when the form version is not in draft status.')
                ->send();

            return $this->refreshTreeData();
        }

        // Validate the proposed tree structure before attempting to save
        if (!$list || !$this->validateTreeStructure($list)) {
            // Validation failed, notification already shown in validateTreeStructure
            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        }

        try {
            // If validation passes, proceed with the update
            $result = parent::updateTree($list);

            // Fire update event for tree structure changes (moves/reorders)
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['tree_structure' => $list],
                        'elements_moved',
                        false
                    );
                }
            }

            return $result;
        } catch (\InvalidArgumentException $e) {
            // Handle model validation exceptions with user-friendly notification
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Cannot Update Tree')
                ->body('Only container elements can have children. The tree structure has been reverted.')
                ->persistent()
                ->send();

            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        } catch (\Exception $e) {
            // Handle any other exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('An unexpected error occurred while updating the tree structure.')
                ->persistent()
                ->send();

            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        }
    }

    /**
     * Refresh the tree data from the database
     */
    protected function refreshTreeData(): array
    {
        // Get fresh data from the database in the same format the tree expects
        return $this->getTreeQuery()
            ->with(['children' => function ($query) {
                $query->orderBy('order');
            }])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->toArray();
    }

    /**
     * Validate the proposed tree structure before saving
     */
    protected function validateTreeStructure(array $list): bool
    {
        foreach ($list as $item) {
            // Check if this item has a parent_id and validate the parent-child relationship
            if (isset($item['parent_id']) && $item['parent_id'] && $item['parent_id'] !== -1) {
                $parent = FormElement::find($item['parent_id']);
                $child = FormElement::find($item['id']);

                if ($parent && $child && !$parent->canHaveChildren()) {
                    // Show user-friendly notification
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Cannot Move Element')
                        ->body("'{$child->name}' cannot be moved into '{$parent->name}' (type: {$parent->element_type}). Only Container elements can have children. The tree has been reverted to its previous state.")
                        ->persistent()
                        ->send();

                    return false;
                }
            }

            // Recursively validate children if they exist
            if (isset($item['children']) && is_array($item['children']) && !empty($item['children'])) {
                if (!$this->validateTreeStructure($item['children'])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        if (!$this->formVersionId) {
            return view('livewire.form-element-tree-builder');
        }

        // Ensure custom CSS classes are applied to tree items
        $this->prepareTreeItemClasses();

        return parent::render();
    }

    /**
     * Prepare custom CSS classes for tree items
     */
    protected function prepareTreeItemClasses(): void
    {
        // This method will be called before rendering to ensure
        // that the tree items have the proper CSS classes applied
        // The actual class application happens in the getTreeRecordClasses method
    }



    /**
     * Update select options for select/radio elements
     */
    protected function updateSelectOptions($elementableModel, array $optionsData): void
    {
        FormElementHelper::updateSelectOptions($elementableModel, $optionsData);
    }

    public function getNodeCollapsedState(?\Illuminate\Database\Eloquent\Model $record = null): bool
    {
        // All tree nodes will be collapsed by default.
        return false;
    }
}
