<?php

namespace App\Filament\Resources\FormVersionResource\Widgets;

use App\Models\Element;
use Filament\Notifications\Notification;
use SolutionForest\FilamentTree\Actions\Action;
use SolutionForest\FilamentTree\Actions\ActionGroup;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;

class ElementsTreeWidget extends BaseWidget
{
    protected static string $model = Element::class;

    protected static int $maxDepth = 10;

    protected ?string $treeTitle = 'Form Elements';

    protected bool $enableTreeTitle = true;

    public ?int $formVersionId = null;

    protected function getFormSchema(): array
    {
        return [
            Select::make('type')
                ->label('Element Type')
                ->options([
                    'container' => 'Container',
                    'field' => 'Field',
                ])
                ->required()
                ->reactive(),

            TextInput::make('custom_label')
                ->label('Label')
                ->required(),

            TextInput::make('order')
                ->label('Order')
                ->numeric()
                ->required(),

            Toggle::make('hide_label')
                ->label('Hide Label'),

            Toggle::make('visible_web')
                ->label('Visible on Web')
                ->default(true),

            Toggle::make('visible_pdf')
                ->label('Visible on PDF')
                ->default(true),

            // Container-specific fields
            Toggle::make('has_repeater')
                ->label('Has Repeater')
                ->visible(fn($get) => $get('type') === 'container'),

            Toggle::make('has_clear_button')
                ->label('Has Clear Button')
                ->visible(fn($get) => $get('type') === 'container'),

            TextInput::make('repeater_item_label')
                ->label('Repeater Item Label')
                ->visible(fn($get) => $get('type') === 'container' && $get('has_repeater')),

            // Field-specific fields
            Select::make('field_template_id')
                ->label('Field Template')
                ->relationship('fieldTemplate', 'name')
                ->visible(fn($get) => $get('type') === 'field'),

            TextInput::make('custom_mask')
                ->label('Custom Mask')
                ->visible(fn($get) => $get('type') === 'field'),
        ];
    }



    // INFOLIST, CAN DELETE
    public function getViewFormSchema(): array
    {
        return [
            //
        ];
    }

    // CUSTOMIZE ICON OF EACH RECORD, CAN DELETE
    // public function getTreeRecordIcon(?\Illuminate\Database\Eloquent\Model $record = null): ?string
    // {
    //     return null;
    // }

    // CUSTOMIZE ACTION OF EACH RECORD, CAN DELETE 
    // protected function getTreeActions(): array
    // {
    //     return [
    //         Action::make('helloWorld')
    //             ->action(function () {
    //                 Notification::make()->success()->title('Hello World')->send();
    //             }),
    //         // ViewAction::make(),
    //         // EditAction::make(),
    //         ActionGroup::make([
    //             
    //             ViewAction::make(),
    //             EditAction::make(),
    //         ]),
    //         DeleteAction::make(),
    //     ];
    // }
    // OR OVERRIDE FOLLOWING METHODS
    //protected function hasDeleteAction(): bool
    //{
    //    return true;
    //}
    //protected function hasEditAction(): bool
    //{
    //    return true;
    //}
    //protected function hasViewAction(): bool
    //{
    //    return true;
    //}
}
