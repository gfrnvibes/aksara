<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        $this->registerGoogleDriveDisk();
    }

    /**
     * Register the "google" filesystem driver used as the off-site backup destination.
     */
    protected function registerGoogleDriveDisk(): void
    {
        Storage::extend('google', function (Application $app, array $config): FilesystemAdapter {
            $client = new GoogleClient;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $adapter = new GoogleDriveAdapter(new Drive($client), $config['folder'] ?? '/');

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
