<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Load summary — {{ $trip->trip_number }}</title>
    <style>
        @page {
            size: Letter landscape;
            margin: .2in;
        }

        html,
        body {
            background: #fff;
            color: #172033;
            margin: 0;
            padding: 0;
        }

        .cv-print-toolbar {
            align-items: center;
            background: #f8fafc;
            border-bottom: 1px solid #d9dee8;
            display: flex;
            font-family: ui-sans-serif, system-ui, sans-serif;
            gap: 12px;
            justify-content: space-between;
            padding: 12px 18px;
        }

        .cv-print-toolbar-title {
            font-size: 15px;
            font-weight: 750;
        }

        .cv-print-toolbar button {
            background: #1c3366;
            border: 0;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 8px 14px;
        }

        .cv-print-document-heading {
            align-items: baseline;
            display: flex;
            font-family: ui-sans-serif, system-ui, sans-serif;
            gap: 10px;
            margin: 12px 14px 8px;
        }

        .cv-print-document-heading strong {
            font-size: 18px;
        }

        .cv-print-document-heading span {
            color: #657085;
            font-size: 12px;
        }

        .cv-print-content {
            padding: 0 14px 14px;
        }

        @media print {
            .cv-print-toolbar {
                display: none;
            }

            .cv-print-document-heading {
                margin: 0 0 5px;
            }

            .cv-print-document-heading strong {
                font-size: 13px;
            }

            .cv-print-document-heading span {
                font-size: 9px;
            }

            .cv-print-content {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="cv-print-toolbar">
        <div class="cv-print-toolbar-title">Print preview — {{ $trip->trip_number }}</div>
        <button type="button" onclick="window.print()">Print load diagram</button>
    </div>

    <div class="cv-print-document-heading">
        <strong>Load summary</strong>
        <span>
            {{ $trip->trip_number }}
            @if ($trip->scheduled_date)
                · {{ $trip->scheduled_date->format('M j, Y') }}
            @endif
            @php
                $printOrderNumbers = $trip->orderedDeliveryOrders()
                    ->pluck('order_number')
                    ->filter()
                    ->join(' · ');
            @endphp
            @if ($printOrderNumbers)
                · {{ $printOrderNumbers }}
            @endif
        </span>
    </div>

    <div class="cv-print-content">
        @include('filament.resources.trip-resource.load-summary', [
            'printMode' => true,
        ])
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 150);
            });
        </script>
    @endif
</body>
</html>
