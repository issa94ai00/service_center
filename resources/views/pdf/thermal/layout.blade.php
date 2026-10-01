{{-- Shell for receipts printed on a thermal roll (80 mm or 58 mm).

     A thermal head prints black only and the band is narrow, so this is one
     column, no fills, and rules drawn as dashed "tear" lines. The page height
     is fitted to the content by the renderer, so there is no page footer: the
     print timestamp closes the receipt instead. --}}
@php($narrow = $paper === \App\Enums\PaperSize::THERMAL_58)
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: xbriyaz, sans-serif; font-size: {{ $narrow ? '7.5pt' : '8.5pt' }}; color: #000; }
    .center { text-align: center; }
    .ltr { direction: ltr; }
    .muted { color: #333; }
    .small { font-size: {{ $narrow ? '6.5pt' : '7.5pt' }}; }

    .company-name { font-size: {{ $narrow ? '10.5pt' : '12.5pt' }}; font-weight: bold; text-align: center; }
    .company-meta { font-size: {{ $narrow ? '6.5pt' : '7.5pt' }}; text-align: center; line-height: 1.4; }
    .doc-title { font-size: {{ $narrow ? '9.5pt' : '11pt' }}; font-weight: bold; text-align: center; margin-top: 1.5mm; }
    .doc-no { font-size: {{ $narrow ? '9pt' : '10.5pt' }}; font-weight: bold; text-align: center; direction: ltr; }

    .cut { border-top: 0.6px dashed #000; height: 0; margin: 2mm 0; }
    .cut-strong { border-top: 1px solid #000; height: 0; margin: 2mm 0; }

    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 0.5mm 0; vertical-align: top; }
    table.kv td.k { color: #333; white-space: nowrap; width: {{ $narrow ? '17mm' : '22mm' }}; }
    table.kv td.v { text-align: left; }
    table.kv tr.grand td { font-size: {{ $narrow ? '9.5pt' : '11pt' }}; font-weight: bold; padding-top: 1.2mm; }

    table.items { width: 100%; border-collapse: collapse; }
    table.items th { font-weight: bold; text-align: right; padding-bottom: 1mm; border-bottom: 0.6px solid #000; }
    table.items td { padding: 0.4mm 0; vertical-align: top; }
    table.items td.desc { padding-top: 1.2mm; font-weight: bold; }
    table.items td.num, table.items th.num { text-align: left; direction: ltr; }

    .amount-box { border: 1px solid #000; padding: 2mm; text-align: center; margin: 1.5mm 0; }
    .amount { font-size: {{ $narrow ? '12pt' : '14pt' }}; font-weight: bold; direction: ltr; }

    .block-title { font-weight: bold; margin-top: 1mm; }
    .notes { margin-top: 1.5mm; }
    .cancelled { border: 1px solid #000; padding: 1.5mm; text-align: center; font-weight: bold; margin-top: 2mm; }
    .terms { font-size: {{ $narrow ? '6pt' : '7pt' }}; line-height: 1.4; }
    .signature { margin-top: 7mm; border-top: 0.6px dotted #000; padding-top: 1mm; text-align: center; }
    .footer-note { text-align: center; font-weight: bold; }
</style>
</head>
<body>

<div class="company-name">{{ $company['name'] }}</div>
<div class="company-meta">
    @if($company['address'])<div>{{ $company['address'] }}</div>@endif
    @if($company['phone'])<div>هاتف: <span class="ltr">{{ $company['phone'] }}</span></div>@endif
    @if($company['tax_number'])<div>الرقم الضريبي: <span class="ltr">{{ $company['tax_number'] }}</span></div>@endif
</div>

<div class="cut-strong"></div>

<div class="doc-title">@yield('title')</div>
@hasSection('document_no')
    <div class="doc-no">@yield('document_no')</div>
@endif

<div class="cut"></div>

@yield('content')

@if(!empty($print['footer_note']))
    <div class="cut"></div>
    <div class="footer-note">{!! nl2br(e($print['footer_note'])) !!}</div>
@endif

<div class="cut"></div>
{{-- Split from its label: an Arabic word and a Latin timestamp in one run are
     reordered by the bidi algorithm. --}}
<table class="kv small muted">
    <tr><td class="k">طُبع في</td><td class="v ltr">{{ $printedAt }}</td></tr>
</table>

</body>
</html>
