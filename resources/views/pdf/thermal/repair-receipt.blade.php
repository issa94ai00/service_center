{{-- Device intake slip on a thermal roll. Same data as pdf.repair-receipt.
     The receipt number is what the customer reads out at pick-up, so it is
     the largest thing on the slip. --}}
@extends('pdf.thermal.layout')

@section('title', $labels['title'])
@section('document_no', $repair->receipt_no ?? '—')

@section('content')

<table class="kv">
    <tr><td class="k">تاريخ الاستلام</td><td class="v ltr">{{ $receiptDate }}</td></tr>
    @if($repair->delivered_at)
        <tr><td class="k">تاريخ التسليم</td><td class="v ltr">{{ $deliveredDate }}</td></tr>
    @endif
    <tr><td class="k">الحالة</td><td class="v">{{ $labels['status'] }}</td></tr>
    <tr><td class="k">{{ $labels['party'] }}</td><td class="v"><strong>{{ $party?->name ?? '—' }}</strong></td></tr>
    @if($party?->phone)
        <tr><td class="k">الهاتف</td><td class="v ltr">{{ $party->phone }}</td></tr>
    @endif
</table>

<div class="cut"></div>

<table class="kv">
    <tr><td class="k">الجهاز</td><td class="v"><strong>{{ $repair->device_type }}</strong></td></tr>
    @php($specs = collect([$repair->brand, $repair->model, $repair->color])->filter()->join(' / '))
    @if($specs)
        <tr><td class="k">المواصفات</td><td class="v">{{ $specs }}</td></tr>
    @endif
    @if($repair->serial_number)
        <tr><td class="k">سيريال</td><td class="v ltr">{{ $repair->serial_number }}</td></tr>
    @endif
</table>

<div class="block-title">العطل المذكور</div>
<div>{{ $repair->reported_issue }}</div>

@if($repair->accessories)
    <div class="block-title">المرفقات</div>
    <div>{{ $repair->accessories }}</div>
@endif

@if($repair->notes)
    <div class="block-title">ملاحظات</div>
    <div>{{ $repair->notes }}</div>
@endif

<div class="cut"></div>

<table class="kv">
    <tr class="grand">
        <td class="k">{{ $costLabel }}</td>
        <td class="v">{{ $cost !== null ? $cost.' '.$company['currency'] : '—' }}</td>
    </tr>
    @if($paid)
        <tr><td class="k">المدفوع</td><td class="v">{{ $paid }}</td></tr>
        <tr><td class="k">{{ $dueLabel }}</td><td class="v"><strong>{{ $due }}</strong></td></tr>
        @if($invoiceNo)
            <tr><td class="k">رقم الفاتورة</td><td class="v ltr">{{ $invoiceNo }}</td></tr>
        @endif
    @endif
</table>

@if($repair->status->value === 'CANCELLED')
    <div class="cancelled">هذا البلاغ ملغي</div>
@endif

@if(!empty($print['repair_terms']))
    <div class="cut"></div>
    <div class="terms">
        <div class="block-title">شروط الصيانة</div>
        {!! nl2br(e($print['repair_terms'])) !!}
    </div>
@endif

@if($print['show_signatures'])
    <div class="signature small">توقيع العميل</div>
@endif

@endsection
