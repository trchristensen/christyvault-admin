<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that should be used for passwordless authentication.
    | This must implement the Authenticatable contract.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Email Column
    |--------------------------------------------------------------------------
    |
    | The database column used to identify users by email.
    |
    */
    'email_column' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use when logging in users.
    | Set to null to use the default guard.
    |
    */
    'guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Remember Login
    |--------------------------------------------------------------------------
    |
    | Whether to set the "remember me" flag when authenticating.
    |
    */
    'remember' => false,

    /*
    |--------------------------------------------------------------------------
    | Magic Link Token
    |--------------------------------------------------------------------------
    |
    | Configuration for the magic link token.
    |
    */
    'token' => [
        /*
         * Length of the random token (in bytes). The resulting string
         * will be twice this length in hex characters.
         * Minimum: 16, Maximum: 128, Default: 32 (64 hex chars)
         */
        'length' => 32,

        /*
         * Hashing algorithm used to store the token in the database.
         * Options: 'sha256', 'bcrypt', 'argon2'
         * Use 'sha256' for best performance, 'bcrypt'/'argon2' for maximum security.
         */
        'hash_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Link Expiry
    |--------------------------------------------------------------------------
    |
    | Number of minutes before a magic link expires.
    |
    */
    'expiry_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Usage Limit
    |--------------------------------------------------------------------------
    |
    | How many times a single magic link can be used.
    | Set to 1 for one-time use, null for unlimited (until expiry).
    |
    */
    'max_uses' => 1,

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route' => [
        /*
         * The URI path for the magic login endpoint.
         */
        'path' => '/magic-login/{token}',

        /*
         * The route name.
         */
        'name' => 'passwordless.login',

        /*
         * Middleware to apply to the magic login route.
         */
        'middleware' => ['web', 'guest'],

        /*
         * Route prefix (e.g. 'auth' would produce /auth/magic-login/{token}).
         */
        'prefix' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    */
    'redirect' => [
        /*
         * Where to redirect after successful login.
         * Can be a URL string or a route name.
         */
        'on_success' => '/dashboard',

        /*
         * Whether 'on_success' is a route name (true) or a URL (false).
         */
        'on_success_is_route' => false,

        /*
         * Where to redirect on failure (expired/invalid/used link).
         * Can be a URL string or a route name.
         */
        'on_failure' => '/login',

        /*
         * Whether 'on_failure' is a route name (true) or a URL (false).
         */
        'on_failure_is_route' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle / Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for magic link generation to prevent abuse.
    |
    */
    'throttle' => [
        /*
         * Enable or disable rate limiting.
         */
        'enabled' => true,

        /*
         * Maximum number of magic links that can be generated per user
         * within the decay period.
         */
        'max_attempts' => 5,

        /*
         * Decay period in minutes. After this period, the attempt counter resets.
         */
        'decay_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot / Prefetch Detection
    |--------------------------------------------------------------------------
    |
    | Email clients (especially Outlook, Apple Mail) often prefetch or scan
    | links before the user clicks them. This can inadvertently consume
    | one-time links. Enable this to require a confirmation step.
    |
    */
    'bot_detection' => [
        /*
         * Enable bot/prefetch detection.
         */
        'enabled' => true,

        /*
         * Strategy for bot detection:
         * - 'confirmation_page': Show a "Click here to continue" page
         * - 'javascript': Use a JS redirect (bots can't execute JS)
         * - 'both': Use JS with a fallback confirmation button
         */
        'strategy' => 'both',

        /*
         * Known bot/prefetch user-agent patterns to detect.
         */
        'user_agent_patterns' => [
            'Microsoft Office',
            'Microsoft Outlook',
            'Word/',
            'Excel/',
            'ms-office',
            'Outlook-iOS',
            'Outlook-Android',
            'SafeLinks',
            'BTWebClient',
            'GoogleImageProxy',
            'YahooMailProxy',
            'Barracuda/',
            'ProofPoint',
            'ZmEu',
            'wget',
            'curl',
            'python-requests',
            'Go-http-client',
            'Apache-HttpClient',
            'libwww-perl',
        ],

        /*
         * HTTP methods that bots typically use (HEAD is common for link scanning).
         */
        'bot_methods' => ['HEAD', 'OPTIONS'],

        /*
         * Headers that indicate a prefetch request.
         */
        'prefetch_headers' => [
            'X-Purpose' => ['preview', 'prefetch'],
            'Purpose' => ['preview', 'prefetch'],
            'Sec-Purpose' => ['prefetch', 'prerender'],
            'X-Moz' => ['prefetch'],
            'Sec-Fetch-Dest' => ['empty'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification / Email
    |--------------------------------------------------------------------------
    */
    'notification' => [
        /*
         * Whether to use the built-in notification to send the magic link.
         * Set to false if you want to handle sending yourself.
         */
        'enabled' => true,

        /*
         * The notification class to use. Set to null to use the default.
         * Must extend Illuminate\Notifications\Notification.
         */
        // The package default implements ShouldQueue even when `queue` is false.
        // Use our non-queued notification so the login email is sent immediately.
        'class' => \App\Notifications\MagicLoginLink::class,

        /*
         * The mailable class to use instead of a notification.
         * If set, this takes priority over the notification class.
         * Must extend Illuminate\Mail\Mailable.
         */
        'mailable' => null,

        /*
         * Delivery channel: 'mail', 'database', or any custom channel.
         */
        'channel' => 'mail',

        /*
         * Whether to queue the notification/email.
         */
        'queue' => false,

        /*
         * Queue name to use (null for default queue).
         */
        'queue_name' => null,

        /*
         * Queue connection to use (null for default connection).
         */
        'queue_connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditional Authentication
    |--------------------------------------------------------------------------
    |
    | Define conditions that must be met for a user to be authenticated.
    | Each condition is a callable or class that receives the user model
    | and must return true to allow login.
    |
    | Example:
    | 'conditions' => [
    |     fn($user) => $user->is_active,
    |     fn($user) => !$user->is_banned,
    |     \App\Auth\CheckSubscription::class,
    | ],
    |
    */
    'conditions' => [],

    /*
    |--------------------------------------------------------------------------
    | After Login Action
    |--------------------------------------------------------------------------
    |
    | A class or closure that runs after successful authentication.
    | Must implement SpykApp\PasswordlessLogin\Contracts\AfterLoginAction
    | or be a callable that receives (Authenticatable $user, Request $request).
    |
    | Example:
    | 'after_login_action' => \App\Actions\UpdateLastLogin::class,
    |
    */
    'after_login_action' => null,

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | The database table name for storing magic link tokens.
    |
    */
    'table' => 'passwordless_login_tokens',

    /*
    |--------------------------------------------------------------------------
    | Auto-cleanup
    |--------------------------------------------------------------------------
    |
    | Automatically clean up expired tokens.
    |
    */
    'cleanup' => [
        /*
         * Enable scheduled cleanup of expired tokens.
         */
        'enabled' => true,

        /*
         * How often to run cleanup (cron expression).
         */
        'schedule' => 'daily',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        /*
         * Invalidate all previous tokens for a user when a new one is generated.
         */
        'invalidate_previous' => true,

        /*
         * Invalidate all tokens for a user after successful login.
         */
        'invalidate_on_login' => true,

        /*
         * Bind the magic link to the IP address that requested it.
         * When enabled, the link can only be used from the same IP.
         * WARNING: This can cause issues with VPNs, mobile networks, etc.
         */
        'ip_binding' => false,

        /*
         * Bind the magic link to the user agent that requested it.
         */
        'user_agent_binding' => false,

        /*
         * Log all magic link activity for auditing.
         */
        'audit_log' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Views
    |--------------------------------------------------------------------------
    |
    | Override the default views used by the package.
    |
    */
    'views' => [
        /*
         * The confirmation page shown for bot detection.
         */
        'confirmation' => 'passwordless-login::confirmation',

        /*
         * The error page shown for invalid/expired links.
         */
        'error' => null,
    ],

];
