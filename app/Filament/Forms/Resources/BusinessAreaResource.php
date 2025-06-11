<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\BusinessAreaResource\Pages;
use App\Models\BusinessArea;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class BusinessAreaResource extends Resource
{
    protected static ?string $model = BusinessArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Business Area or Program';
    protected static ?string $title = 'Business Area or Program';
    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('short_name'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Relationships')
                    ->schema([
                        Forms\Components\Select::make('ministries')
                            ->multiple()
                            ->preload()
                            ->relationship('ministries', 'name'),

                        Forms\Components\Select::make('users')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->relationship('users', 'name')
                            ->label('Contacts')
                            ->getOptionLabelFromRecordUsing(fn(User $user) => "{$user->name} ({$user->email})")
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) => $action
                                    ->visible(fn() => Gate::allows('admin'))
                            )
                            ->createOptionForm(
                                [
                                    Forms\Components\TextInput::make('name')
                                        ->required(),
                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->required(),
                                ]
                            )->createOptionUsing(function (array $data) {
                                $password = \Illuminate\Support\Str::password(20);

                                $user = User::create([
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'password' => bcrypt($password),
                                    'created_via_business_area' => true,
                                ]);
                                $user->assignRole('user');

                                $user->notify(new \App\Notifications\FormAccountCreatedNotification());

                                return $user->id;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->query(
                BusinessArea::query()
                    ->with([
                        'users',
                        'forms.formTags',
                        'forms.formVersions',
                        'ministries',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('formCount')
                    ->label('Total Forms')
                    ->toggleable()
                    ->getStateUsing(fn($record) => $record->getFormCount($record->forms)),
                Tables\Columns\TextColumn::make('forms_migration2025')
                    ->label('Forms Migration 2025')
                    ->toggleable()
                    ->visible(Gate::allows('admin'))
                    ->getStateUsing(fn($record) => $record->countFormsMigration2025($record->forms)),
                Tables\Columns\TextColumn::make('forms_completed')
                    ->label('Forms Completed')
                    ->toggleable()
                    ->visible(Gate::allows('admin'))
                    ->getStateUsing(fn($record) => $record->countFormsCompleted($record->forms)),
                Tables\Columns\TextColumn::make('forms_in_progress')
                    ->label('Forms In Progress')
                    ->toggleable()
                    ->visible(Gate::allows('admin'))
                    ->getStateUsing(fn($record) => $record->countFormsInProgress($record->forms)),
                Tables\Columns\TextColumn::make('forms_to_be_done')
                    ->label('Forms To Be Done')
                    ->toggleable()
                    ->visible(Gate::allows('admin'))
                    ->getStateUsing(fn($record) => $record->countFormsToBeDone($record->forms)),
                Tables\Columns\TextColumn::make('short_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ministries.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('users')
                    ->label('Contacts')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->formatStateUsing(function ($state) {
                        $state = $state->name . ' (' . $state->email . ')';
                        return $state;
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);

        // If showAllColumns query present, notify user about column visibility
        if (request()->query('showAllColumns')) {
            \Filament\Notifications\Notification::make()
                ->title('Tip: Show More Columns')
                ->body('Use the column selector (top right of the table) to toggle Migration 2025 status columns.')
                ->icon('heroicon-o-information-circle')
                ->success()
                ->send();
        }
        return $table;
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
            'index' => Pages\ListBusinessAreas::route('/'),
            'create' => Pages\CreateBusinessArea::route('/create'),
            'edit' => Pages\EditBusinessArea::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Business Area or Program';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Business Areas or Programs';
    }
}
