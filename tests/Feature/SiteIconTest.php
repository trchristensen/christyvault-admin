<?php

use Illuminate\Support\Facades\Blade;

it('ships the complete Christy Vault browser icon set', function () {
    foreach ([
        'favicon.ico',
        'favicon.svg',
        'apple-touch-icon.png',
        'icon-192.png',
        'icon-512.png',
        'site.webmanifest',
    ] as $asset) {
        expect(public_path($asset))->toBeFile()
            ->and(filesize(public_path($asset)))->toBeGreaterThan(0);
    }

    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toHaveKey('name', 'Christy Vault')
        ->toHaveKey('theme_color', '#1c3366')
        ->and(collect($manifest['icons'])->pluck('src')->all())
        ->toBe(['/icon-192.png', '/icon-512.png']);
});

it('renders browser, Apple, and app icon metadata', function () {
    $html = Blade::render('<x-site-icons />');

    expect($html)
        ->toContain('favicon.ico')
        ->toContain('favicon.svg')
        ->toContain('apple-touch-icon.png')
        ->toContain('site.webmanifest')
        ->toContain('name="theme-color" content="#1c3366"');
});

it('adds the icon set to every Filament panel login page', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('favicon.svg', escape: false)
        ->assertSee('apple-touch-icon.png', escape: false)
        ->assertSee('site.webmanifest', escape: false);
})->with([
    'admin' => '/login',
    'team' => '/team/login',
    'maintenance' => '/maintenance/login',
    'operations' => '/operations/login',
    'sales' => '/sales/login',
]);
