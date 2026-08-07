<?php

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;

it('uses a native grouped header toolbar for common order actions', function (): void {
    Filament::setCurrentPanel('admin');

    $page = new class extends EditOrder
    {
        public function headerActionsForTest(): array
        {
            return $this->getHeaderActions();
        }
    };

    $group = $page->headerActionsForTest()[0];
    $group->getActions();
    $actions = $group->getFlatActions();

    expect($group)->toBeInstanceOf(ActionGroup::class)
        ->and($group->isButtonGroup())->toBeTrue()
        ->and(array_keys($actions))->toBe([
            'save',
            'cancel',
            'loadSummary',
            'printDeliveryTag',
            'previewDeliveryTag',
            'delete',
        ])->and($actions['save']->isButton())->toBeTrue()
        ->and($actions['save']->isLabelHidden())->toBeFalse()
        ->and($actions['save']->getLabel())->toBe('Save changes')
        ->and($actions['save']->getFormToSubmit())->toBe('save')
        ->and($actions['save']->getFormId())->toBe('form')
        ->and($actions['cancel']->isLabelHidden())->toBeTrue()
        ->and($actions['loadSummary']->isLabelHidden())->toBeTrue()
        ->and($actions['printDeliveryTag']->isLabelHidden())->toBeTrue()
        ->and($actions['previewDeliveryTag']->isLabelHidden())->toBeTrue()
        ->and($actions['delete']->isLabelHidden())->toBeTrue()
        ->and($actions['delete']->getIcon())->toBe('heroicon-o-trash')
        ->and($actions['delete']->getLabel())->toBe('Delete order')
        ->and($actions['delete']->getColor())->toBe('danger')
        ->and($actions['delete']->isConfirmationRequired())->toBeTrue();
});
