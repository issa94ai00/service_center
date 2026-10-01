@extends('pdf.layout')

@section('title', $labels['title'])
@section('document_no', $payment->reference_no ?? '—')

@section('status')
    <table class="status-badge" align="left">
        <tr><td class="status-{{ strtolower($payment->status->value) }}">{{ $labels['status'] }}</td></tr>
    </table>
@endsection

@section('content')

<table class="meta">
    <tr>
        <td class="box" width="50%">
            <table width="100%">
                <tr><td class="label">{{ $labels['party'] }}</td><td><strong>{{ $party?->name ?? '—' }}</strong></td></tr>
                @if($party?->phone)
                    <tr><td class="label">الهاتف</td><td class="ltr" style="text-align:right;">{{ $party->phone }}</td></tr>
                @endif
            </table>
        </td>
        <td width="4%"></td>
        <td class="box" width="46%">
            <table width="100%">
                <tr><td class="label">التاريخ</td><td class="ltr" style="text-align:right;">{{ $paymentDate }}</td></tr>
                <tr><td class="label">طريقة الدفع</td><td>{{ $labels['method'] }}</td></tr>
                <tr><td class="label">العملة</td><td>{{ $company['currency'] }}</td></tr>
                {{-- سند قبض على بلاغ صيانة: الجهاز ما زال في المحل، فالسند
                     يسمّيه ويحمل رقم إيصال استلامه. --}}
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

<table class="lines" style="margin-top:6mm;">
    <tr>
        <th width="30%">{{ $labels['amount_label'] }}</th>
        <td style="font-size:14pt;font-weight:bold;direction:ltr;text-align:left;">
            {{ $amount }} {{ $company['currency'] }}
        </td>
    </tr>
    <tr>
        <th>المبلغ كتابةً</th>
        <td>{{ $amountInWords }}</td>
    </tr>
</table>

@if($allocations->isNotEmpty())
    <div style="margin-top:6mm;font-weight:bold;">توزيع الدفعة على الفواتير</div>
    <table class="lines">
        <thead>
            <tr>
                <th width="10%">#</th>
                <th width="35%">رقم الفاتورة</th>
                <th width="25%">تاريخ الفاتورة</th>
                <th width="30%">المبلغ المخصّص</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allocations as $index => $allocation)
                <tr class="{{ $index % 2 ? 'zebra' : '' }}">
                    <td class="num">{{ $index + 1 }}</td>
                    <td class="num">{{ $allocation['invoice_no'] }}</td>
                    <td class="num">{{ $allocation['invoice_date'] }}</td>
                    <td class="num">{{ $allocation['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@elseif(!empty($reference))
    <div class="notes">عربون على بلاغ الصيانة {{ $reference['value'] }} — يُخصم من فاتورة التسوية عند تسليم الجهاز.</div>
@else
    <div class="notes">دفعة غير مخصّصة على فاتورة بعينها — تُرحّل على رصيد الحساب.</div>
@endif

@if($payment->notes)
    <div class="notes"><strong>ملاحظات:</strong> {{ $payment->notes }}</div>
@endif

@if($payment->status->value === 'CANCELLED')
    <div class="notes watermark-cancelled">
        هذا السند ملغى{{ $payment->cancellation_reason ? ' — '.$payment->cancellation_reason : '' }}
    </div>
@endif

@if($print['show_signatures'])
<table class="signatures">
    <tr>
        <td class="sign" width="40%">{{ $labels['signature_party'] }}</td>
        <td width="20%"></td>
        <td class="sign" width="40%">توقيع أمين الصندوق</td>
    </tr>
</table>
@endif

@endsection
