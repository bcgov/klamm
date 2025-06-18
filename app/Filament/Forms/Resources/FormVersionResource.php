<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Models\FormVersion;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('form_id')
                    ->relationship('form', 'form_id_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->searchable()
                    ->default(request()->query('form_id_title'))
                    ->getSearchResultsUsing(function (string $search) {
                        return DB::table('forms')
                            ->where('form_id_title', 'like', "%{$search}%")
                            ->select('id', 'form_id_title')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($row) => [$row->id => $row->form_id_title])
                            ->toArray();
                    }),
                Forms\Components\Select::make('status')
                    ->options(function () {
                        return FormVersion::getStatusOptions();
                    })
                    ->default('draft')
                    ->disabled()
                    ->required(),
                Forms\Components\Section::make('Form Properties')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->compact()
                    ->schema([
                        Forms\Components\Select::make('form_developer_id')
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
                        Forms\Components\TextInput::make('footer')
                            ->columnSpan(2),
                        Forms\Components\Textarea::make('comments')
                            ->columnSpanFull()
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_id_title')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->getStateUsing(fn($record) => $record->getFormattedStatusName()),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListFormVersions::route('/'),
            'create' => Pages\CreateFormVersion::route('/create'),
            'view' => Pages\ViewFormVersion::route('/{record}'),
            'edit' => Pages\EditFormVersion::route('/{record}/edit'),
        ];
    }
}
