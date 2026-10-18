@extends('pdf.layout')

@section('title', $labels['title'])
@section('document_no', $statement['party']['name'])

@section('status')
@php
    $closingBal = (float) $statement['closing_balance'];
    $isSettled = abs($closingBal) < 0.005;
@endphp
<table class="status-badge" style="width:{{ $isSettled ? '28mm' : '38mm' }};">
    <tr>
        <td class="{{ $isSettled ? 'status-posted' : 'status-waiting_approval' }}">
            {{ $balanceHint }}
        </td>
    </tr>
</table>
@endsection

@section('content')

<table class="meta">
    <tr>
        <td class="box" width="50%">
            <table width="100%">
                <tr><td class="label">{{ $labels['party'] }}</td><td><strong>{{ $statement['party']['name'] }}</strong></td></tr>
                @if($statement['party']['company'] ?? null)
                    <tr><td class="label">الشركة / النشاط</td><td>{{ $statement['party']['company'] }}</td></tr>
                @endif
                @if($statement['party']['phone'] ?? null)
                    <tr><td class="label">الهاتف</td><td class="ltr" style="text-align:right;">{{ $statement['party']['phone'] }}</td></tr>
                @endif
                <tr>
                    <td class="label">حالة الرصيد</td>
                    <td>
                        <strong>{{ $money($statement['closing_balance']).' '.$company['currency'] }}</strong>
                        <span class="muted" style="font-size:7.5pt;">({{ $balanceHint }})</span>
                    </td>
                </tr>
            </table>
        </td>
        <td width="4%"></td>
        <td class="box" width="46%">
            <table width="100%">
                <tr>
                    <td class="label">من تاريخ</td>
                    <td>
                        @if($statement['period']['from'])
                            <span dir="ltr">{{ $statement['period']['from'] }}</span>
                        @else
                            بداية الحساب
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">إلى تاريخ</td>
                    <td><span dir="ltr">{{ $statement['period']['to'] ?? $printedOn }}</span></td>
                </tr>
                <tr><td class="label">عدد الحركات</td><td class="ltr" style="text-align:right;">{{ count($statement['rows']) }}</td></tr>
                <tr><td class="label">العملة الأساسية</td><td>{{ $company['currency'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($truncated)
    <div class="notes watermark-cancelled">
        تنبيه: يعرض هذا الكشف أول {{ number_format($rowLimit) }} حركة فقط. حدّد فترة أضيق للحصول على كشف كامل.
    </div>
@endif

<table class="lines">
    <thead>
        <tr>
            <th width="13%">التاريخ</th>
            <th width="31%">البيان</th>
            <th width="16%">المرجع</th>
            <th width="13%" style="text-align:left;">مدين</th>
            <th width="13%" style="text-align:left;">دائن</th>
            <th width="14%" style="text-align:left;">الرصيد</th>
        </tr>
    </thead>
    <tbody>
        <tr style="background:#f8fafc;">
            <td class="num">{{ $statement['period']['from'] ?? '—' }}</td>
            <td colspan="2"><strong>رصيد ما قبله (افتتاحي)</strong></td>
            <td class="num">—</td>
            <td class="num">—</td>
            <td class="num"><strong>{{ $money($statement['opening_balance']).' '.$company['currency'] }}</strong></td>
        </tr>
        @forelse($statement['rows'] as $index => $row)
            <tr class="{{ $index % 2 ? 'zebra' : '' }}">
                <td class="num">{{ $row['transaction_date'] }}</td>
                <td>
                    <span style="font-weight:600;">{{ $row['type_label'] }}</span>
                    @if($row['description'])<div class="muted" style="font-size:7.5pt;margin-top:0.5mm;">{{ $row['description'] }}</div>@endif
                </td>
                <td class="num">{{ $row['reference_no'] ?? '—' }}</td>
                <td class="num">{{ (float) $row['debit'] ? $money($row['debit']).' '.$company['currency'] : '—' }}</td>
                <td class="num">{{ (float) $row['credit'] ? $money($row['credit']).' '.$company['currency'] : '—' }}</td>
                <td class="num" style="font-weight:600;">{{ $money($row['running_balance']).' '.$company['currency'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#888;padding:6mm;">لا توجد حركات في الفترة المحددة</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">إجمالي الفترة</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['totals']['debit']).' '.$company['currency'] }}</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['totals']['credit']).' '.$company['currency'] }}</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['closing_balance']).' '.$company['currency'] }}</th>
        </tr>
    </tfoot>
</table>

@php
    $netMovement = (float) $statement['totals']['debit'] - (float) $statement['totals']['credit'];
@endphp

<table width="100%" style="margin-top:5mm;page-break-inside:avoid;">
    <tr>
        <td width="50%" style="vertical-align:top;">
            <div class="notes" style="margin-top:2mm;font-size:8pt;line-height:1.6;color:#555;">
                <div><strong>ملاحظات:</strong></div>
                <div>• هذا الكشف مستخرج آلياً ويعتبر صالحاً لمطابقة الحسابات حتى تاريخه.</div>
                <div>• يرجى مراجعة الحركات وإشعارنا بأي ملاحظة خلال 7 أيام من تاريخ الاستلام.</div>
            </div>
        </td>
        <td width="5%"></td>
        <td width="45%" style="vertical-align:top;">
            <table class="totals" width="100%">
                <tr><td class="label">الرصيد الافتتاحي</td><td class="value">{{ $money($statement['opening_balance']).' '.$company['currency'] }}</td></tr>
                <tr><td class="label">إجمالي المدين</td><td class="value">{{ $money($statement['totals']['debit']).' '.$company['currency'] }}</td></tr>
                <tr><td class="label">إجمالي الدائن</td><td class="value">{{ $money($statement['totals']['credit']).' '.$company['currency'] }}</td></tr>
                <tr>
                    <td class="label">صافي حركة الفترة</td>
                    <td class="value">{{ $money(abs($netMovement)).' '.$company['currency'] }} <span style="font-size:7.5pt;font-weight:normal;color:#666;">({{ $netMovement >= 0 ? 'مدين' : 'دائن' }})</span></td>
                </tr>
                <tr class="grand">
                    <td class="label">الرصيد الختامي</td>
                    <td class="value">{{ $money($statement['closing_balance']).' '.$company['currency'] }}</td>
                </tr>
            </table>
            <div class="muted" style="font-size:8.5pt;margin-top:2mm;text-align:left;font-weight:bold;">{{ $balanceHint }}</div>
        </td>
    </tr>
</table>

@if(!empty($print['show_signatures']))
<table class="signatures" style="page-break-inside:avoid;margin-top:12mm;">
    <tr>
        <td class="sign" width="40%">توقيع {{ $labels['party'] }} (مطابقة الرصيد)</td>
        <td width="20%"></td>
        <td class="sign" width="40%">توقيع المحاسب / الإدارة المالية</td>
    </tr>
</table>
@endif

@endsection
