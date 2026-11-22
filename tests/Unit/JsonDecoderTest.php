<?php

declare(strict_types=1);

use Moztopia\Money\Exception\InvalidSerialization;
use Moztopia\Money\Internal\Json\JsonNumber;
use Moztopia\Money\Internal\Json\StrictJson;

it('preserves long decimal and exponent tokens before any PHP float exists', function () {
    $payload = StrictJson::decode('{"rate":35.123456789012345678,"exp":1.234567890123456789e-20,"integer":999999999999999999999999999999,"string":"rate 1.23e5"}');
    expect($payload->rate)->toBeInstanceOf(JsonNumber::class)
        ->and($payload->rate->value)->toBe('35.123456789012345678')
        ->and($payload->exp->value)->toBe('1.234567890123456789e-20')
        ->and($payload->integer->value)->toBe('999999999999999999999999999999')
        ->and($payload->string)->toBe('rate 1.23e5');
});

it('distinguishes empty objects, arrays, strings and literals', function () {
    $json = <<<'JSON'
[{},[],"quote: \" and unicode \u20ac",true,false,null]
JSON;
    $payload = StrictJson::decode($json);
    expect($payload[0])->toBeInstanceOf(stdClass::class)
        ->and($payload[1])->toBe([])
        ->and($payload[2])->toBe('quote: " and unicode €')
        ->and($payload[3])->toBeTrue()
        ->and($payload[4])->toBeFalse()
        ->and($payload[5])->toBeNull();
});

it('rejects invalid JSON syntax and hostile structural payloads', function (string $payload) {
    StrictJson::decode($payload);
})->with([
    '', ' ', '+1', '.1', '01', '1.', '1e', '1e+', '--1', '[1,]', '{"a":1,}',
    '{"a":1,"\\u0061":2}', '{"a":truefalse}', '{"a" 1}', '{a:1}', '{"a":"\\uD800"}',
    '"\\x20"', '"unterminated', '[[[]]', '[1 2]', '{"\\u0000":0}',
    str_repeat('[', 66).str_repeat(']', 66), str_repeat(' ', 2097153),
])->throws(InvalidSerialization::class);
