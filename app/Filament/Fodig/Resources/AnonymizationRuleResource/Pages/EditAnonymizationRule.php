<?php

namespace App\Filament\Fodig\Resources\AnonymizationRuleResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnonymizationRule extends EditRecord
{
    protected static string $resource = AnonymizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Anonymization rule updated';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['method_assignments']);

        return $data;
    }

    protected function afterSave(): void
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
