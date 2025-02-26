<x-filament-panels::page>
    {{ $this->form }}

    <div class="p-4 mb-4 bg-white rounded-lg shadow">
        <h3 class="mb-4 text-lg font-bold">Debug Information</h3>

        <div class="mb-4">
            <h4 class="font-semibold">Current Settings:</h4>
            <pre class="p-2 bg-gray-100 rounded">
Location ID: {{ $this->locationId }}
Timeframe: {{ $this->timeframe }}
            </pre>
        </div>

        <div class="mb-4">
            <h4 class="font-semibold">Sales Data ({{ $this->salesResults?->count() ?? 0 }} records):</h4>
            <pre class="p-2 bg-gray-100 rounded">
                @json($this->salesResults ?? [], JSON_PRETTY_PRINT)
            </pre>
        </div>

        <div class="mb-4">
            <h4 class="font-semibold">Visits Data ({{ $this->visitsResults?->count() ?? 0 }} records):</h4>
            <pre class="p-2 bg-gray-100 rounded">
                @json($this->visitsResults ?? [], JSON_PRETTY_PRINT)
            </pre>
        </div>
    </div>

    <div class="p-4 bg-white rounded-lg shadow">
        <h3 class="mb-4 text-lg font-bold">Performance Chart</h3>
        <div x-data="{
            chart: null,
            chartData: @js($this->chartData),

            async initChart() {
                console.log('initChart called');
                if (typeof Chart === 'undefined') {
                    await new Promise((resolve) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                        script.onload = resolve;
                        document.head.appendChild(script);
                    });
                }

                await new Promise(resolve => setTimeout(resolve, 0));

                console.log('Creating chart with data:', this.chartData);

                this.chart = new Chart($refs.canvas, {
                    type: 'bar',
                    data: this.chartData,
                    options: {
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
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            x: {
                                stacked: false,
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                stacked: true,
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Products Ordered'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Visits'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        },
                        barPercentage: 0.8,
                        categoryPercentage: 0.9
                    }
                });
            },

            updateChartData(newData) {
                console.log('updateChartData called with:', newData);

                if (!this.chart) {
                    console.error('No chart instance');
                    return;
                }

                if (!newData || !Array.isArray(newData.datasets)) {
                    console.error('Invalid chart data structure:', newData);
                    return;
                }

                try {
                    // Destroy existing chart
                    this.chart.destroy();

                    // Create new chart with updated data
                    this.chart = new Chart($refs.canvas, {
                        type: 'bar',
                        data: {
                            datasets: newData.datasets,
                            labels: newData.labels
                        },
                        options: {
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
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                }
                            },
                            scales: {
                                x: {
                                    stacked: false,
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    stacked: true,
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Products Ordered'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Number of Visits'
                                    },
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    });

                    console.log('Chart updated successfully');
                } catch (error) {
                    console.error('Error updating chart:', error);
                }
            }
        }" x-init="initChart();
        $wire.on('chartDataUpdated', (event) => {
            console.log('Received chartDataUpdated event:', event);
            if (event.chartData) {
                updateChartData(event.chartData);
            } else {
                console.error('No chart data in event:', event);
            }
        });" wire:ignore class="relative" style="height: 400px;">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
</x-filament-panels::page>
