<?php

namespace App\Filament\Reports\Resources;

use App\Filament\Reports\Resources\ReportEntryResource\Pages;
use App\Models\ReportEntry;
use App\Models\ReportLabelSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Exports\ReportEntryExporter;
use Filament\Forms\Get;
use App\Filament\Imports\ReportEntryImporter;
use Filament\Tables\Actions\ImportAction;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Actions\ExportBulkAction;

class ReportEntryResource extends Resource
{
    protected static ?string $model = ReportEntry::class;

    protected static ?string $label = 'Report Dictionary';

    protected static ?string $navigationLabel = 'Report Label Dictionary';

    public static function getNavigationIcon(): string
    {
        return asset('svg/report-dictionary-logo-light.svg');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('business_area_id')
                    ->relationship('reportBusinessArea', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Business Area')
                    ->columnSpanFull(),
                Forms\Components\Select::make('report_id')
                    ->relationship('report', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Report Name')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('existing_label')
                    ->required()
                    ->maxLength(255)
                    ->label('Existing Label')
                    ->columnSpanFull(),
                Forms\Components\Select::make('report_dictionary_label_id')
                    ->relationship('reportDictionaryLabel', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Dictionary Label')
                    ->columnSpanFull(),
                Forms\Components\Select::make('label_source_id')
                    ->relationship('labelSource', 'name')
                    ->label('Label Source')
                    ->reactive()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('data_field')
                    ->columnSpanFull()
                    ->label('Source Data Field')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('icm_data_field_path')
                    ->label('ICM Data Field Path')
                    ->visible(function (Get $get) {
                        $labelSourceId = $get('label_source_id');
                        if ($labelSourceId) {
                            $labelSourceName = ReportLabelSource::find($labelSourceId)?->name;
                            return $labelSourceName === 'ICM';
                        }

                        return false;
                    })
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_matching_rate')
                    ->label('Label Match Rating')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reportDictionaryLabel.name')
                    ->searchable()
                    ->label('Dictionary Label'),
                Tables\Columns\TextColumn::make('reportBusinessArea.name')
                    ->searchable()
                    ->label('Business Area'),
                Tables\Columns\TextColumn::make('report.name')
                    ->searchable()
                    ->label('Report Name'),
                Tables\Columns\TextColumn::make('existing_label')
                    ->searchable()
                    ->label('Existing Label'),
                Tables\Columns\TextColumn::make('labelSource.name')
                    ->searchable()
                    ->label('Label Source'),
                Tables\Columns\TextColumn::make('data_field')
                    ->searchable()
                    ->label('Source Data Field'),
                Tables\Columns\TextColumn::make('icm_data_field_path')
                    ->label('ICM Data Field Path')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->labelSource && $record->labelSource->name === 'ICM') {
                            return $state ?? 'N/A';
                        }
                        return 'N/A';
                    }),
                Tables\Columns\TextColumn::make('data_matching_rate')
                    ->label('Label Match Rating')
                    ->badge()
                    ->colors([
                        'success' => static fn($state): bool => $state === 'low',
                        'warning' => static fn($state): bool => $state === 'medium',
                        'danger' => static fn($state): bool => $state === 'high',
                    ]),
                Tables\Columns\TextColumn::make('lastUpdatedBy.name')
                    ->label('Last Updated By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_area_id')
                    ->relationship('reportBusinessArea', 'name')
                    ->preload()
                    ->label('Business Area'),
                Tables\Filters\SelectFilter::make('label_source_id')
                    ->relationship('labelSource', 'name')
                    ->preload()
                    ->label('Label Source'),
                Tables\Filters\SelectFilter::make('data_matching_rate')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->label('Data Matching Rate'),
                Tables\Filters\SelectFilter::make('report_id')
                    ->relationship('report', 'name')
                    ->preload()
                    ->label('Report'),
                Tables\Filters\SelectFilter::make('last_updated_by')
                    ->relationship('lastUpdatedBy', 'name')
                    ->preload()
                    ->label('Last Updated By'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->color(Color::hex('#2D2D2D'))
                        ->icon('heroicon-o-trash')
                        ->label('Delete'),
                    ExportBulkAction::make()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->label('Download Report Labels')
                        ->color('primary')
                        ->exporter(ReportEntryExporter::class)
                ])->visible(fn() => Gate::allows('reports') || Gate::allows('admin')),
            ])
            ->headerActions([
                ImportAction::make('Import CSV')
                    ->icon('heroicon-o-folder-arrow-down')
                    ->outlined()
                    ->label('Import Label(s)')
                    ->importer(ReportEntryImporter::class)
                    ->visible(fn() => Gate::allows('reports') || Gate::allows('admin')),
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportEntries::route('/'),
            'create' => Pages\CreateReportEntry::route('/create'),
            'view' => Pages\ViewReportEntry::route('/{record}'),
            'edit' => Pages\EditReportEntry::route('/{record}/edit'),
        ];
    }
}
