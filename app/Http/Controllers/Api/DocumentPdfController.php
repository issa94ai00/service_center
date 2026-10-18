<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaperSize;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\Technician;
use App\Services\DocumentPdfService;
use App\Support\PrintSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdfController extends Controller
{
    public function __construct(protected DocumentPdfService $documents) {}

    public function salesInvoice(Request $request, SalesInvoice $sale): Response
    {
        $this->authorize('view', $sale);

        return $this->stream(
            $this->documents->salesInvoice($sale, $this->paper($request, PrintSettings::INVOICE)),
            $this->filename('sales-invoice', $sale->invoice_no, $sale->id),
        );
    }

    public function purchaseInvoice(Request $request, PurchaseInvoice $purchase): Response
    {
        $this->authorize('view', $purchase);

        return $this->stream(
            $this->documents->purchaseInvoice($purchase, $this->paper($request, PrintSettings::INVOICE)),
            $this->filename('purchase-invoice', $purchase->invoice_no, $purchase->id),
        );
    }

    public function paymentReceipt(Request $request, Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return $this->stream(
            $this->documents->paymentReceipt($payment, $this->paper($request, PrintSettings::PAYMENT)),
            $this->filename('payment-receipt', $payment->reference_no, $payment->id),
        );
    }

    public function repairReceipt(Request $request, Repair $repair): Response
    {
        $this->authorize('view', $repair);

        return $this->stream(
            $this->documents->repairReceipt($repair, $this->paper($request, PrintSettings::REPAIR)),
            $this->filename('repair-receipt', $repair->receipt_no, $repair->id),
        );
    }

    public function customerStatement(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()?->hasPermission('statements.view'), 403);

        $filters = $this->periodFilters($request);

        return $this->stream(
            $this->documents->customerStatement($customer, $filters, $this->paper($request, PrintSettings::STATEMENT)),
            $this->filename('customer-statement', null, $customer->id),
        );
    }

    public function supplierStatement(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()?->hasPermission('statements.view'), 403);

        $filters = $this->periodFilters($request);

        return $this->stream(
            $this->documents->supplierStatement($supplier, $filters, $this->paper($request, PrintSettings::STATEMENT)),
            $this->filename('supplier-statement', null, $supplier->id),
        );
    }

    public function technicianStatement(Request $request, Technician $technician): Response
    {
        abort_unless($request->user()?->hasPermission('statements.view'), 403);

        $filters = $this->periodFilters($request);

        return $this->stream(
            $this->documents->technicianStatement($technician, $filters, $this->paper($request, PrintSettings::STATEMENT)),
            $this->filename('technician-statement', null, $technician->id),
        );
    }

    /**
     * A test page on a chosen paper, for lining up a printer. It carries no
     * real data, so any signed-in user may print one.
     */
    public function sample(Request $request): Response
    {
        $paper = $this->paper($request, PrintSettings::INVOICE) ?? PaperSize::A4;

        return $this->stream($this->documents->sample($paper), 'print-test-'.$paper->value.'.pdf');
    }

    /**
     * What the print buttons need to label themselves: every paper, which
     * papers each document kind supports, and the shop default for each.
     */
    public function config(PrintSettings $printing): JsonResponse
    {
        return response()->json(['data' => [
            'papers' => array_map(fn (PaperSize $paper) => [
                'value' => $paper->value,
                'label' => $paper->label(),
                'thermal' => $paper->isThermal(),
                'width' => $paper->width(),
            ], PaperSize::cases()),
            'supported' => collect(array_keys(PrintSettings::supported()))
                ->mapWithKeys(fn (string $kind) => [$kind => PrintSettings::supportedValues($kind)])
                ->all(),
            'defaults' => $printing->defaults(),
        ]]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * The paper asked for in ?paper=, if any. One the document kind has no
     * layout for is refused rather than silently swapped for the default, so
     * the printed page always matches the size the clerk picked.
     */
    protected function paper(Request $request, string $kind): ?PaperSize
    {
        $validated = $request->validate([
            'paper' => ['nullable', 'string', Rule::in(PrintSettings::supportedValues($kind))],
        ], [
            'paper.in' => 'حجم الورق غير مدعوم لهذا المستند.',
        ]);

        return PaperSize::tryFrom((string) ($validated['paper'] ?? ''));
    }


    /**
     * @return array{from: ?string, to: ?string}
     */
    protected function periodFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويًا له.',
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * Documents open inline so "print" is one click; the browser's own save
     * action still writes the file under this name.
     */
    protected function stream(string $pdf, string $filename): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * ASCII-only filename: a raw Arabic document number in a Content-Disposition
     * header is mangled by browsers that do not read RFC 5987 encoding.
     */
    protected function filename(string $prefix, ?string $reference, int $id): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $reference);

        return $prefix.'-'.($slug !== '' ? $slug : $id).'.pdf';
    }
}
