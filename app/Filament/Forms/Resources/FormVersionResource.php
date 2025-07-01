<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Filament\Forms\Resources\FormVersionResource\Pages\BuildFormVersion;
use App\Models\FormBuilding\FormVersion;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Auth;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(3)
                    ->schema([
                        Select::make('form_id')
                            ->relationship('form', 'form_id_title')
                            ->required()
                            ->reactive()
                            ->preload()
                            ->searchable()
                            ->columnSpan(2)
                            ->default(request()->query('form_id_title')),
                        Select::make('status')
                            ->options(function () {
                                return FormVersion::getStatusOptions();
                            })
                            ->default('draft')
                            ->disabled()
                            ->columnSpan(1)
                            ->required(),
                        Section::make('Form Properties')
                            ->collapsible()
                            ->columns(1)
                            ->compact()
                            ->columnSpanFull()
                            ->schema([
                                Select::make('form_developer_id')
                                    ->label('Form Developer')
                                    ->relationship(
                                        'formDeveloper',
                                        'name',
                                        fn($query) => $query->whereHas('roles', fn($q) => $q->where('name', 'form-developer'))
                                    )
                                    ->default(Auth::id())
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),
                                Select::make('form_data_sources')
                                    ->multiple()
                                    ->preload()
                                    ->columnSpan(2)
                                    ->relationship('formDataSources', 'name'),
                                TextInput::make('footer')
                                    ->columnSpanFull(),
                                Textarea::make('comments')
                                    ->columnSpanFull()
                                    ->maxLength(500),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form.form_id_title')
                    ->label('Form')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('version_number')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn($state) => FormVersion::getStatusColour($state))
                    ->getStateUsing(fn($record) => $record->getFormattedStatusName()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn($record) => $record->status === 'published')
                    ->action(function ($record) {
                        $record->update(['status' => 'archived']);
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->tooltip('Archive this form version'),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'index' => Pages\ListFormVersions::route('/'),
            'create' => Pages\CreateFormVersion::route('/create'),
            'edit' => Pages\EditFormVersion::route('/{record}/edit'),
            'view' => Pages\ViewFormVersion::route('/{record}'),
            'build' => BuildFormVersion::route('/{record}/build'),
        ];
    }
}
