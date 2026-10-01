{{-- Sales and purchase invoices on a thermal roll. Same data as pdf.invoice;
     each line is two rows — the description, then quantity × price and the
     line total — because six columns do not fit in 72 mm. --}}
@extends('pdf.thermal.layout')

@section('title', $labels['title'])
@section('document_no', $invoice->invoice_no ?? '—')

@section('content')

<table class="kv">
    <tr><td class="k">التاريخ</td><td class="v ltr">{{ $documentDate }}</td></tr>
    @if($dueDate)
        <tr><td class="k">الاستحقاق</td><td class="v ltr">{{ $dueDate }}</td></tr>
    @endif
    <tr><td class="k">الحالة</td><td class="v">{{ $labels['status'] }}</td></tr>
    <tr><td class="k">{{ $labels['party'] }}</td><td class="v"><strong>{{ $party?->name ?? '—' }}</strong></td></tr>
    @if($party?->phone)
        <tr><td class="k">الهاتف</td><td class="v ltr">{{ $party->phone }}</td></tr>
    @endif
    @if(!empty($reference))
        <tr><td class="k">{{ $reference['label'] }}</td><td class="v ltr">{{ $reference['value'] }}</td></tr>
        @if(!empty($reference['note']))
            <tr><td class="k">{{ $reference['note_label'] }}</td><td class="v">{{ $reference['note'] }}</td></tr>
        @endif
    @endif
</table>

<div class="cut"></div>

<table class="items">
    <thead>
        <tr>
            <th>البيان</th>
            <th class="num">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $index => $line)
            <tr><td class="desc" colspan="2">{{ $index + 1 }}. {{ $line['description'] }}</td></tr>
            <tr>
                <td class="muted">
                    <span class="ltr">{{ $line['quantity'] }} × {{ $line['unit_price'] }}</span>
                    @if($line['unit']) {{ $line['unit'] }}@endif
                </td>
                <td class="num">{{ $line['line_total'] }}</td>
            </tr>
        @empty
            <tr><td colspan="2" class="center muted">لا توجد بنود</td></tr>
        @endforelse
    </tbody>
</table>

<div class="cut"></div>

<table class="kv">
    @if($totals['has_discount'])
        <tr><td class="k">المجموع</td><td class="v">{{ $totals['subtotal'] }}</td></tr>
        <tr><td class="k">الخصم</td><td class="v">{{ $totals['discount'] }}</td></tr>
    @endif
    <tr class="grand"><td class="k">الإجمالي</td><td class="v">{{ $totals['total'] }} {{ $company['currency'] }}</td></tr>
    <tr><td class="k">المدفوع</td><td class="v">{{ $totals['paid'] }}</td></tr>
    <tr><td class="k">المتبقي</td><td class="v"><strong>{{ $totals['remaining'] }}</strong></td></tr>
</table>

@if($invoice->notes)
    <div class="notes"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
@endif

@if($invoice->status->value === 'CANCELLED')
    <div class="cancelled">فاتورة ملغاة{{ $invoice->cancellation_reason ? ' — '.$invoice->cancellation_reason : '' }}</div>
@endif

@if($print['show_signatures'])
    <div class="signature small">{{ $labels['signature_party'] }}</div>
@endif

@endsection
