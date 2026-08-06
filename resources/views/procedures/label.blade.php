<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $revision->code }} QR Sign</title>
    <style>
        @page { size: letter portrait; margin: .45in; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .sheet { min-height: 9.9in; border: 4px solid #1c3366; border-radius: 18px; padding: .55in; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .company { color: #1c3366; font-size: 20px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        h1 { max-width: 7in; margin: 22px 0 8px; font-size: 34px; line-height: 1.15; }
        .code { color: #586174; font-size: 20px; font-weight: 700; }
        .qr { width: 4.4in; height: 4.4in; margin: 24px 0 18px; }
        .qr svg { display: block; width: 100%; height: 100%; }
        .scan { font-size: 24px; font-weight: 800; }
        .detail { max-width: 6in; margin-top: 10px; color: #586174; font-size: 16px; }
        .version { margin-top: 22px; padding-top: 18px; border-top: 1px solid #ccd3dc; width: 100%; color: #586174; font-size: 14px; }
        .print { position: fixed; top: 14px; right: 14px; padding: 10px 16px; border: 0; border-radius: 8px; background: #1c3366; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        @media print { .print { display: none; } }
    </style>
</head>
<body>
    <button class="print" onclick="window.print()">Print</button>
    <div class="sheet">
        <div class="company">Christy Vault Company</div>
        <h1>{{ $revision->title }}</h1>
        <div class="code">{{ $revision->code }}</div>
        <div class="qr">{!! $procedure->generateQrCode(900) !!}</div>
        <div class="scan">Scan to view the current procedure</div>
        <div class="detail">The QR code always opens the latest published version. Do not rely on an outdated printed copy.</div>
        <div class="version">Posted for public QR access · Current at printing: {{ $revision->version_label }}, effective {{ $revision->effective_date->format('M j, Y') }}</div>
    </div>
</body>
</html>
