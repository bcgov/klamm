<?php

namespace App\Filament\Resources\FormVersionResource\Widgets;

use App\Models\FormBuilding\FormElement;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use SolutionForest\FilamentTree\Actions\Action;
use SolutionForest\FilamentTree\Actions\ActionGroup;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;

class FormElementWidget extends BaseWidget
{
    protected static string $model = FormElement::class;

    protected static int $maxDepth = 2;

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
                ->required(),
            Toggle::make('is_visible')
                ->label('Visible')
                ->default(true),
            Toggle::make('visible_web')
                ->label('Visible on Web')
                ->default(true),
            Toggle::make('visible_pdf')
                ->label('Visible on PDF')
                ->default(true),
        ];
    }

    public function getViewFormSchema(): array
    {
        return [
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

    protected function getTreeQuery(): Builder
    {
        $query = parent::getTreeQuery();

        if ($this->formVersionId) {
            $query->where('form_version_id', $this->formVersionId);
        }

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

        return $data;
    }
}
