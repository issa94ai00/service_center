@extends('pdf.layout')

@section('title', $labels['title'])
@section('document_no', $statement['party']['name'])

@section('content')

<table class="meta">
    <tr>
        <td class="box" width="50%">
            <table width="100%">
                <tr><td class="label">{{ $labels['party'] }}</td><td><strong>{{ $statement['party']['name'] }}</strong></td></tr>
                @if($statement['party']['company'] ?? null)
                    <tr><td class="label">الشركة</td><td>{{ $statement['party']['company'] }}</td></tr>
                @endif
                @if($statement['party']['phone'] ?? null)
                    <tr><td class="label">الهاتف</td><td class="ltr" style="text-align:right;">{{ $statement['party']['phone'] }}</td></tr>
                @endif
            </table>
        </td>
        <td width="4%"></td>
        <td class="box" width="46%">
            <table width="100%">
                {{-- Split across two rows: an Arabic word and a Latin date in the
                     same cell get reordered by the bidi algorithm, which prints
                     "2026-09-09" as "09-09-2026". --}}
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
                <tr><td class="label">العملة</td><td>{{ $company['currency'] }}</td></tr>
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
            <th width="13%">مدين</th>
            <th width="13%">دائن</th>
            <th width="14%">الرصيد</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="num">{{ $statement['period']['from'] ?? '—' }}</td>
            <td colspan="2"><strong>رصيد ما قبله</strong></td>
            <td class="num">—</td>
            <td class="num">—</td>
            <td class="num"><strong>{{ $money($statement['opening_balance']) }}</strong></td>
        </tr>
        @forelse($statement['rows'] as $index => $row)
            <tr class="{{ $index % 2 ? 'zebra' : '' }}">
                <td class="num">{{ $row['transaction_date'] }}</td>
                <td>
                    {{ $row['type_label'] }}
                    @if($row['description'])<div class="muted" style="font-size:7.5pt;">{{ $row['description'] }}</div>@endif
                </td>
                <td class="num">{{ $row['reference_no'] ?? '—' }}</td>
                <td class="num">{{ (float) $row['debit'] ? $money($row['debit']) : '—' }}</td>
                <td class="num">{{ (float) $row['credit'] ? $money($row['credit']) : '—' }}</td>
                <td class="num">{{ $money($row['running_balance']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#888;padding:6mm;">لا توجد حركات في الفترة المحددة</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3">إجمالي الفترة</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['totals']['debit']) }}</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['totals']['credit']) }}</th>
            <th style="text-align:left;direction:ltr;">{{ $money($statement['closing_balance']) }}</th>
        </tr>
    </tfoot>
</table>

<table width="100%" style="margin-top:5mm;">
    <tr>
        <td width="55%"></td>
        <td width="45%">
            <table class="totals" width="100%">
                <tr><td class="label">الرصيد الافتتاحي</td><td class="value">{{ $money($statement['opening_balance']) }}</td></tr>
                <tr><td class="label">إجمالي المدين</td><td class="value">{{ $money($statement['totals']['debit']) }}</td></tr>
                <tr><td class="label">إجمالي الدائن</td><td class="value">{{ $money($statement['totals']['credit']) }}</td></tr>
                <tr class="grand">
                    <td class="label">الرصيد الختامي</td>
                    <td class="value">{{ $money($statement['closing_balance']) }} {{ $company['currency'] }}</td>
                </tr>
            </table>
            <div class="muted" style="font-size:8pt;margin-top:2mm;text-align:left;">{{ $balanceHint }}</div>
        </td>
    </tr>
</table>

@endsection
