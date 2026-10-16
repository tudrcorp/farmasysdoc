<?php

namespace App\Http\Controllers\Fiscal;

use App\Enums\SalesBookStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SalesBook;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\SalesBookBuilder;
use App\Services\Fiscal\SalesBookPdfFactory;
use App\Services\Fiscal\SalesBookPeriodLock;
use App\Services\Fiscal\SalesBookXlsxExporter;
use App\Support\Fiscal\SalesBookAccess;
use App\Support\Fiscal\SalesBookPeriod;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga del Libro de Ventas (PDF o Excel). Si la quincena está cerrada se entrega siempre el snapshot
 * congelado; si no, un borrador marcado como no válido para declarar.
 */
final class SalesBookReportController extends Controller
{
    public function __invoke(
        Request $request,
        SalesBookBuilder $builder,
        SalesBookPeriodLock $lock,
        SalesBookXlsxExporter $xlsx,
        SalesBookPdfFactory $pdf,
    ): Response {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        abort_unless(SalesBookAccess::canView($user), 403, 'No tienes permiso para el Libro de Ventas.');

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $bookId = (int) $request->query('book', 0);

        if ($bookId > 0) {
            $book = SalesBook::query()->with('branch')->findOrFail($bookId);
            $branch = $book->branch;
        } else {
            $branch = Branch::query()->findOrFail((int) $request->query('branch'));

            try {
                $period = SalesBookPeriod::fromKey((string) $request->query('period'));
            } catch (InvalidArgumentException $exception) {
                abort(422, $exception->getMessage());
            }

            $book = $lock->closedBook((int) $branch->id, $period);
        }

        abort_unless($branch instanceof Branch && SalesBookAccess::canAccessBranch($user, (int) $branch->id), 403, 'No tienes acceso a esta sucursal.');

        if ($book instanceof SalesBook) {
            abort_unless($book->hasValidContentHash(), 409, 'El libro cerrado no supera la verificación de integridad (SHA-256).');

            $data = $book->snapshotData();
            $period = $book->period();
            $isDraft = false;
            $statusLine = ($book->status === SalesBookStatus::Closed ? 'LIBRO CERRADO' : 'VERSIÓN ANULADA (REABIERTA)')
                .' · versión '.$book->version
                .' · cerrado el '.$book->closed_at?->format('d/m/Y h:i A').' por '.$book->closed_by
                .' · SHA-256 '.$book->content_hash;
        } else {
            $data = $builder->build($branch, $period, SalesBookAccess::actorLabel($user));
            $isDraft = true;
            $statusLine = 'BORRADOR — NO VÁLIDO PARA DECLARAR. La quincena no está cerrada; los datos pueden cambiar.';
        }

        AuditLogger::record(
            'sales_book_downloaded',
            'Libro de Ventas: descarga '.strtoupper($format).' · '.$branch->name.' · '.$period->label().($isDraft ? ' (borrador)' : ' (v'.$book->version.')'),
            $book instanceof SalesBook ? SalesBook::class : null,
            $book?->id,
            $period->key(),
            ['module' => 'fiscal', 'branch_id' => $branch->id, 'format' => $format, 'draft' => $isDraft],
        );

        $filename = 'libro-de-ventas-'.str($branch->name)->slug().'-'.$period->key().($isDraft ? '-borrador' : '-v'.$book->version);

        if ($format === 'xlsx') {
            $path = $xlsx->export($data, $statusLine);

            return response()->download($path, $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        return $pdf->make($data, $statusLine, $isDraft)->download($filename.'.pdf');
    }
}
