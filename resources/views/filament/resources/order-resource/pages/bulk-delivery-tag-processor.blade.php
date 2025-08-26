@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Form Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <form wire:submit.prevent="process">
                {{ $this->form }}
                
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
                    <div class="flex justify-end space-x-3">
                        @foreach ($this->getFormActions() as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        @if($this->getShowResults() && $this->getProcessingResults())
            @php
                $results = $this->getProcessingResults();
            @endphp
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Processing Results
                    </h3>
                </div>
                
                <div class="p-6">
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                                {{ $results['total_pages'] }}
                            </div>
                            <div class="text-sm text-blue-700 dark:text-blue-300">Total Pages</div>
                        </div>
                        
                        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                            <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                                {{ $results['processed'] }}
                            </div>
                            <div class="text-sm text-green-700 dark:text-green-300">Processed</div>
                        </div>
                        
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-lg border border-emerald-200 dark:border-emerald-800">
                            <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                                {{ $results['matched'] }}
                            </div>
                            <div class="text-sm text-emerald-700 dark:text-emerald-300">Matched</div>
                        </div>
                        
                        <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-800">
                            <div class="text-2xl font-bold text-amber-900 dark:text-amber-100">
                                {{ count($results['unmatched']) }}
                            </div>
                            <div class="text-sm text-amber-700 dark:text-amber-300">Unmatched</div>
                        </div>
                    </div>

                    <!-- Success Rate -->
                    @if($results['processed'] > 0)
                        @php
                            $successRate = round(($results['matched'] / $results['processed']) * 100, 1);
                        @endphp
                        <div class="mb-6">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                <span>Success Rate</span>
                                <span>{{ $successRate }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                <div class="bg-green-600 h-2 rounded-full transition-all duration-500" 
                                     style="width: {{ $successRate }}%"></div>
                            </div>
                        </div>
                    @endif

                    <!-- Unmatched Tags -->
                    @if(!empty($results['unmatched']))
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">
                                Unmatched Delivery Tags
                            </h4>
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                                <div class="p-4">
                                    <p class="text-sm text-amber-800 dark:text-amber-200 mb-3">
                                        The following delivery tags could not be automatically matched to orders. You may need to manually attach them or check the order numbers.
                                    </p>
                                    <div class="space-y-2">
                                        @foreach($results['unmatched'] as $unmatched)
                                            <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-3 rounded border">
                                                <div class="flex items-center space-x-3">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                        Page {{ $unmatched['page'] }}
                                                    </span>
                                                    <span class="text-sm text-gray-900 dark:text-white">
                                                        Order #{{ $unmatched['order_number'] ?? 'Not found' }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    @if(isset($unmatched['file_path']))
                                                        <a href="{{ Storage::disk('r2')->url($unmatched['file_path']) }}" 
                                                           target="_blank"
                                                           class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded hover:bg-blue-200 transition-colors dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                                                            <x-heroicon-o-eye class="w-3 h-3 mr-1" />
                                                            View
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Errors -->
                    @if(!empty($results['errors']))
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">
                                Processing Errors
                            </h4>
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                <div class="p-4">
                                    <div class="space-y-2">
                                        @foreach($results['errors'] as $error)
                                            <div class="flex items-start space-x-3 bg-white dark:bg-gray-800 p-3 rounded border">
                                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        @if($error['page'] === 'general')
                                                            General Error
                                                        @else
                                                            Page {{ $error['page'] }}
                                                        @endif
                                                    </div>
                                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                        {{ $error['error'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Success Message -->
                    @if($results['matched'] > 0)
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800 p-4">
                            <div class="flex items-center">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-3" />
                                <div class="text-sm text-green-800 dark:text-green-200">
                                    Successfully processed {{ $results['matched'] }} delivery tag{{ $results['matched'] !== 1 ? 's' : '' }} and attached them to their corresponding orders.
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>