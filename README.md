## About Klamm

Klamm is intended to be a data-capture tool for use by Sector Priorities, it should:

-   Allow the Forms Modernization team to inventory its forms and what data they container.
-   Allow the Business Rules Engine team to contribute their discoveries of data sources.
-   Allow the FODIG teams to track their data-repository discoveries.

Klamm is built using [FilamentPHP](https://filamentphp.com) which is itself built atop [Laravel](https://laravel.com).

It also uses Blueprint to speed up the generation of model boilerplate.

## Setup Instructions

Requirements:

-   [PHP](https://www.php.net/manual/en/install.php)
-   [Composer](https://getcomposer.org/doc/00-intro.md)

Clone the repository:

```
git clone https://github.com/bcgov/klamm.git
```

Navigate into the repository:

```
cd KLAMM
```

---

### For Quick setup

Install with Docker using Sail:

If you are using Windows:

```
# Install WSL
wsl --install

# Set the default version to WSL 2
wsl --set-default-version 2

# Download Docker Desktop https://www.docker.com/products/docker-desktop/
# Make sure WSL 2 is enabled in Docker Desktop -> Settings -> General -> Use WSL 2 Based Engine
# And make sure resources is enabled in Docker Desktop -> Settings -> WSL Integration -> Ubuntu (or your specific distro)

# Set your default WSL version
wsl -s Ubuntu

# Optionally if you installed a different distro or maintain others, you can see your installed distros and default distro with this command
wsl --list

# Now make sure Docker Desktop is running, and open WSL
wsl

# Now navigate to your repo (in my case, it is the following)
cd /mnt/c/Users/{username}/Documents/GitHub/Klamm
```

The rest of the commands will be the same for WSL, Mac and Linux

```
# Install composer packages (which includes sail)
composer install

# We are using sail which provides an easy to use interface with docker for running our artisan commands
# You can read more about Sail https://laravel.com/docs/11.x/sail

# Now you can run all your php commands with ./vendor/bin/sail instead
# I recommend creating an alias so you can use sail instead of typing ./vendor/bin/sail every time
# Depending on where your bash configs are located you will have to do one of the following:
nano ~/.bashrc
# Or
nano ~/.zshrc

# Then add this line, save the file, and restart your terminal
alias sail='./vendor/bin/sail'

# To start the application run
./vendor/bin/sail up -d
# Or if you have setup the alias
sail up -d

# To stop the application run
./vendor/bin/sail down
# Or if you have setup the alias
sail down
```

Create the APP_KEY, run the migrations and seed the data:

```
# Create the .env file
cp .env.example .env

# Create the APP_KEY
sail artisan key:generate

# Run the migrations
sail artisan migrate

# Generate the permissions policies based on the models
sail artisan permissions:sync

# Seed the initial data
sail artisan db:seed

# Seed the users data (local only, or create a user in the steps below)
sail artisan db:seed --class=UserSeeder

# Optionally seed the Momus Data
sail artisan db:seed --class=MomusSeeder

# Optionally seed the BRE Data
# To seed a starting template without policy rules/fields run:
sail artisan db:seed --class=BREBasicSeeder

# Otherwise, to seed with SDPR policy rules/fields run:
sail artisan db:seed --class=BRESeeder
```

If you would like to use a GUI for your Postgres database I reccomend TablePlus. In the connection details you will want to put the following in the connection details:

```
Host: localhost
Port: 5432
User: laravel
Password: secret
Database: laravel
```

You can add a user account for yourself in the user seeder or use the command:

```
sail artisan create-user
```

You will need to assign a role to your account to access the different panels.
I suggest assigning the admin role to your user as you will get full access to all the panels.

```
sail artisan assign-user-role
```

The page should now be accessible at

`http://localhost/`

---

## General Developer Workflows for Adding New Content

```
# Create a model and migration
sail artisan make:model Test -m

# The Model should be singular and it will create a plural table (in this case tests)

# Edit the migration to include the the columns
Schema::create('tests', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});

# Make sure to include a rollback
Schema::dropIfExists('tests');

# Run the migration
sail artisan migrate

# Optionally create a seeder
sail artisan make:seeder TestSeeder

# Fill the seeder
Test::create(['name' => 'Name One']);
Test::create(['name' => 'Name Two']);

# Run the specific seeder
sail artisan db:seed --class=TestSeeder

# Create a filament resource from the model
# Make sure to specify which panel you want the resource to be in
sail artisan make:filament-resource Test --view

# In your new TestResource.php define the $form and $table
# $form is used for editing and creating records
return $form
    ->schema([
        Forms\Components\TextInput::make('name')
            ->required(),
    ]);

# $table is used for listing and viewing the records
return $table
    ->columns([
        Tables\Columns\TextColumn::make('name')
            ->searchable()
            ->sortable(),
    ]);

# You should see the page name on the left menu of whichever dashboard you added it to

# We use policies to control who can do what to our records
# The policies are based on the permissions attached to each role
# Each model has view, view-any, create, update, and delete permissions
# The filament pages will restrict content based on the model specific policy
# Generate the policy:
sail artisan make:policy TestPolicy --model=Test

# Edit the policy for each permission like such:
public function create(User $user): bool
{
    return $user->can('create Form');
}

# If you add a new table/model you will need to generate a new set of permissions
# Please update the view-only and edit access in the PermissionsSeeder and to generate the new permissions run
sail artisan permissions:sync

```

### Enable Local Exports

Certain views offer the ability to export a list of entries in the form of a CSV or Excel File. To enable this in local development run

```
sail artisan queue:work
```

### Using the mail provider
Mailhog is setup locally so you can test email features. Make sure the queue is running and features such as email password reset will work locally.

You can go to `http://localhost:8025` to see the Mailhog GUI which will capture emails being sent.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
