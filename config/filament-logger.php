<?php
return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',

    'activity_resource' => \Z3d0X\FilamentLogger\Resources\ActivityResource::class,
	'scoped_to_tenant' => true,
	'navigation_sort' => null,
    'log_only_dirty' => true,
    'log_attributes' => ['*'],

    'resources' => [
        'enabled' => true,
        'log_name' => 'Resource',
        'logger' => \Z3d0X\FilamentLogger\Loggers\ResourceLogger::class,
        'color' => 'success',
		
        'exclude' => [
            //App\Filament\Resources\UserResource::class,
            App\Filament\Resources\OrderResource::class,
        ],
        'cluster' => null,
        'navigation_group' =>'System',
    ],

    'access' => [
        'enabled' => true,
        'logger' => \Z3d0X\FilamentLogger\Loggers\AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
    ],

    'notifications' => [
        'enabled' => true,
        'logger' => \Z3d0X\FilamentLogger\Loggers\NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
    ],

    'models' => [
        'enabled' => true,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => \Z3d0X\FilamentLogger\Loggers\ModelLogger::class,
        'register' => [
            // App\Models\User::class,
            // App\Models\Order::class,
            // App\Models\OrderProduct::class,
            // App\Models\Location::class,
            // App\Models\Product::class,
            // App\Models\ContactType::class,
            // App\Models\Contact::class,
            // App\Models\Trip::class,
            // App\Models\Employee::class
        ],
    ],

    'custom' => [
        // [
        //     'log_name' => 'Custom',
        //     'color' => 'primary',
        // ]
    ],
];
