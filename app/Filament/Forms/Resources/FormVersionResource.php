<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Filament\Forms\Resources\FormVersionResource\RelationManagers;
use App\Models\FormVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                    ->relationship('form', 'form_title')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'testing' => 'Testing',
                        'archived' => 'Archived',
                        'published' => 'Published',
                    ])
                    ->required(),
                Forms\Components\Fieldset::make('Requester Information')
                    ->schema([
                        Forms\Components\TextInput::make('form_requester_name')
                            ->label('Name'),
                        Forms\Components\TextInput::make('form_requester_email')
                            ->label('Email'),
                    ])
                    ->label('Requester Information'),
                Forms\Components\Fieldset::make('Approver Information')
                    ->schema([
                        Forms\Components\TextInput::make('form_approver_name')
                            ->label('Name'),
                        Forms\Components\TextInput::make('form_approver_email')
                            ->label('Email'),
                    ])
                    ->label('Approver Information'),
                Forms\Components\Fieldset::make('Updater Information')
                    ->schema([
                        Forms\Components\TextInput::make('form_updater_name')
                            ->label('Name'),
                        Forms\Components\TextInput::make('form_updater_email')
                            ->label('Email'),
                    ])
                    ->label('Updater Information'),
                Forms\Components\TextArea::make('comments')
                    ->label('Comments')
                    ->maxLength(500),
                Forms\Components\Select::make('deployed_to')
                    ->label('Deployed To')
                    ->options([
                        'dev' => 'Development',
                        'test' => 'Testing',
                        'prod' => 'Production',
                    ])
                    ->nullable()
                    ->afterStateUpdated(fn(callable $set) => $set('deployed_at', now())),
                Forms\Components\DateTimePicker::make('deployed_at')
                    ->label('Deployment Date'),

                Forms\Components\Repeater::make('form_instance_fields')
                    ->label('Form Fields')
                    ->relationship('formInstanceFields')
                    ->columnSpan(2)
                    ->reorderable(true)
                    ->itemLabel(
                        fn($state) => $state['label'] ?? null
                    )
                    ->schema([
                        Forms\Components\Select::make('form_field_id')
                            ->label('Form Field')
                            ->relationship('formField', 'label')
                            ->required(),
                        Forms\Components\TextInput::make('label')
                            ->label("Custom Label"),
                        Forms\Components\TextInput::make('data_binding'),
                        Forms\Components\TextArea::make('validation'),
                        Forms\Components\TextArea::make('conditional_logic'),
                        Forms\Components\TextArea::make('styles'),
                    ])->collapsed(),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('Generate Form Template')
                        ->action(function (Forms\Get $get, Forms\Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = \App\Helpers\FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        }),
                    Forms\Components\Actions\Action::make('Preview Form Template')
                        ->url(function (Forms\Get $get) {
                            $jsonTemplate = $get('generated_text');
                            $encodedJson = base64_encode($jsonTemplate);
                            return route('forms.rendered_forms.preview', ['json' => $encodedJson]);
                        })
                        ->openUrlInNewTab()
                        ->disabled(fn(Forms\Get $get) => empty($get('generated_text'))),
                ]),
                Forms\Components\TextArea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_title')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_to')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('form_requester_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_requester_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_developer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('form_approver_email')
                    ->searchable(),
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
                Tables\Actions\EditAction::make(),
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
