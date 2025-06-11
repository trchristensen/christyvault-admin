<x-filament-panels::page>
    <div class="space-y-6">
        <!-- SMS Status Overview -->
        <x-filament::section>
            <x-slot name="heading">
                SMS System Status
            </x-slot>

            @php
                $stats = $this->getSmsStats();
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-users class="h-8 w-8 text-blue-500" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Drivers with Phone</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['drivers_with_phone'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-truck class="h-8 w-8 text-green-500" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Deliveries Today</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['drivers_with_deliveries_today'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            @if($stats['sms_enabled'])
                                <x-heroicon-o-check-circle class="h-8 w-8 text-green-500" />
                            @else
                                <x-heroicon-o-x-circle class="h-8 w-8 text-red-500" />
                            @endif
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">SMS Status</p>
                            <p class="text-lg font-semibold {{ $stats['sms_enabled'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $stats['sms_enabled'] ? 'Enabled' : 'Disabled' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-clock class="h-8 w-8 text-purple-500" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Daily Schedule</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $stats['daily_schedule_time'] ?? 'Not set' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Configuration Info -->
        <x-filament::section>
            <x-slot name="heading">
                Configuration
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4>SMS Settings</h4>
                        <ul class="text-sm space-y-1">
                            <li><strong>SMS Enabled:</strong> {{ $stats['sms_enabled'] ? 'Yes' : 'No' }}</li>
                            <li><strong>Daily Schedule:</strong> {{ $stats['daily_schedule_enabled'] ? 'Enabled' : 'Disabled' }}</li>
                            <li><strong>Schedule Time:</strong> {{ $stats['daily_schedule_time'] ?? 'Not set' }}</li>
                            <li><strong>From Number:</strong> {{ config('sms.telnyx.from_number') ?: 'Not configured' }}</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4>Available Commands</h4>
                        <ul class="text-sm space-y-1">
                            <li><code>HELP</code> - Show available commands</li>
                            <li><code>ORDER [number]</code> - Get order details</li>
                            <li><code>STATUS [number]</code> - Check order status</li>
                            <li><code>DELIVERED [number]</code> - Mark as delivered</li>
                            <li><code>ORDERS</code> or <code>TODAY</code> - Today's orders</li>
                        </ul>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Setup Instructions -->
        @if(!config('sms.telnyx.api_key'))
        <x-filament::section>
            <x-slot name="heading">
                Setup Required
            </x-slot>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">SMS Configuration Required</h3>
                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                            <p>To enable SMS functionality, add these environment variables:</p>
                            <pre class="mt-2 bg-gray-100 dark:bg-gray-800 p-2 rounded text-xs"><code>TELNYX_API_KEY=your_api_key_here
TELNYX_FROM_NUMBER=+1234567890
TELNYX_WEBHOOK_SECRET=your_webhook_secret_here
SMS_ENABLED=true</code></pre>
                            <p class="mt-2">
                                Set up your webhook URL in Telnyx: <code>{{ route('sms.webhook') }}</code>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
        @endif

        <!-- Recent Activity -->
        <x-filament::section>
            <x-slot name="heading">
                Commands
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p>Use the commands above to test SMS functionality or send daily schedules manually.</p>
                
                <h4>Manual Commands</h4>
                <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg">
                    <code class="text-sm">
                        # Send daily schedule to all drivers<br>
                        php artisan sms:daily-schedule<br><br>
                        
                        # Send to specific driver<br>
                        php artisan sms:daily-schedule --driver=123<br><br>
                        
                        # Test without sending<br>
                        php artisan sms:daily-schedule --dry-run
                    </code>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page> 