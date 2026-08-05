<?php

namespace App\Filament\Widgets;

use App\Support\OfficeManagerDashboard;
use Filament\Widgets\Widget;

class OfficeManagerAttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.office-manager-attention-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'items' => app(OfficeManagerDashboard::class)->attentionItems(),
        ];
    }
}
