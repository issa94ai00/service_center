{{-- Shared shell for every printable document. mPDF supports a limited CSS
     subset, so this deliberately uses tables and inline-ish rules rather than
     flexbox or grid. --}}
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: xbriyaz, sans-serif; font-size: 9pt; color: #111; }
    .muted { color: #666; }
    .ltr { direction: ltr; }

    table.sheet { width: 100%; border-collapse: collapse; }

    .company-name { font-size: 15pt; font-weight: bold; }
    .company-meta { font-size: 8pt; color: #555; line-height: 1.5; }

    .doc-title { font-size: 13pt; font-weight: bold; }
    .doc-no { font-size: 10pt; direction: ltr; }

    .rule { border-bottom: 1px solid #333; height: 1px; }

    table.meta { width: 100%; border-collapse: collapse; margin-top: 4mm; }
    table.meta td { padding: 1.5mm 2mm; font-size: 8.5pt; vertical-align: top; }
    table.meta td.label { color: #666; width: 22mm; }
    table.meta .box { border: 0.5px solid #ccc; background: #fafafa; }

    table.lines { width: 100%; border-collapse: collapse; margin-top: 5mm; }
    table.lines th {
        background: #f1f5f9; border: 0.5px solid #cbd5e1; padding: 2mm 1.5mm;
        font-size: 8pt; font-weight: bold; text-align: right;
    }
    table.lines td { border: 0.5px solid #dbe2ea; padding: 1.8mm 1.5mm; font-size: 8.5pt; }
    table.lines td.num { text-align: left; direction: ltr; }
    table.lines tr.zebra td { background: #fbfcfd; }

    table.totals { border-collapse: collapse; margin-top: 4mm; }
    table.totals td { padding: 1.5mm 3mm; font-size: 9pt; }
    table.totals td.label { color: #555; }
    table.totals td.value { text-align: left; direction: ltr; font-weight: bold; }
    table.totals tr.grand td { border-top: 1px solid #333; font-size: 11pt; }

    /* mPDF applies neither width nor padding to a styled div here, but its
       table engine honours both — so the badge is a one-cell table. */
    table.status-badge { border-collapse: collapse; width: 28mm; }
    table.status-badge td {
        padding: 1.4mm 2mm; text-align: center;
        font-size: 8pt; font-weight: bold; border: 0.5px solid;
    }
    table.status-badge td.status-posted { background: #e7f6ec; color: #12683a; border-color: #9fd7b4; }
    table.status-badge td.status-draft { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    table.status-badge td.status-cancelled { background: #fdecec; color: #a11212; border-color: #f0a9a9; }
    table.status-badge td.status-received { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    table.status-badge td.status-diagnosing { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    table.status-badge td.status-under_repair { background: #fefce8; color: #a16207; border-color: #fde68a; }
    table.status-badge td.status-waiting_approval { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    table.status-badge td.status-ready { background: #ecfeff; color: #0e7490; border-color: #a5f3fc; }
    table.status-badge td.status-delivered { background: #e7f6ec; color: #12683a; border-color: #9fd7b4; }

    .notes { margin-top: 5mm; font-size: 8.5pt; color: #444; }
    .signatures { margin-top: 14mm; width: 100%; }
    .signatures td.sign { font-size: 8.5pt; color: #555; padding-top: 10mm; border-top: 0.5px solid #999; text-align: center; }

    .watermark-cancelled { color: #a11212; font-size: 11pt; font-weight: bold; }
</style>
</head>
<body>

<table class="sheet">
    <tr>
        <td width="60%" style="vertical-align:top;">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-meta">
                @if($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if($company['phone'])<div>هاتف: <span class="ltr">{{ $company['phone'] }}</span></div>@endif
                @if($company['email'])<div class="ltr" style="text-align:right;">{{ $company['email'] }}</div>@endif
                @if($company['tax_number'])<div>الرقم الضريبي: <span class="ltr">{{ $company['tax_number'] }}</span></div>@endif
            </div>
        </td>
        <td width="40%" style="vertical-align:top;text-align:left;">
            <div class="doc-title">@yield('title')</div>
            @hasSection('document_no')
                <div class="doc-no">@yield('document_no')</div>
            @endif
            @hasSection('status')
                <div style="margin-top:2mm;">@yield('status')</div>
            @endif
        </td>
    </tr>
</table>

<div class="rule" style="margin-top:3mm;"></div>

@yield('content')

</body>
</html>
