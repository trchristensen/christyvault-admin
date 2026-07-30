<x-filament-panels::page>
    <style>
        .sp-dashboard {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .sp-panel {
            overflow: hidden;
            border: 1px solid #dce3ec;
            border-radius: 0.875rem;
            background: #fff;
            box-shadow: 0 1px 2px rgb(15 23 42 / 5%);
        }

        .sp-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.875rem;
        }

        .sp-kpi {
            min-width: 0;
            min-height: 8.75rem;
            padding: 1.125rem 1.25rem;
        }

        .sp-kpi-label {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.055em;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .sp-kpi-value {
            margin-top: 0.65rem;
            color: #0f172a;
            font-size: clamp(1.6rem, 2vw, 2rem);
            font-weight: 750;
            letter-spacing: -0.035em;
            line-height: 1;
        }

        .sp-kpi-detail {
            margin-top: 0.55rem;
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.35;
        }

        .sp-kpi-change-detail {
            margin-top: 0.55rem;
            font-size: 0.8rem;
            font-weight: 650;
        }

        .sp-drilldown {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.8rem 1rem;
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .sp-drilldown-label {
            color: #1d4ed8;
            font-size: 0.68rem;
            font-weight: 750;
            letter-spacing: 0.055em;
            text-transform: uppercase;
        }

        .sp-drilldown-title {
            margin-top: 0.15rem;
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .sp-section {
            padding: 1.25rem;
        }

        .sp-section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .sp-section-title {
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .sp-section-description,
        .sp-data-through {
            margin-top: 0.2rem;
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .sp-data-through {
            flex: none;
            margin: 0;
            font-weight: 600;
            white-space: nowrap;
        }

        .sp-chart {
            position: relative;
            width: 100%;
            height: 25rem;
        }

        .sp-breakdown-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .sp-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .sp-table {
            width: 100%;
            min-width: 58rem;
            border-collapse: collapse;
            color: #334155;
            font-size: 0.84rem;
        }

        .sp-table thead {
            background: #f8fafc;
        }

        .sp-table th {
            padding: 0.7rem 1.25rem;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 750;
            letter-spacing: 0.045em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .sp-table th.sp-number,
        .sp-table td.sp-number {
            text-align: right;
        }

        .sp-table td {
            padding: 0.8rem 1.25rem;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .sp-table tbody tr:hover {
            background: #f8fafc;
        }

        .sp-product {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 14rem;
        }

        .sp-product-code {
            flex: none;
            padding: 0.22rem 0.45rem;
            border-radius: 0.35rem;
            background: #334155;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 750;
            line-height: 1.2;
        }

        .sp-product-name,
        .sp-current-value {
            color: #0f172a;
            font-weight: 700;
        }

        .sp-change-percent {
            margin-top: 0.1rem;
            font-size: 0.7rem;
        }

        .sp-mix {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 10rem;
        }

        .sp-progress {
            flex: 1;
            height: 0.45rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .sp-progress-value {
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }

        .sp-share {
            width: 3.3rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            text-align: right;
        }

        .sp-empty {
            padding: 3rem 1.25rem !important;
            color: #64748b;
            text-align: center;
        }

        .sp-footnote {
            color: #64748b;
            font-size: 0.72rem;
            line-height: 1.5;
        }

        .dark .sp-panel {
            border-color: rgb(255 255 255 / 10%);
            background: #111827;
        }

        .dark .sp-kpi-label,
        .dark .sp-kpi-detail,
        .dark .sp-section-description,
        .dark .sp-data-through,
        .dark .sp-table,
        .dark .sp-table th,
        .dark .sp-share,
        .dark .sp-footnote {
            color: #94a3b8;
        }

        .dark .sp-kpi-value,
        .dark .sp-section-title,
        .dark .sp-product-name,
        .dark .sp-current-value,
        .dark .sp-drilldown-title {
            color: #f8fafc;
        }

        .dark .sp-table thead,
        .dark .sp-table tbody tr:hover {
            background: rgb(255 255 255 / 4%);
        }

        .dark .sp-table td,
        .dark .sp-breakdown-header {
            border-color: rgb(255 255 255 / 8%);
        }

        .dark .sp-progress {
            background: #334155;
        }

        .dark .sp-drilldown {
            border-color: #1e3a8a;
            background: rgb(30 58 138 / 22%);
        }

        @media (max-width: 87rem) {
            .sp-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 56rem) {
            .sp-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sp-section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .sp-data-through {
                white-space: normal;
            }

            .sp-chart {
                height: 21rem;
            }
        }

        @media (max-width: 38rem) {
            .sp-kpi-grid {
                grid-template-columns: 1fr;
            }

            .sp-drilldown {
                align-items: flex-start;
                flex-direction: column;
            }

            .sp-chart {
                height: 18rem;
            }
        }
    </style>

    @php
        $summary = $this->report['summary'] ?? [];
        $breakdown = $this->report['breakdown'] ?? ['level' => 'category', 'rows' => []];
        $period = $this->report['period'] ?? [];
        $change = (float) ($summary['change'] ?? 0);
        $changePercent = $summary['changePercent'] ?? null;
        $changeTone = $change > 0
            ? 'color: rgb(21 128 61)'
            : ($change < 0 ? 'color: rgb(185 28 28)' : 'color: rgb(71 85 105)');
    @endphp

    {{ $this->form }}

    <div class="sp-dashboard">
    @if (($breakdown['level'] ?? 'category') === 'product')
        <div class="sp-panel sp-drilldown">
            <div>
                <p class="sp-drilldown-label">
                    Product drill-down
                </p>
                <p class="sp-drilldown-title">
                    {{ $breakdown['scope'] }}
                </p>
            </div>

            <x-filament::button
                color="gray"
                icon="heroicon-m-arrow-left"
                wire:click="clearProductDrilldown"
            >
                All categories
            </x-filament::button>
        </div>
    @endif

    <div class="sp-kpi-grid">
        <div class="sp-panel sp-kpi">
            <p class="sp-kpi-label">
                {{ $summary['currentLabel'] ?? 'Current period' }}
            </p>
            <p class="sp-kpi-value">
                {{ $this->formatMetricValue($summary['currentValue'] ?? 0) }}
            </p>
            <p class="sp-kpi-detail">
                {{ $this->metric === 'revenue' ? 'Order revenue' : 'Product units ordered' }}
            </p>
        </div>

        <div class="sp-panel sp-kpi">
            <p class="sp-kpi-label">
                {{ $summary['previousLabel'] ?? 'Prior-year period' }}
            </p>
            <p class="sp-kpi-value">
                {{ $this->formatMetricValue($summary['previousValue'] ?? 0) }}
            </p>
            <p class="sp-kpi-detail">
                Same comparison window
            </p>
        </div>

        <div class="sp-panel sp-kpi">
            <p class="sp-kpi-label">
                Year-over-year change
            </p>
            <p class="sp-kpi-value" style="{{ $changeTone }}">
                {{ $this->formatPercent($changePercent, true) }}
            </p>
            <p class="sp-kpi-change-detail" style="{{ $changeTone }}">
                {{ $this->formatMetricValue($change, true) }}
            </p>
        </div>

        <div class="sp-panel sp-kpi">
            <p class="sp-kpi-label">
                Orders
            </p>
            <p class="sp-kpi-value">
                {{ number_format($summary['currentOrders'] ?? 0) }}
            </p>
            <p class="sp-kpi-detail">
                {{ number_format($summary['previousOrders'] ?? 0) }} in prior-year period
            </p>
        </div>

        <div class="sp-panel sp-kpi">
            <p class="sp-kpi-label">
                Completed sales visits
            </p>
            <p class="sp-kpi-value">
                {{ number_format($summary['completedVisits'] ?? 0) }}
            </p>
            <p class="sp-kpi-detail">
                {{ number_format($summary['previousCompletedVisits'] ?? 0) }} in prior-year period
            </p>
        </div>
    </div>

    <section class="sp-panel sp-section">
        <div class="sp-section-header">
            <div>
                <h2 class="sp-section-title">
                    Monthly performance
                </h2>
                <p class="sp-section-description">
                    {{ $this->metric === 'revenue' ? 'Order revenue' : 'Units ordered' }},
                    compared with the same months one year earlier.
                </p>
            </div>

            <p class="sp-data-through">
                Data through {{ $summary['dataThrough'] ?? 'no orders in this period' }}
            </p>
        </div>

        <div
            x-data="{
                chart: null,
                chartData: @js($this->chartData),
                chartMeta: @js($this->report['chartMeta'] ?? []),

                async ensureChartLibrary() {
                    if (typeof Chart !== 'undefined') {
                        return;
                    }

                    if (!window.salesPerformanceChartLoader) {
                        window.salesPerformanceChartLoader = new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
                            script.onload = resolve;
                            script.onerror = reject;
                            document.head.appendChild(script);
                        });
                    }

                    await window.salesPerformanceChartLoader;
                },

                formatValue(value) {
                    if (this.chartMeta.metric === 'revenue') {
                        return new Intl.NumberFormat('en-US', {
                            style: 'currency',
                            currency: 'USD',
                            maximumFractionDigits: 0,
                        }).format(value);
                    }

                    return new Intl.NumberFormat('en-US', {
                        maximumFractionDigits: 0,
                    }).format(value);
                },

                options() {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index',
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                    padding: 18,
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `${context.dataset.label}: ${this.formatValue(context.parsed.y)}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: this.chartMeta.axisLabel ?? 'Products Ordered',
                                },
                                ticks: {
                                    callback: (value) => this.formatValue(value),
                                },
                            },
                        },
                        barPercentage: 0.82,
                        categoryPercentage: 0.72,
                    };
                },

                async initChart() {
                    await this.ensureChartLibrary();
                    this.chart = new Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: this.chartData,
                        options: this.options(),
                    });
                },

                updateChart(chartData, chartMeta) {
                    this.chartData = chartData;
                    this.chartMeta = chartMeta;

                    if (!this.chart) {
                        this.initChart();
                        return;
                    }

                    this.chart.data = chartData;
                    this.chart.options = this.options();
                    this.chart.update();
                },
            }"
            x-init="
                initChart();
                $wire.on('sales-performance-updated', (event) => {
                    const payload = Array.isArray(event) ? event[0] : event;
                    updateChart(payload.chartData, payload.chartMeta);
                });
            "
            wire:ignore
            class="sp-chart"
        >
            <canvas x-ref="canvas"></canvas>
        </div>
    </section>

    <section class="sp-panel">
        <div class="sp-breakdown-header">
            <div>
                <h2 class="sp-section-title">
                    {{ ($breakdown['level'] ?? 'category') === 'category' ? 'Product category mix' : $breakdown['scope'].' products' }}
                </h2>
                <p class="sp-section-description">
                    @if (($breakdown['level'] ?? 'category') === 'category')
                        Open a category to see the actual products driving its totals.
                    @else
                        Specific SKUs within this product category.
                    @endif
                </p>
            </div>
        </div>

        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>
                            Product
                        </th>
                        <th class="sp-number">
                            {{ $summary['currentLabel'] ?? 'Current' }}
                        </th>
                        <th class="sp-number">
                            {{ $summary['previousLabel'] ?? 'Previous' }}
                        </th>
                        <th class="sp-number">
                            Change
                        </th>
                        <th>
                            Current mix
                        </th>
                        @if (($breakdown['level'] ?? 'category') === 'category')
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($breakdown['rows'] ?? [] as $row)
                        @php
                            $rowChangeTone = $row['change'] > 0
                                ? 'color: rgb(21 128 61)'
                                : ($row['change'] < 0 ? 'color: rgb(185 28 28)' : 'color: rgb(71 85 105)');
                        @endphp
                        <tr>
                            <td>
                                <div class="sp-product">
                                    @if (filled($row['code']))
                                        <span class="sp-product-code">
                                            {{ $row['code'] }}
                                        </span>
                                    @endif
                                    <span class="sp-product-name">
                                        {{ $row['label'] }}
                                    </span>
                                </div>
                            </td>
                            <td class="sp-number sp-current-value">
                                {{ $this->formatMetricValue($row['current']) }}
                            </td>
                            <td class="sp-number">
                                {{ $this->formatMetricValue($row['previous']) }}
                            </td>
                            <td class="sp-number" style="{{ $rowChangeTone }}">
                                <div>{{ $this->formatMetricValue($row['change'], true) }}</div>
                                <div class="sp-change-percent">{{ $this->formatPercent($row['changePercent'], true) }}</div>
                            </td>
                            <td>
                                <div class="sp-mix">
                                    <div class="sp-progress">
                                        <div
                                            class="sp-progress-value"
                                            style="width: {{ min(100, max(0, $row['share'])) }}%"
                                        ></div>
                                    </div>
                                    <span class="sp-share">
                                        {{ number_format($row['share'], 1) }}%
                                    </span>
                                </div>
                            </td>
                            @if (($breakdown['level'] ?? 'category') === 'category')
                                <td class="sp-number">
                                    <x-filament::button
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-magnifying-glass-plus"
                                        wire:click="drillIntoCategoryAt({{ $loop->index }})"
                                    >
                                        View products
                                    </x-filament::button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ ($breakdown['level'] ?? 'category') === 'category' ? 6 : 5 }}"
                                class="sp-empty"
                            >
                                No non-cancelled orders were found for this selection.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="sp-footnote">
        Sales are grouped by order date and exclude cancelled orders. Fill-load quantities use delivered quantity first,
        then the saved planned quantity. Sales visits are counted only when marked completed and use their completed date.
    </p>
    </div>
</x-filament-panels::page>
