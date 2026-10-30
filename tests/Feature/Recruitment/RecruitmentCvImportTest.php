<?php

use App\Enums\RecruitmentCvExtractionStatus;
use App\Models\Employee;
use App\Models\RecruitmentCandidate;
use App\Services\Recruitment\CandidateCvImporter;
use App\Services\Recruitment\CvPhotoCropper;
use App\Support\Recruitment\CvDataNormalizer;
use App\Support\Recruitment\RecruitmentNationalId;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakeGeminiResponse(array $data): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => json_encode($data)]]]]]];
}

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    config(['services.gemini.api_key' => 'test-key']);
    Storage::disk('local')->put('recruitment/cvs/cv.pdf', '%PDF-1.4 test');
});

it('normalizes venezuelan national ids', function (?string $input, ?string $expected): void {
    expect(RecruitmentNationalId::normalize($input))->toBe($expected);
})->with([
    ['V-12.345.678', 'V-12345678'],
    ['12345678', 'V-12345678'],
    ['e 84.123.456', 'E-84123456'],
    ['C.I.: V 12345678', 'V-12345678'],
    ['123', null],
    ['no tiene', null],
    [null, null],
]);

it('flags missing and doubtful fields', function (): void {
    $result = CvDataNormalizer::normalize([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'national_id' => 'V-12.345.678',
        'phone' => '+58 414 123 4567',
        'email' => 'correo-invalido',
        'address' => null,
        'uncertain_fields' => ['last_name'],
    ]);

    expect($result['attributes']['national_id'])->toBe('V-12345678')
        ->and($result['attributes']['phone'])->toBe('04141234567')
        ->and($result['attributes']['email'])->toBeNull()
        ->and($result['flags'])->toContain('last_name', 'email', 'address')
        ->and($result['flags'])->not->toContain('phone');
});

it('creates an unreviewed candidate from the cv', function (): void {
    Http::fake(['*' => Http::response(fakeGeminiResponse([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'national_id' => '12345678',
        'phone' => '04141234567',
        'email' => 'ana@example.com',
        'address' => 'Caracas',
    ]))]);

    $candidate = app(CandidateCvImporter::class)->import('recruitment/cvs/cv.pdf', 'cv.pdf');

    expect($candidate->first_name)->toBe('Ana')
        ->and($candidate->national_id)->toBe('V-12345678')
        ->and($candidate->cv_extraction_status)->toBe(RecruitmentCvExtractionStatus::Completed)
        ->and($candidate->isReviewed())->toBeFalse();
});

it('keeps the cv when gemini fails', function (): void {
    Http::fake(['*' => Http::response([], 500)]);

    $candidate = app(CandidateCvImporter::class)->import('recruitment/cvs/cv.pdf', 'cv.pdf');

    expect($candidate->exists)->toBeTrue()
        ->and($candidate->cv_path)->toBe('recruitment/cvs/cv.pdf')
        ->and($candidate->cv_extraction_status)->toBe(RecruitmentCvExtractionStatus::Failed);
});

it('does not overwrite corrected data when reading again', function (): void {
    Http::fake(['*' => Http::response(fakeGeminiResponse(['first_name' => 'Otro', 'last_name' => 'Nombre', 'phone' => '04141234567']))]);

    $candidate = RecruitmentCandidate::factory()->create([
        'first_name' => 'Ana',
        'phone' => null,
        'cv_path' => 'recruitment/cvs/cv.pdf',
    ]);

    app(CandidateCvImporter::class)->read($candidate);

    expect($candidate->refresh()->first_name)->toBe('Ana')
        ->and($candidate->phone)->toBe('04141234567');
});

it('finds employees whatever the national id format', function (): void {
    $employee = Employee::factory()->create(['national_id' => '12345678']);

    expect(RecruitmentNationalId::findEmployee('V-12.345.678')?->id)->toBe($employee->id);
});

it('crops the photo from an image cv with a small margin', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'cv');
    imagepng(imagecreatetruecolor(1000, 1000), $source);

    $path = app(CvPhotoCropper::class)->crop($source, 'image/png', ['page' => 1, 'box_2d' => [100, 100, 300, 300]]);

    Storage::disk('public')->assertExists($path);
    expect(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBeGreaterThan(200);

    unlink($source);
});

it('returns no photo when the cv has none', function (): void {
    expect(app(CvPhotoCropper::class)->crop('/no/existe.pdf', 'application/pdf', null))->toBeNull();
});

it('keeps the extracted data and explains why the photo failed', function (): void {
    config(['recruitment.pdftoppm_binary' => 'binario-que-no-existe']);

    Http::fake(['*' => Http::response(fakeGeminiResponse([
        'first_name' => 'Ana',
        'photo' => ['page' => 1, 'box_2d' => [100, 100, 300, 300]],
    ]))]);

    $candidate = app(CandidateCvImporter::class)->import('recruitment/cvs/cv.pdf', 'cv.pdf');

    expect($candidate->first_name)->toBe('Ana')
        ->and($candidate->photo_path)->toBeNull()
        ->and($candidate->cv_photo_error)->toContain('pdftoppm')
        ->and($candidate->isFieldFlagged('photo_path'))->toBeTrue();
});
