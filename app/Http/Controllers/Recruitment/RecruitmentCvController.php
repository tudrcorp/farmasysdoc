<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecruitmentCvController extends Controller
{
    public function candidate(Request $request, RecruitmentCandidate $candidate): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessRecruitmentCandidates(), 403);

        return $this->stream((string) $candidate->cv_path);
    }

    public function employee(Request $request, Employee $employee): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessHrModule(), 403);

        return $this->stream((string) $employee->cv_path);
    }

    private function stream(string $path): StreamedResponse
    {
        $disk = Storage::disk(RecruitmentCandidate::CV_DISK);
        abort_unless($path !== '' && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path);
    }
}
