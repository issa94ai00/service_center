<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\Repair;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Services\DocumentPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdfController extends Controller
{
    public function __construct(protected DocumentPdfService $documents) {}

    public function salesInvoice(SalesInvoice $sale): Response
    {
        $this->authorize('view', $sale);

        return $this->stream(
            $this->documents->salesInvoice($sale),
            $this->filename('sales-invoice', $sale->invoice_no, $sale->id),
        );
    }

    public function purchaseInvoice(PurchaseInvoice $purchase): Response
    {
        $this->authorize('view', $purchase);

        return $this->stream(
            $this->documents->purchaseInvoice($purchase),
            $this->filename('purchase-invoice', $purchase->invoice_no, $purchase->id),
        );
    }

    public function paymentReceipt(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return $this->stream(
            $this->documents->paymentReceipt($payment),
            $this->filename('payment-receipt', $payment->reference_no, $payment->id),
        );
    }

    public function repairReceipt(Repair $repair): Response
    {
        $this->authorize('view', $repair);

        return $this->stream(
            $this->documents->repairReceipt($repair),
            $this->filename('repair-receipt', $repair->receipt_no, $repair->id),
        );
    }

    public function customerStatement(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()?->hasPermission('statements.view'), 403);

        $filters = $this->periodFilters($request);

        return $this->stream(
            $this->documents->customerStatement($customer, $filters),
            $this->filename('customer-statement', null, $customer->id),
        );
    }

    public function supplierStatement(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()?->hasPermission('statements.view'), 403);

        $filters = $this->periodFilters($request);

        return $this->stream(
            $this->documents->supplierStatement($supplier, $filters),
            $this->filename('supplier-statement', null, $supplier->id),
        );
    }

    // ---------------------------------------------------------------- internals

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
