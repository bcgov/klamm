<?php

namespace App\Filament\Home\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;

class Profile extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'My Account';
    protected static ?string $navigationLabel = 'Profile';
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static string $view = 'filament.home.pages.profile';
    protected static ?int $navigationSort = 2;

    public $name;
    public $email;
    public $current_password;
    public $new_password;
    public $new_password_confirmation;
    public $api_token;
    public $tooltips_enabled;

    public function mount()
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, '');
        }

        $this->name = $user->name;
        $this->email = $user->email;
        $this->tooltips_enabled = $user->tooltips_enabled;
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Section::make('Profile Information')
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required(),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    Actions::make([
                        Action::make('updateProfile')
                            ->label('Update Profile')
                            ->action('updateProfile')
                    ])
                ]),
            Section::make('User Preferences')
                ->schema([
                    Toggle::make('tooltips_enabled')
                        ->label('Enable Tooltips')
                        ->helperText('Show helpful tooltips throughout the application')
                        ->live(),
                    Actions::make([
                        Action::make('updatePreferences')
                            ->label('Update Preferences')
                            ->action('updatePreferences')
                    ])
                ]),
            Section::make('Update Password')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Current Password')
                        ->password()
                        ->required(),
                    TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required(),
                    TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->same('new_password')
                        ->required(),
                    Actions::make([
                        Action::make('updatePassword')
                            ->label('Update Password')
                            ->action('updatePassword')
                    ])
                ]),
        ];

        if (optional(Auth::user())->hasRole('admin')) {
            $schema[] = Section::make('API Token Management')
                ->schema([
                    TextInput::make('api_token')
                        ->label('API Token')
                        ->disabled()
                        ->default(fn() => session('api_token_plaintext')),
                    Actions::make([
                        Action::make('updateApiToken')
                            ->label('Regenerate API Token')
                            ->action('updateApiToken')
                            ->color('danger')
                    ])
                ]);
        }

        return $schema;
    }

    public function updateProfile()
    {
        $user = Auth::user();

        $validatedData = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'tooltips_enabled' => 'required|boolean',
        ]);

        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->tooltips_enabled = $validatedData['tooltips_enabled'];

        /** @var \App\Models\User $user **/
        $user->save();

        Notification::make()
            ->title("Profile updated successfully!")
            ->success()
            ->send();
    }

    public function updatePreferences()
    {
        $user = Auth::user();

        $validatedData = $this->validate([
            'tooltips_enabled' => 'required|boolean',
        ]);

        $user->tooltips_enabled = $validatedData['tooltips_enabled'];

        /** @var \App\Models\User $user **/
        $user->save();

        Notification::make()
            ->title("Preferences updated successfully!")
            ->success()
            ->send();
    }

    public function updatePassword()
    {
        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'The provided password does not match your current password.');
            return;
        }

        $validatedData = $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);

        $user->password = Hash::make($this->new_password);

        /** @var \App\Models\User $user **/
        $user->save();

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        Notification::make()
            ->title("Password updated successfully!")
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }

    public function updateApiToken()
    {
        $user = Auth::user();

        /** @var \App\Models\User $user **/

        $tokenId = $user->email;
        $user->tokens()->where('name', $tokenId)->delete();

        $abilities = $user->getRoleNames()->toArray();
        $token = $user->createToken($tokenId, $abilities);
        $tokenPlainText = $token->plainTextToken;

        $this->api_token = $tokenPlainText;

        Notification::make()
            ->title("API Token updated successfully!")
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        CustomActivitylogResource::resetConfiguration();
        CustomActivitylogResource::withColumns([
            'log_name',
            'event',
            'description',
            'subject_type',
            'properties',
            'created_at'
        ]);
        CustomActivitylogResource::withFilters([
            'date',
            'event',
            'log_name',
        ]);
        $configuredTable = CustomActivitylogResource::configureStandardTable($table);

        return $configuredTable
            ->query(
                \Spatie\Activitylog\Models\Activity::query()
                    ->where('causer_id', Auth::user()->id)
            )
            ->heading('My Activity Log')
            ->defaultSort('activity_log.created_at', 'desc');
    }
}
