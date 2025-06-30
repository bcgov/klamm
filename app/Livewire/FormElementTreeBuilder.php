<?php

namespace App\Livewire;

use App\Models\FormBuilding\FormElement;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Livewire\Component;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;

class FormElementTreeBuilder extends BaseWidget
{
    protected static string $model = FormElement::class;

    protected static int $maxDepth = 5;

    protected ?string $treeTitle = 'Form Elements';

    protected bool $enableTreeTitle = true;

    public $formVersionId;

    public function mount($formVersionId = null)
    {
        $this->formVersionId = $formVersionId;
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->rows(3),
                            TextInput::make('help_text')
                                ->maxLength(500),
                            Select::make('elementable_type')
                                ->label('Element Type')
                                ->options(FormElement::getAvailableElementTypes())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Clear existing elementable data when type changes
                                    $set('elementable_data', []);
                                }),
                            Toggle::make('is_visible')
                                ->label('Visible')
                                ->default(true),
                            Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->default(true),
                            Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->default(true),
                        ]),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            $elementType = $get('elementable_type');
                            if (!$elementType) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('select_element_type')
                                        ->label('')
                                        ->content('Please select an element type in the General tab first.')
                                ];
                            }
                            return $this->getElementSpecificSchema($elementType);
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
                        ->schema([
                            TextInput::make('name')
                                ->disabled(),
                            Textarea::make('description')
                                ->disabled()
                                ->rows(3),
                            TextInput::make('help_text')
                                ->disabled(),
                            TextInput::make('elementable_type')
                                ->label('Element Type')
                                ->disabled(),
                            Toggle::make('is_visible')
                                ->label('Visible')
                                ->disabled(),
                            Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->disabled(),
                            Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->disabled(),
                        ]),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            $elementType = $get('elementable_type');
                            if (!$elementType) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('select_element_type')
                                        ->label('')
                                        ->content('No specific properties available.')
                                ];
                            }
                            return $this->getElementSpecificSchema($elementType, true);
                        }),
                ])
                ->columnSpanFull(),
        ];
    }

    protected function getElementSpecificSchema(string $elementType, bool $disabled = false): array
    {
        // Check if the element type class exists and has the getFilamentSchema method
        if (class_exists($elementType) && method_exists($elementType, 'getFilamentSchema')) {
            return $elementType::getFilamentSchema($disabled);
        }

        // Fallback for element types that don't have schema defined yet
        return [
            \Filament\Forms\Components\Placeholder::make('no_specific_properties')
                ->label('')
                ->content('This element type has no specific properties defined yet.')
        ];
    }

    protected function getTreeActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
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

        $elementType = class_basename($record->elementable_type ?? '');
        return "[{$elementType}] {$record->name}";
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->formVersionId) {
            $data['form_version_id'] = $this->formVersionId;
        }

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

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load polymorphic data into elementable_data field for editing
        if (isset($data['elementable']) && $data['elementable']) {
            $elementableData = $data['elementable']->toArray();
            unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);
            $data['elementable_data'] = $elementableData;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle polymorphic relationship creation/update
        $elementType = $data['elementable_type'];
        $elementableData = $data['elementable_data'] ?? [];

        // Remove elementable_data from main form data as it will be handled separately
        unset($data['elementable_data']);

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): void
    {
        // Extract and store elementable data before updating the main record
        $elementableData = $data['elementable_data'] ?? [];
        $elementType = $data['elementable_type'] ?? null;

        // Remove elementable_data from the main update data
        unset($data['elementable_data']);

        try {
            // Update the main FormElement
            $record->update($data);

            // Handle polymorphic relationship
            if ($elementType && !empty($elementableData)) {
                if ($record->elementable) {
                    // Update existing polymorphic model
                    $record->elementable->update($elementableData);
                } else {
                    // Create new polymorphic model
                    $elementableModel = new $elementType($elementableData);
                    $record->elementable()->save($elementableModel);
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
        // Validate the proposed tree structure before attempting to save
        if (!$list || !$this->validateTreeStructure($list)) {
            // Validation failed, notification already shown in validateTreeStructure
            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        }

        try {
            // If validation passes, proceed with the update
            return parent::updateTree($list);
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
}
