<?php

namespace App\Filament\Fodig\Resources\AnonymizationRuleResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymizationRule extends CreateRecord
{
    protected static string $resource = AnonymizationRuleResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Anonymization rule created';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Strip out the repeater data — it goes to the pivot, not the model.
        unset($data['method_assignments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncMethodAssignments();
    }

    private function syncMethodAssignments(): void
    {
        $items = $this->data['method_assignments'] ?? [];
        $sync = [];

        foreach ($items as $item) {
            $methodId = $item['method_id'] ?? null;

            if (! $methodId) {
                continue;
            }

            $isDefault = ! empty($item['is_default']);
            $strategy = $isDefault ? null : (trim((string) ($item['strategy'] ?? '')) ?: null);

            $sync[$methodId] = [
                'is_default' => $isDefault,
                'strategy' => $strategy,
            ];
        }

        $this->record->methods()->sync($sync);
    }
}
