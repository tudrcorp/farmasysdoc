<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationAudit;
use App\Models\PurchaseItem;
use App\Observers\AuditModelObserver;
use App\Observers\PurchaseItemObserver;
use App\Support\Filesystem\ResilientFilesystem;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use ReflectionClass;
use ReflectionException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('files', fn (): ResilientFilesystem => new ResilientFilesystem);
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
        $this->ensureLivewireTemporaryUploadDirectoriesExist();
        $this->disableLivewireChecksumFailureThrottling();
        $this->skipGlobalRequestMutatorsForLivewireEndpoints();

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

    /**
     * Livewire registra skipWhen usando la cabecera X-Livewire; si un proxy la quita,
     * ConvertEmptyStringsToNull / TrimStrings alteran el JSON del snapshot y el checksum falla
     * (CorruptComponentPayloadException).
     *
     * Estos middlewares van en el stack global antes del router: no confiar solo en el nombre
     * de ruta ni en str_starts_with(path) (falla con subdirectorio o reescrituras). El POST de
     * /livewire-…/update siempre trae un array "components" en la raíz del JSON.
     */
    protected function skipGlobalRequestMutatorsForLivewireEndpoints(): void
    {
        $livewirePathPrefix = ltrim(EndpointResolver::prefix(), '/');

        $skip = static function (Request $request) use ($livewirePathPrefix): bool {
            if ($request->hasHeader('X-Livewire')) {
                return true;
            }

            $path = $request->path();
            if ($path !== '' && str_contains($path, $livewirePathPrefix.'/')) {
                return true;
            }

            if ($request->isMethod('POST') && $request->isJson()) {
                $payload = $request->json()->all();
                if (isset($payload['components']) && is_array($payload['components'])) {
                    return true;
                }
            }

            return false;
        };

        ConvertEmptyStringsToNull::skipWhen($skip);
        TrimStrings::skipWhen($skip);
    }
}
