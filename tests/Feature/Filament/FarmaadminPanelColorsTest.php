<?php

use Filament\Facades\Filament;

test('farmaadmin panel registers brand color palette', function () {
    $panel = Filament::getPanel('farmaadmin');

    expect($panel->getId())->toBe('farmaadmin');

    $colors = $panel->getColors();

    expect($colors)->toHaveKeys(['primary', 'info', 'success']);
});

test('farmaadmin panel uses league spartan as sans font', function () {
    Filament::setCurrentPanel('farmaadmin');

    expect(Filament::getFontFamily())->toBe('League Spartan');
});

test('farmaadmin panel uses readable brand logo height', function () {
    expect(Filament::getPanel('farmaadmin')->getBrandLogoHeight())->toBe('4.6rem');
});
