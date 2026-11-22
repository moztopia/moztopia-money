<?php

declare(strict_types=1);

use Moztopia\Money\CurrencyRegistry;

it('validates the published Laravel custom configuration fragment', function () {
    $document = file_get_contents(dirname(__DIR__, 2).'/docs/LARAVEL.md');
    preg_match('/```php\n(.*?)\n```/s', $document, $snippet);
    $config = eval('return ['.$snippet[1].'];');
    $this->app->make('config')->set('moztopia-money.custom_currencies', $config['custom_currencies']);
    $definition = $this->app->make(CurrencyRegistry::class)->get('example.org/rewards', 'POINT', '1');
    expect($definition->minorUnits)->toBe(3);
    expect(array_values($config['currency_fields']['asset']))->toBe(['asset_code', 'asset_registry', 'asset_version', 'asset_scale']);
});
