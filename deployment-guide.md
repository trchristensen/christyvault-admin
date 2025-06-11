# ChristyVault Delivery PWA - Deployment Guide

## 1. Hosting Options

### Option A: Netlify (Recommended - Dead Simple)
```bash
# 1. Create account at netlify.com
# 2. Connect your git repo or drag/drop the driver-pwa folder
# 3. Deploy automatically gets HTTPS: https://cv-delivery.netlify.app
```

### Option B: Vercel
```bash
# 1. Install Vercel CLI
npm i -g vercel

# 2. Deploy from driver-pwa directory
cd driver-pwa
vercel --prod
```

### Option C: Your Own Server (cPanel/Apache)
```bash
# 1. Upload driver-pwa files to: yoursite.com/delivery/
# 2. Ensure HTTPS is enabled (required for PWA)
# 3. Add these Apache .htaccess rules:

# .htaccess in /delivery/ folder:
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /delivery/index.html [L]

# Enable HTTPS redirect
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 2. Link Shortener Setup

### Option A: Simple Laravel Route (In your main app)
Add to `routes/web.php`:
```php
Route::get('/d/{code}', function ($code) {
    // Decode the shortened URL
    $data = base64_decode($code);
    $params = json_decode($data, true);
    
    if (!$params || !isset($params['order'], $params['token'])) {
        abort(404);
    }
    
    $deliveryUrl = "https://delivery.yoursite.com?" . http_build_query($params);
    return redirect($deliveryUrl);
});

// Helper function to generate short links
function generateDeliveryLink($orderId, $token) {
    $data = base64_encode(json_encode([
        'order' => $orderId,
        'token' => $token
    ]));
    
    return "https://yoursite.com/d/" . $data;
}
```

### Option B: Custom Short Domain
```bash
# 1. Register short domain: cv.co, cvdel.co, etc.
# 2. Point to same server as main site
# 3. Create separate vhost:

# Apache VirtualHost for cv.co
<VirtualHost *:443>
    ServerName cv.co
    DocumentRoot /path/to/shortener
    
    # Simple PHP redirect script
</VirtualHost>
```

## 3. Text Message Integration

### SMS with Short Links:
```php
// In your Laravel app - SMS service
function sendDeliveryText($driver, $orders) {
    $links = [];
    foreach ($orders as $order) {
        $token = generateSecureToken($order->id, $driver->id);
        $shortLink = generateDeliveryLink($order->id, $token);
        $links[] = "Order #{$order->order_number}: {$shortLink}";
    }
    
    $message = "Your deliveries today:\n" . implode("\n", $links);
    
    // Send via Twilio, etc.
    sendSMS($driver->phone, $message);
}
```

## 4. Laravel API Routes Needed

Add these to your `routes/api.php`:
```php
// Get order details for delivery
Route::get('/orders/{order}/delivery', [DeliveryController::class, 'show'])
    ->middleware('auth:delivery-token');

// Complete delivery
Route::post('/orders/{order}/complete', [DeliveryController::class, 'complete'])
    ->middleware('auth:delivery-token');

// Upload photos
Route::post('/orders/{order}/photos', [DeliveryController::class, 'uploadPhotos'])
    ->middleware('auth:delivery-token');
```

## 5. Foreman Assignment Interface

Simple URL for foreman: `https://yoursite.com/assign-deliveries`

```php
// Quick assignment page
Route::get('/assign-deliveries', function () {
    $drivers = Employee::whereHas('positions', fn($q) => 
        $q->where('name', 'driver'))->get();
    
    $orders = Order::whereNull('driver_id')
        ->where('status', 'ready_for_delivery')
        ->with('location')
        ->get();
    
    return view('assign-deliveries', compact('drivers', 'orders'));
});

Route::post('/assign-deliveries', function (Request $request) {
    foreach ($request->assignments as $orderId => $driverId) {
        if ($driverId) {
            Order::find($orderId)->update(['driver_id' => $driverId]);
        }
    }
    
    // Send SMS to drivers
    sendDeliveryTexts();
    
    return back()->with('success', 'Deliveries assigned!');
});
```

## 6. Example Text Messages

**Long URL (current):**
```
Your delivery: https://delivery.yoursite.com/?order=ORD-00123&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**With Short Link:**
```
Order #123: https://cv.co/d/abc123
Order #124: https://cv.co/d/def456
```

**Even Shorter:**
```
Deliveries: cv.co/d/abc123, cv.co/d/def456
```

## 7. PWA Installation

Drivers can "install" the PWA:
1. Open link in browser
2. "Add to Home Screen" option appears
3. Creates app icon on phone
4. Works offline, feels like native app

Total bundle size: ~25KB (loads in 1-2 seconds even on 2G) 