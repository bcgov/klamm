<?php

namespace App\Filament\Fodig\Resources\BoundarySystemInterfaceResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Split;
use App\Models\BoundarySystemInterface;

class ViewBoundarySystemInterface extends ViewRecord
{
    protected static string $resource = BoundarySystemInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Interface Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('short_description'),
                        TextEntry::make('transaction_frequency')
                            ->label('Transaction Frequency')
                            ->formatStateUsing(fn($state) => BoundarySystemInterface::getTransactionFrequencyOptions()[$state] ?? $state),
                        TextEntry::make('transaction_schedule'),
                        TextEntry::make('complexity')
                            ->formatStateUsing(fn($state) => BoundarySystemInterface::getComplexityOptions()[$state] ?? $state),
                        TextEntry::make('integration_type')
                            ->label('Integration Type')
                            ->formatStateUsing(fn($state) => BoundarySystemInterface::getIntegrationTypeOptions()[$state] ?? $state),
                        TextEntry::make('mode_of_transfer')
                            ->label('Mode of Transfer')
                            ->formatStateUsing(fn($state) => BoundarySystemInterface::getModeOfTransferOptions()[$state] ?? $state),
                        TextEntry::make('protocol')
                            ->formatStateUsing(fn($state) => BoundarySystemInterface::getProtocolOptions()[$state] ?? $state),
                        TextEntry::make('tags')
                            ->label('Tags')
                            ->bulleted()
                            ->listWithLineBreaks()
                            ->limit(10)
                            ->getStateUsing(fn($record) => $record->tags->pluck('name')->toArray()),
                    ])
                    ->columns(2)
                    ->collapsed(false),

                Section::make('Data Format and Security')
                    ->schema([
                        TextEntry::make('data_format')
                            ->label('Data Formats')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->getStateUsing(
                                fn($record) => collect($record->data_format)
                                    ->map(fn($val) => BoundarySystemInterface::getDataFormatOptions()[$val] ?? $val)
                                    ->toArray()
                            ),
                        TextEntry::make('security')
                            ->label('Security')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->getStateUsing(
                                fn($record) => collect($record->security)
                                    ->map(fn($val) => BoundarySystemInterface::getSecurityOptions()[$val] ?? $val)
                                    ->toArray()
                            ),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Systems')
                    ->schema([
                        Split::make([
                            Section::make([
                                TextEntry::make('sourceSystem.name')->label('System Name'),
                                TextEntry::make('sourceSystem.contact.department_name')->label('Department')
                                    ->visible(fn($record) => $record->sourceSystem?->contact !== null),
                                TextEntry::make('sourceSystem.contact.emails_list')
                                    ->label('Contact Emails')
                                    ->visible(fn($record) => $record->sourceSystem?->contact?->emails->isNotEmpty()),
                            ])
                                ->label('Source System')
                                ->heading('Source System')
                                ->grow(true),

                            Section::make([
                                TextEntry::make('targetSystem.name')->label('System Name'),
                                TextEntry::make('targetSystem.contact.department_name')->label('Department')
                                    ->visible(fn($record) => $record->targetSystem?->contact !== null),
                                TextEntry::make('targetSystem.contact.emails_list')
                                    ->label('Contact Emails')
                                    ->visible(fn($record) => $record->targetSystem?->contact?->emails->isNotEmpty()),
                            ])
                                ->label('Target System')
                                ->heading('Target System')
                                ->grow(true),
                        ])
                            ->from('md')
                    ])
                    ->collapsed(true),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->markdown()
                            ->label(' '),
                    ])
                    ->collapsed(true),
            ]);
    }
}
