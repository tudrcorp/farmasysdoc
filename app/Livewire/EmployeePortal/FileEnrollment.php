<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Services\Hr\EmployeeFileMediaStore;
use App\Services\Hr\EmployeePortalAccess;
use App\Support\Hr\EmployeeFileTerms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.employee-portal')]
#[Title('Firma y huella')]
class FileEnrollment extends Component
{
    use WithFileUploads;

    public string $step = 'intro';

    public ?string $signaturePreviewUrl = null;

    public ?string $fingerprintPreviewUrl = null;

    public mixed $signatureUpload = null;

    public mixed $fingerprintUpload = null;

    public bool $acceptedFileTerms = false;

    public bool $showChangeNotice = false;

    public function mount(): void
    {
        $this->acceptedFileTerms = $this->employee()->hasAcceptedFileTerms();
        $this->refreshPreviews();

        if ($this->employee()->hasCompleteEmployeeFile()) {
            $this->step = 'view';
        }
    }

    public function startEnrollment(): void
    {
        if (! $this->assertFileUnlocked()) {
            return;
        }

        $this->validate([
            'acceptedFileTerms' => ['accepted'],
        ], [
            'acceptedFileTerms.accepted' => 'Debes aceptar los términos y condiciones para guardar tu firma y huella.',
        ]);

        $this->employee()->acceptFileTerms();
        $this->goTo('signature');
    }

    public function requestFileChange(): void
    {
        $this->showChangeNotice = true;
    }

    public function closeChangeNotice(): void
    {
        $this->showChangeNotice = false;
    }

    public function goTo(string $step): void
    {
        if ($step === 'review') {
            $step = 'done';
        }

        if (! in_array($step, ['intro', 'signature', 'fingerprint', 'done', 'view'], true)) {
            return;
        }

        if (in_array($step, ['intro', 'signature', 'fingerprint'], true) && ! $this->assertFileUnlocked()) {
            return;
        }

        if (! in_array($step, ['intro', 'view'], true) && ! $this->employee()->hasAcceptedFileTerms()) {
            $this->addError('acceptedFileTerms', 'Debes aceptar los términos y condiciones para continuar.');
            $this->step = 'intro';

            return;
        }

        $this->resetErrorBag();
        $this->step = $step;
    }

    public function keepExistingSignature(): void
    {
        if (! $this->assertFileUnlocked() || ! $this->employee()->hasSignature()) {
            return;
        }

        $this->goTo('fingerprint');
    }

    public function keepExistingFingerprint(): void
    {
        if (! $this->assertFileUnlocked() || ! $this->employee()->hasFingerprint()) {
            return;
        }

        $this->goTo('done');
    }

    public function saveSignatureStroke(string $dataUrl): void
    {
        if (! $this->assertFileUnlocked() || ! $this->assertFileTermsAccepted()) {
            return;
        }

        try {
            app(EmployeeFileMediaStore::class)->storeSignatureFromDataUrl($this->employee(), $dataUrl);
        } catch (ValidationException $exception) {
            $this->setImageError($exception);

            return;
        }

        $this->refreshPreviews();
        $this->goTo('fingerprint');
    }

    public function saveSignatureUpload(): void
    {
        if (! $this->assertFileUnlocked() || ! $this->assertFileTermsAccepted()) {
            return;
        }

        $this->validate([
            'signatureUpload' => ['required', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ], [
            'signatureUpload.required' => 'Elige una imagen de tu firma.',
            'signatureUpload.image' => 'El archivo debe ser una imagen.',
            'signatureUpload.max' => 'La imagen no puede superar 2 MB.',
            'signatureUpload.mimes' => 'Usa PNG, JPG o WEBP.',
        ]);

        if (! $this->signatureUpload instanceof UploadedFile) {
            $this->addError('signatureUpload', 'Elige una imagen de tu firma.');

            return;
        }

        try {
            app(EmployeeFileMediaStore::class)->storeSignatureUpload($this->employee(), $this->signatureUpload);
        } catch (ValidationException $exception) {
            $this->setImageError($exception, 'signatureUpload');

            return;
        }

        $this->signatureUpload = null;
        $this->refreshPreviews();
        $this->goTo('fingerprint');
    }

    public function saveFingerprintCapture(string $dataUrl): void
    {
        if (! $this->assertFileUnlocked() || ! $this->assertFileTermsAccepted()) {
            return;
        }

        try {
            app(EmployeeFileMediaStore::class)->storeFingerprintFromDataUrl($this->employee(), $dataUrl);
        } catch (ValidationException $exception) {
            $this->setImageError($exception);

            return;
        }

        $this->refreshPreviews();
        $this->goTo('done');
    }

    public function saveFingerprintUpload(): void
    {
        if (! $this->assertFileUnlocked() || ! $this->assertFileTermsAccepted()) {
            return;
        }

        $this->validate([
            'fingerprintUpload' => ['required', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ], [
            'fingerprintUpload.required' => 'Elige una foto de tu huella.',
            'fingerprintUpload.image' => 'El archivo debe ser una imagen.',
            'fingerprintUpload.max' => 'La imagen no puede superar 2 MB.',
            'fingerprintUpload.mimes' => 'Usa PNG, JPG o WEBP.',
        ]);

        if (! $this->fingerprintUpload instanceof UploadedFile) {
            $this->addError('fingerprintUpload', 'Elige una foto de tu huella.');

            return;
        }

        try {
            app(EmployeeFileMediaStore::class)->storeFingerprintUpload($this->employee(), $this->fingerprintUpload);
        } catch (ValidationException $exception) {
            $this->setImageError($exception, 'fingerprintUpload');

            return;
        }

        $this->fingerprintUpload = null;
        $this->refreshPreviews();
        $this->goTo('done');
    }

    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        return view('livewire.employee-portal.file-enrollment', [
            'employee' => $employee,
            'fileTermsTitle' => EmployeeFileTerms::title(),
            'fileTermsParagraphs' => EmployeeFileTerms::paragraphs(),
            'fileTermsAcceptanceLabel' => EmployeeFileTerms::acceptanceLabel(),
        ]);
    }

    private function assertFileUnlocked(): bool
    {
        if (! $this->employee()->hasCompleteEmployeeFile()) {
            return true;
        }

        $this->step = 'view';
        $this->showChangeNotice = true;

        return false;
    }

    private function assertFileTermsAccepted(): bool
    {
        if ($this->employee()->hasAcceptedFileTerms()) {
            return true;
        }

        $this->addError('acceptedFileTerms', 'Debes aceptar los términos y condiciones para guardar tu firma y huella.');
        $this->step = 'intro';

        return false;
    }

    private function refreshPreviews(): void
    {
        $employee = $this->employee()->fresh();
        abort_unless($employee instanceof Employee, 403);

        $this->signaturePreviewUrl = $employee->signatureUrl();
        $this->fingerprintPreviewUrl = $employee->fingerprintUrl();
    }

    private function setImageError(ValidationException $exception, string $field = 'image'): void
    {
        $message = collect($exception->errors())->flatten()->first();

        $this->addError($field, is_string($message) ? $message : 'No se pudo guardar la imagen.');
    }
}
