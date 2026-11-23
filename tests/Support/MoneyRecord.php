<?php

declare(strict_types=1);

namespace Moztopia\Money\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Moztopia\Money\Laravel\Casts\MoneyCast;

/**
 * Exercise synthetic storage without introducing package-owned database entities.
 */

final class MoneyRecord extends Model
{
    protected $table = 'money_records';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    /**
     * Declare exact storage types and currency sources in the consuming model.
     *
     * @return array<string, string> Explicit monetary casts.
     */

    protected function casts(): array
    {
        return [
            'minor' => MoneyCast::class.':minor,THB,int64',
            'decimal_text' => MoneyCast::class.':decimal,THB,text',
            'variable' => MoneyCast::class.':minor,column:currency_code,int64',
            'custom' => MoneyCast::class.':minor,custom:rewards,text',
            'identity' => MoneyCast::class.':minor,fields:asset,text',
        ];
    }
}
