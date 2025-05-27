<?php

namespace App\Filament\Fodig\Resources\BoundarySystemInterfaceResource\Pages;

use App\Filament\Fodig\Resources\BoundarySystemInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Grid as InfolistGrid;
use App\Models\BoundarySystemInterface;
use Illuminate\Support\HtmlString;

class ViewBoundarySystemInterface extends ViewRecord
{
    protected static string $resource = BoundarySystemInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Interface Details')
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('name')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Name'))),
                                TextEntry::make('short_description')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Short Description'))),
                                TextEntry::make('transaction_frequency')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Transaction Frequency')))
                                    ->formatStateUsing(fn($state) => BoundarySystemInterface::getTransactionFrequencyOptions()[$state] ?? $state),
                                TextEntry::make('transaction_schedule')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Transaction Schedule'))),
                                TextEntry::make('complexity')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Complexity')))
                                    ->formatStateUsing(fn($state) => BoundarySystemInterface::getComplexityOptions()[$state] ?? $state),
                                TextEntry::make('integration_type')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Integration Type')))
                                    ->formatStateUsing(fn($state) => BoundarySystemInterface::getIntegrationTypeOptions()[$state] ?? $state),
                                TextEntry::make('mode_of_transfer')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Mode of Transfer')))
                                    ->formatStateUsing(fn($state) => BoundarySystemInterface::getModeOfTransferOptions()[$state] ?? $state),
                                TextEntry::make('protocol')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Protocol')))
                                    ->formatStateUsing(fn($state) => BoundarySystemInterface::getProtocolOptions()[$state] ?? $state),
                                TextEntry::make('tags')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Tags')))
                                    ->bulleted()
                                    ->listWithLineBreaks()
                                    ->getStateUsing(fn($record) => $record->tags->pluck('name')->toArray()),
                            ]),
                    ])
                    ->collapsed(false),

                Section::make('Data Format and Security')
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('data_format')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Data Formats')))
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->getStateUsing(
                                        fn($record) => collect($record->data_format)
                                            ->map(fn($val) => BoundarySystemInterface::getDataFormatOptions()[$val] ?? $val)
                                            ->toArray()
                                    ),
                                TextEntry::make('security')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Security')))
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->getStateUsing(
                                        fn($record) => collect($record->security)
                                            ->map(fn($val) => BoundarySystemInterface::getSecurityOptions()[$val] ?? $val)
                                            ->toArray()
                                    ),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Systems')
                    ->schema([
                        Split::make([
                            Section::make([
                                InfolistGrid::make(1)
                                    ->schema([
                                        TextEntry::make('sourceSystem.name')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('System Name'))),
                                        TextEntry::make('sourceSystem.contact.department_name')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('Department')))
                                            ->visible(fn($record) => $record->sourceSystem?->contact !== null),
                                        TextEntry::make('sourceSystem.contact.emails_list')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('Contact Emails')))
                                            ->visible(fn($record) => $record->sourceSystem?->contact?->emails->isNotEmpty()),
                                    ]),
                            ])
                                ->label('Source System')
                                ->heading('Source System')
                                ->grow(true),

                            Section::make([
                                InfolistGrid::make(1)
                                    ->schema([
                                        TextEntry::make('targetSystem.name')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('System Name'))),
                                        TextEntry::make('targetSystem.contact.department_name')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('Department')))
                                            ->visible(fn($record) => $record->targetSystem?->contact !== null),
                                        TextEntry::make('targetSystem.contact.emails_list')
                                            ->columnSpanFull()
                                            ->label(new HtmlString(self::formatLabel('Contact Emails')))
                                            ->visible(fn($record) => $record->targetSystem?->contact?->emails->isNotEmpty()),
                                    ]),
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
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('description')
                                    ->columnSpanFull()
                                    ->markdown()
                                    ->label(new HtmlString(self::formatLabel('Description'))),
                            ]),
                    ])
                    ->collapsed(true),
            ]);
    }
}
