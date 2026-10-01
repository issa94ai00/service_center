@extends('pdf.layout')

@section('title', $labels['title'])
@section('document_no', $repair->receipt_no ?? '—')

@section('status')
    <table class="status-badge" align="left">
        <tr><td class="status-{{ strtolower($repair->status->value) }}">{{ $labels['status'] }}</td></tr>
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
                @if($party?->address)
                    <tr><td class="label">العنوان</td><td>{{ $party->address }}</td></tr>
                @endif
            </table>
        </td>
        <td width="4%"></td>
        <td class="box" width="46%">
            <table width="100%">
                <tr><td class="label">تاريخ الاستلام</td><td class="ltr" style="text-align:right;">{{ $receiptDate }}</td></tr>
                @if($repair->delivered_at)
                    <tr><td class="label">تاريخ التسليم</td><td class="ltr" style="text-align:right;">{{ $deliveredDate }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top:5mm;">
    <thead>
        <tr>
            <th width="30%">الجهاز</th>
            <th width="35%">المواصفات</th>
            <th width="35%">{{ $costLabel }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $repair->device_type }}</td>
            <td>
                {{ collect([$repair->brand, $repair->model, $repair->color])->filter()->join(' / ') ?: '—' }}
                @if($repair->serial_number)
                    <div>سيريال: <span class="ltr" style="text-align:right;">{{ $repair->serial_number }}</span></div>
                @endif
            </td>
            <td class="num">{{ $cost !== null ? $cost.' '.$company['currency'] : '—' }}</td>
        </tr>
    </tbody>
</table>

@if($paid)
    {{-- Money already taken against this device. Printed only when there is
         some, so a plain intake receipt stays as short as it was. --}}
    <table class="lines" style="margin-top:5mm;">
        <tr>
            <th width="25%">المدفوع</th>
            <td class="num">{{ $paid }} {{ $company['currency'] }}</td>
            <th width="25%">{{ $dueLabel }}</th>
            <td class="num">{{ $due }} {{ $company['currency'] }}</td>
        </tr>
        @if($invoiceNo)
            <tr>
                <th>رقم الفاتورة</th>
                <td class="ltr" colspan="3" style="text-align:right;">{{ $invoiceNo }}</td>
            </tr>
        @endif
    </table>
@endif

<div style="margin-top:5mm;">
    <table class="lines">
        <tr>
            <th width="25%">العطل المذكور</th>
            <td>{{ $repair->reported_issue }}</td>
        </tr>
        @if($repair->accessories)
            <tr>
                <th>المرفقات</th>
                <td>{{ $repair->accessories }}</td>
            </tr>
        @endif
        @if($repair->notes)
            <tr>
                <th>ملاحظات</th>
                <td>{{ $repair->notes }}</td>
            </tr>
        @endif
    </table>
</div>

@if($repair->status->value === 'CANCELLED')
    <div class="notes watermark-cancelled">هذا البلاغ ملغي.</div>
@endif

{{-- The shop's own terms — how long an unclaimed device is kept, what the
     warranty covers — printed where the customer signs for them. --}}
@if(!empty($print['repair_terms']))
    <div class="terms">
        <div class="terms-title">شروط الصيانة</div>
        {!! nl2br(e($print['repair_terms'])) !!}
    </div>
@endif

@if($print['show_signatures'])
<table class="signatures">
    <tr>
        <td class="sign" width="40%">توقيع العميل المستلم</td>
        <td width="20%"></td>
        <td class="sign" width="40%">توقيع موظف الاستلام</td>
    </tr>
</table>
@endif

@endsection