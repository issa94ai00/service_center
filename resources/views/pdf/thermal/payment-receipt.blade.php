{{-- Payment receipt on a thermal roll. Same data as pdf.payment-receipt. --}}
@extends('pdf.thermal.layout')

@section('title', $labels['title'])
@section('document_no', $payment->reference_no ?? '—')

@section('content')

<table class="kv">
    <tr><td class="k">التاريخ</td><td class="v ltr">{{ $paymentDate }}</td></tr>
    <tr><td class="k">الحالة</td><td class="v">{{ $labels['status'] }}</td></tr>
    <tr><td class="k">{{ $labels['party'] }}</td><td class="v"><strong>{{ $party?->name ?? '—' }}</strong></td></tr>
    @if($party?->phone)
        <tr><td class="k">الهاتف</td><td class="v ltr">{{ $party->phone }}</td></tr>
    @endif
    <tr><td class="k">طريقة الدفع</td><td class="v">{{ $labels['method'] }}</td></tr>
    @if(!empty($reference))
        <tr><td class="k">{{ $reference['label'] }}</td><td class="v ltr">{{ $reference['value'] }}</td></tr>
        @if(!empty($reference['note']))
            <tr><td class="k">{{ $reference['note_label'] }}</td><td class="v">{{ $reference['note'] }}</td></tr>
        @endif
    @endif
</table>

<div class="amount-box">
    <div class="small">{{ $labels['amount_label'] }}</div>
    <div class="amount">{{ $amount }} {{ $amountCurrency }}</div>
    @if($baseEquivalent)
        <div class="small">يعادل {{ $baseEquivalent['amount'] }} — سعر الصرف {{ $baseEquivalent['rate'] }}</div>
    @endif
</div>
<div class="small center">{{ $amountInWords }}</div>

@if($allocations->isNotEmpty())
    <div class="cut"></div>
    <div class="block-title">توزيع الدفعة على الفواتير</div>
    <table class="items">
        @foreach($allocations as $allocation)
            <tr>
                <td class="ltr" style="text-align:right;">{{ $allocation['invoice_no'] }}</td>
                <td class="num">{{ $allocation['amount'] }} {{ $company['currency'] }}</td>
            </tr>
        @endforeach
    </table>
@elseif(!empty($reference))
    <div class="notes small">عربون على بلاغ الصيانة — يُخصم من فاتورة التسوية عند التسليم.</div>
@endif

@if($payment->notes)
    <div class="notes"><strong>ملاحظات:</strong> {{ $payment->notes }}</div>
@endif

@if($payment->status->value === 'CANCELLED')
    <div class="cancelled">سند ملغى{{ $payment->cancellation_reason ? ' — '.$payment->cancellation_reason : '' }}</div>
@endif

@if($print['show_signatures'])
    <div class="signature small">{{ $labels['signature_party'] }}</div>
@endif

@endsection
