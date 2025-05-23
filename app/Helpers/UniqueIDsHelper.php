<?php

namespace App\Helpers;

use App\Filament\Forms\Resources\FormVersionResource;
use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;

class UniqueIDsHelper
{
    public static function extractInstanceIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            // Add default instance IDs and custom instance IDs.
            if (isset($block['data']['instance_id'])) {
                $ids[] = $block['data']['instance_id'];
            }
            if (isset($block['data']['custom_instance_id'])) {
                $ids[] = $block['data']['custom_instance_id'];
            }

            // Check for nested components in a container or group block.
            if (isset($block['data']['components'])) {
                $ids = array_merge($ids, self::extractInstanceIds($block['data']['components']));
            }
            if (isset($block['data']['form_fields'])) {
                $ids = array_merge($ids, self::extractInstanceIds($block['data']['form_fields']));
            }
        }

        return $ids;
    }

    /**
     * Returns a custom validation closure that checks for duplicate instance IDs
     * using the IDs stored in the session under the key "all_instance_ids".
     */
    public static function uniqueIDsRule(): Closure
    {
        return function (string $attribute, $id, Closure $fail) {
            $allIds = session()->get('all_instance_ids', []);
            $occurrences = collect($allIds)->filter(fn($item) => $item === $id)->count();

            if ($occurrences > 1) {
                $fail("The instance ID '{$id}' is already in use.");
            }
        };
    }

    public static function calculateElementID(): string
    {
        $counter = FormVersionResource::getElementCounter();
        FormVersionResource::incrementElementCounter();
        return 'element' . $counter;
    }

    // Recursively assign new instance IDs to cloned elements
    protected static function processNestedInstanceIDs(array $data): array
    {
        return collect($data)
            ->map(function ($value) {
                if (is_array($value)) {
                    if (array_key_exists('instance_id', $value)) {
                        $value['instance_id'] = UniqueIDsHelper::calculateElementID();
                    }
                    return self::processNestedInstanceIDs($value);
                }

                return $value;
            })
            ->all();
    }

    public static function cloneElement()
    {
        return fn(Action $action) => $action->action(
            function ($arguments, Builder $component): void {
                // Identify element in Builder state
                $state = $component->getState();
                $elementKey = $arguments['item']; // Outside of a nested Builder, this associative array key is an integer, but inside, it is a UUID.
                $elementIndex = array_search($arguments['item'], array_keys($state)); // So we need to get the index of that key

                // Clone block, generate new instance ID
                $new_instance_id = self::calculateElementID();
                $clonedBlock = self::processNestedInstanceIDs($state[$elementKey]['data']);
                $clonedBlock['instance_id'] = $new_instance_id;

                // Add the cloned block into state
                array_splice($state, $elementIndex + 1, 0, [[
                    ...$state[$elementKey],
                    'data' => $clonedBlock,
                ]]);
                $component->state($state);
            }
        );
    }
}
