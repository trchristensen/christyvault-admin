<!DOCTYPE html>
<html>
<head>
    <title>Test Delivery Links</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .link-item { 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px;
            background: #f9f9f9; 
        }
        .link { 
            color: #2563eb; 
            text-decoration: none; 
            font-weight: bold;
            word-break: break-all;
        }
        .order-info { color: #666; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Test Delivery Links</h1>
    <p>Click these links to test the delivery PWA:</p>
    
    @foreach($links as $linkData)
        <div class="link-item">
            <div class="order-info">
                <strong>{{ $linkData['order']->order_number }}</strong> - 
                {{ $linkData['order']->location->name ?? 'No location' }}
            </div>
            <a href="{{ $linkData['link'] }}" target="_blank" class="link">
                {{ $linkData['link'] }}
            </a>
        </div>
    @endforeach
    
    @if($links->count() == 0)
        <p>No orders found. Create some orders in your admin panel first.</p>
    @endif
</body>
</html> 