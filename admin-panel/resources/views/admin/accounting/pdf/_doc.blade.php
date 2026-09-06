{{-- Shared statutory-document chrome for dompdf. Include at the top of a PDF blade;
     pass $entity (array from FinancialStatementService::entity()), $docTitle, $docSub. --}}
<style>
    @page { margin: 96px 42px 70px 42px; }
    * { font-family: "DejaVu Serif", "Times New Roman", serif; }
    body { font-size: 11px; color: #111; margin: 0; }
    .doc-head { position: fixed; top: -74px; left: 0; right: 0; text-align: center; }
    .doc-head .co { font-size: 15px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
    .doc-head .meta { font-size: 9px; color: #444; margin-top: 2px; }
    .doc-head hr { border: none; border-top: 1.5px solid #111; margin: 6px 0 0; }
    .doc-foot { position: fixed; bottom: -50px; left: 0; right: 0; font-size: 8px; color: #555; border-top: .5px solid #999; padding-top: 4px; }
    .doc-foot .pnum:after { content: counter(page) " of " counter(pages); }
    h1.doc-title { font-size: 13px; text-align: center; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 2px; }
    p.doc-sub { text-align: center; font-size: 10px; color: #333; margin: 0 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    table.grid td, table.grid th { border: .75px solid #333; padding: 4px 7px; vertical-align: top; }
    table.stmt td { padding: 3px 6px; }
    table.stmt th { padding: 4px 6px; border-bottom: 1.25px solid #111; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
    .num { text-align: right; font-family: "DejaVu Sans Mono", monospace; white-space: nowrap; }
    .rule-top td { border-top: 1px solid #111; }
    .rule-dbl td { border-top: 1px solid #111; border-bottom: 3px double #111; }
    .head-row td { font-weight: bold; padding-top: 8px; }
    .indent { padding-left: 22px !important; }
    .indent2 { padding-left: 40px !important; }
    .muted { color: #555; }
    .sign-grid { margin-top: 40px; }
    .sign-grid td { border: none; width: 50%; font-size: 10px; padding-top: 30px; }
    .note-blk { margin-top: 4px; page-break-inside: avoid; }
    .note-blk .nt { font-weight: bold; font-size: 10px; margin-bottom: 2px; }
</style>

<div class="doc-head">
    <div class="co">{{ $entity['name'] ?? 'Company' }}</div>
    <div class="meta">
        @if(!empty($entity['cin'])) CIN: {{ $entity['cin'] }} &nbsp;|&nbsp; @endif
        @if(!empty($entity['pan'])) PAN: {{ $entity['pan'] }} &nbsp;|&nbsp; @endif
        @if(!empty($entity['gstin'])) GSTIN: {{ $entity['gstin'] }} @endif
        @if(!empty($entity['address'])) <br>{{ $entity['address'] }} @endif
    </div>
    <hr>
</div>

<div class="doc-foot">
    <table><tr>
        <td style="border:none">Generated {{ now()->format('d M Y, H:i') }} · Operational books — subject to statutory audit &amp; adjustments</td>
        <td style="border:none; text-align:right" class="pnum">Page </td>
    </tr></table>
</div>
