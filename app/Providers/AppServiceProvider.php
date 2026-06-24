<?php

namespace App\Providers;

use App\Http\Responses\Auth\LogoutResponse;
use App\Listeners\RecordAuthenticationAudit;
use App\Models\PurchaseItem;
use App\Observers\AuditModelObserver;
use App\Observers\PurchaseItemObserver;
use App\Support\Filesystem\ResilientFilesystem;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as FilamentLogoutResponseContract;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LogoutResponse as FortifyLogoutResponseContract;
use Livewire\Mechanisms\HandleComponents\Checksum;
use ReflectionClass;
use ReflectionException;

use function Livewire\on;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('files', fn (): ResilientFilesystem => new ResilientFilesystem);
        $this->app->singleton(
            FilamentLogoutResponseContract::class,
            LogoutResponse::class,
        );
        $this->app->singleton(FortifyLogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PurchaseItem::observe(PurchaseItemObserver::class);

        $authAudit = app(RecordAuthenticationAudit::class);
        Event::listen(Login::class, [$authAudit, 'handleLogin']);
        Event::listen(Logout::class, [$authAudit, 'handleLogout']);
        Event::listen(Failed::class, [$authAudit, 'handleFailed']);

        $auditObserver = app(AuditModelObserver::class);
        foreach (config('audit.models', []) as $modelClass) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass::observe($auditObserver);
        }

        $this->configureDefaults();
        $this->configureLivewireReleaseToken();
        $this->ensureLivewireTemporaryUploadDirectoriesExist();
        $this->disableLivewireChecksumFailureThrottling();
        $this->observeLivewireChecksumFailures();

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_LAYOUT_START,
            fn (): string => view('filament.farmaadmin.components.simple-auth-ambient')->render(),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Carbon::setLocale(config('app.locale'));

        // El checksum de Livewire usa json_encode() sobre snapshots con importes decimales.
        // En despliegues con múltiples nodos, diferencias de serialize_precision causan
        // checksums distintos para el mismo snapshot.
        ini_set('serialize_precision', '-1');

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

    protected function configureLivewireReleaseToken(): void
    {
        $configured = env('LIVEWIRE_RELEASE_TOKEN');

        if (is_string($configured) && $configured !== '') {
            config(['livewire.release_token' => $configured]);

            return;
        }

        $lockFile = base_path('composer.lock');

        if (! is_file($lockFile)) {
            return;
        }

        config([
            'livewire.release_token' => substr(
                hash('sha256', (string) filemtime($lockFile).':'.(string) filesize($lockFile)),
                0,
                16,
            ),
        ]);
    }

    /**
     * Livewire escribe en `livewire-tmp/` bajo el root del disco; si el directorio no existe, falla filesize/mime.
     */
    protected function ensureLivewireTemporaryUploadDirectoriesExist(): void
    {
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        foreach ([storage_path('app/public'), storage_path('app/private')] as $root) {
            File::ensureDirectoryExists($root.'/'.$directory);
        }
    }

    /**
     * Livewire bloquea la IP con HTTP 429 tras varios checksum inválidos; en despliegues
     * con muchas pestañas o balanceo sin sesión compartida eso penaliza usuarios legítimos.
     */
    protected function disableLivewireChecksumFailureThrottling(): void
    {
        try {
            $reflection = new ReflectionClass(Checksum::class);
            $property = $reflection->getProperty('maxFailures');
            $property->setAccessible(true);
            $property->setValue(null, PHP_INT_MAX);
        } catch (ReflectionException) {
            // Si cambia el paquete Livewire, ignorar en lugar de tumbar el arranque.
        }
    }

    protected function observeLivewireChecksumFailures(): void
    {
        on('checksum.fail', function (string $receivedChecksum, string $computedChecksum, array $snapshot): void {
            $memo = $snapshot['memo'] ?? [];
            $request = request();

            logger()->error('Livewire checksum mismatch details.', [
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'host' => gethostname(),
                'app_key_fingerprint' => hash('sha256', (string) config('app.key')),
                'php_version' => PHP_VERSION,
                'serialize_precision' => ini_get('serialize_precision'),
                'precision' => ini_get('precision'),
                'received_checksum_prefix' => substr($receivedChecksum, 0, 12),
                'computed_checksum_prefix' => substr($computedChecksum, 0, 12),
                'component_name' => $memo['name'] ?? null,
                'component_id' => $memo['id'] ?? null,
            ]);
        });
    }
}
