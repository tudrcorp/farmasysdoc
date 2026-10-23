<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequestAttachment;
use App\Services\Hr\EmployeePortalAccess;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeePortalRequestAttachmentController extends Controller
{
    public function __invoke(EmployeeRequestAttachment $attachment, EmployeePortalAccess $access): StreamedResponse
    {
        $employee = $access->employee();
        $attachment->loadMissing('employeeRequest');

        abort_unless(
            $employee !== null && (int) $attachment->employeeRequest?->employee_id === (int) $employee->id,
            403,
        );

        $disk = Storage::disk(EmployeeRequestAttachment::DISK);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
