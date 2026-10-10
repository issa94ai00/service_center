{{-- Sales and purchase invoices share a layout; $kind switches the wording and
     which party column is shown. --}}
@extends('pdf.layout')

@section('title', $labels['title'])
@section('document_no', $invoice->invoice_no ?? '—')

@section('status')
    <table class="status-badge" align="left">
        <tr><td class="status-{{ strtolower($invoice->status->value) }}">{{ $labels['status'] }}</td></tr>
    </table>
@endsection

@section('content')

<table class="meta">
    <tr>
        <td class="box" width="50%">
            <table width="100%">
                <tr><td class="label">{{ $labels['party'] }}</td><td><strong>{{ $party?->name ?? '—' }}</strong></td></tr>
                @if($party?->company)
                    <tr><td class="label">الشركة</td><td>{{ $party->company }}</td></tr>
                @endif
                @if($party?->phone)
                    <tr><td class="label">الهاتف</td><td class="ltr" style="text-align:right;">{{ $party->phone }}</td></tr>
                @endif
                @if($party?->address)
                    <tr><td class="label">العنوان</td><td>{{ $party->address }}</td></tr>
                @endif
            </table>
        </td>
        <td width="4%"></td>
        <td class="box" width="46%">
            <table width="100%">
                <tr><td class="label">التاريخ</td><td class="ltr" style="text-align:right;">{{ $documentDate }}</td></tr>
                @if($dueDate)
                    <tr><td class="label">الاستحقاق</td><td class="ltr" style="text-align:right;">{{ $dueDate }}</td></tr>
                @endif
                <tr><td class="label">العملة</td><td>{{ $company['currency'] }}</td></tr>
                @if($invoice->posted_at)
                    <tr><td class="label">تاريخ الاعتماد</td><td class="ltr" style="text-align:right;">{{ $invoice->posted_at->format('Y-m-d') }}</td></tr>
                @endif
                {{-- فاتورة تسوية صيانة تحمل رقم إيصال الاستلام والجهاز، فتُقرأ
                     بجانب الإيصال الذي بيد العميل. --}}
                @if(!empty($reference))
                    <tr><td class="label">{{ $reference['label'] }}</td><td class="ltr" style="text-align:right;">{{ $reference['value'] }}</td></tr>
                    @if(!empty($reference['note']))
                        <tr><td class="label">{{ $reference['note_label'] }}</td><td>{{ $reference['note'] }}</td></tr>
                    @endif
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th width="6%">#</th>
            <th width="{{ $showKind ? '34%' : '40%' }}">البيان</th>
            @if($showKind)<th width="10%">النوع</th>@endif
            <th width="10%">الوحدة</th>
            <th width="12%">الكمية</th>
            <th width="14%">سعر الوحدة</th>
            <th width="14%">الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $index => $line)
            <tr class="{{ $index % 2 ? 'zebra' : '' }}">
                <td class="num">{{ $index + 1 }}</td>
                <td>{{ $line['description'] }}</td>
                @if($showKind)<td>{{ $line['kind'] }}</td>@endif
                <td>{{ $line['unit'] ?? '—' }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $line['unit_price'] }} {{ $company['currency'] }}</td>
                <td class="num">{{ $line['line_total'] }} {{ $company['currency'] }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $showKind ? 7 : 6 }}" style="text-align:center;color:#888;padding:6mm;">لا توجد بنود</td></tr>
        @endforelse
    </tbody>
</table>

<table width="100%" style="margin-top:4mm;">
    <tr>
        <td width="52%" style="vertical-align:top;">
            @if($invoice->notes)
                <div class="notes"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
            @endif
            @if($invoice->status->value === 'CANCELLED')
                <div class="notes watermark-cancelled">
                    هذه الفاتورة ملغاة{{ $invoice->cancellation_reason ? ' — '.$invoice->cancellation_reason : '' }}
                </div>
            @endif
        </td>
        <td width="48%">
            <table class="totals" width="100%">
                <tr>
                    <td class="label">المجموع قبل الخصم</td>
                    <td class="value">{{ $totals['subtotal'] }} {{ $company['currency'] }}</td>
                </tr>
                @if($totals['has_discount'])
                    <tr>
                        <td class="label">الخصم</td>
                        <td class="value">{{ $totals['discount'] }} {{ $company['currency'] }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td class="label">الإجمالي</td>
                    <td class="value">{{ $totals['total'] }} {{ $company['currency'] }}</td>
                </tr>
                <tr>
                    <td class="label">المدفوع</td>
                    <td class="value">{{ $totals['paid'] }} {{ $company['currency'] }}</td>
                </tr>
                <tr>
                    <td class="label">المتبقي</td>
                    <td class="value">{{ $totals['remaining'] }} {{ $company['currency'] }}</td>
                </tr>
                @foreach($totals['equivalents'] as $equivalent)
                    <tr>
                        <td class="label">الإجمالي بـ{{ $equivalent['name'] }}</td>
                        <td class="value">{{ $equivalent['total'] }} {{ $equivalent['symbol'] }}</td>
                    </tr>
                    @if($equivalent['remaining'])
                        <tr>
                            <td class="label">المتبقي بـ{{ $equivalent['name'] }}</td>
                            <td class="value">{{ $equivalent['remaining'] }} {{ $equivalent['symbol'] }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
            @if($totals['equivalents']->isNotEmpty())
                <div style="font-size:8pt;color:#64748b;margin-top:1mm;">
                    @foreach($totals['equivalents'] as $equivalent)
                        سعر الصرف: 1 {{ $company['currency'] }} = {{ $equivalent['rate'] }} {{ $equivalent['symbol'] }}@if(!$loop->last) — @endif
                    @endforeach
                </div>
            @endif
        </td>
    </tr>
</table>

@if($print['show_signatures'])
<table class="signatures">
    <tr>
        <td class="sign" width="40%">{{ $labels['signature_party'] }}</td>
        <td width="20%"></td>
        <td class="sign" width="40%">توقيع المسؤول</td>
    </tr>
</table>
@endif

@endsection
