<?php

declare(strict_types=1);

it('executes every marked quick-start PHP example against the real public API', function () {
    $document = file_get_contents(dirname(__DIR__, 2).'/docs/QUICKSTART.md');
    preg_match_all('/<!-- executable:php -->\s*```php\n(.*?)\n```/s', $document, $snippets);
    expect($snippets[1])->toHaveCount(3);
    foreach ($snippets[1] as $snippet) {
        (static function (string $code): void {
            eval($code);
        })($snippet);
    }
});
