<div class="space-y-6">
    <div class="space-y-4">
        <h3 class="text-lg font-medium">Delivery Calendar</h3>
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="font-mono text-sm break-all">
                {{ $url }}
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <h3 class="text-lg font-medium">Team Calendar</h3>
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="font-mono text-sm break-all">
                {{ auth()->user()->getLeaveCalendarFeedUrl() }}
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="text-lg font-medium">How to Subscribe</h3>

        <div class="space-y-2">
            <h4 class="font-medium">Apple Calendar</h4>
            <ol class="list-decimal list-inside space-y-1 text-sm">
                <li>Open Calendar app</li>
                <li>Go to File > New Calendar Subscription</li>
                <li>Paste either URL above</li>
                <li>Click Subscribe</li>
            </ol>
        </div>

        <div class="space-y-2">
            <h4 class="font-medium">Google Calendar</h4>
            <ol class="list-decimal list-inside space-y-1 text-sm">
                <li>Open Google Calendar</li>
                <li>Click the + next to "Other calendars"</li>
                <li>Select "From URL"</li>
                <li>Paste either URL above</li>
                <li>Click "Add calendar"</li>
            </ol>
        </div>
    </div>
</div>
