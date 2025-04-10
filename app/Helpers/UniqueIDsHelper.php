<?php

namespace App\Helpers;

use Closure;

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
}
