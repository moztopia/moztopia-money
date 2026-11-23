<?php

declare(strict_types=1);

namespace Moztopia\Money;

use Moztopia\Money\Exception\FormattingFailure;

/**
 * Present exact decimal strings with explicit or supported locale conventions.
 */

final readonly class MoneyFormatter
{
    /**
     * Define exact grouping and label placement without a numeric formatter.
     */

    public function __construct(
        private string $decimalSeparator = '.',
        private string $groupingSeparator = ',',
        private int $primaryGrouping = 3,
        private int $secondaryGrouping = 3,
        private bool $currencyAfter = false,
        private string $currencySpacing = ' ',
        private bool $useSymbol = false,
        private bool $arabicDigits = false,
    ) {
        if ($decimalSeparator === '' || $decimalSeparator === $groupingSeparator
            || preg_match('/[\p{N}\x00-\x1f]/u', $decimalSeparator.$groupingSeparator) !== 0
            || $primaryGrouping < 1 || $primaryGrouping > 9 || $secondaryGrouping < 1 || $secondaryGrouping > 9) {
            throw new FormattingFailure('Invalid exact formatting separators or grouping sizes.');
        }
    }

    /**
     * Select documented deterministic locale conventions with no required extension.
     */

    public static function forLocale(string $locale, bool $useSymbol = false): self
    {
        return match (str_replace('-', '_', $locale)) {
            'en_US', 'en_GB', 'th_TH' => new self(useSymbol: $useSymbol),
            'de_DE' => new self(',', '.', currencyAfter: true, currencySpacing: "\u{00a0}", useSymbol: $useSymbol),
            'fr_FR' => new self(',', "\u{202f}", currencyAfter: true, currencySpacing: "\u{00a0}", useSymbol: $useSymbol),
            'hi_IN' => new self(secondaryGrouping: 2, useSymbol: $useSymbol),
            'ar_EG' => new self("\u{066b}", "\u{066c}", currencyAfter: true, currencySpacing: "\u{00a0}", useSymbol: $useSymbol, arabicDigits: true),
            default => throw new FormattingFailure('Unsupported exact formatting locale: '.$locale),
        };
    }

    /**
     * Format every digit exactly without changing scale or monetary identity.
     */

    public function format(Money $money): string
    {
        $amount = $money->amount();
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');
        $groups = [];
        $size = $this->primaryGrouping;
        while (strlen($whole) > $size) {
            array_unshift($groups, substr($whole, -$size));
            $whole = substr($whole, 0, -$size);
            $size = $this->secondaryGrouping;
        }
        array_unshift($groups, $whole);
        $number = ($negative ? '-' : '').implode($this->groupingSeparator, $groups);
        if ($fraction !== '') {
            $number .= $this->decimalSeparator.$fraction;
        }
        if ($this->arabicDigits) {
            $number = strtr($number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
            if ($negative) {
                $number = "\u{061c}".$number;
            }
        }
        $label = $this->useSymbol ? $money->currency()->symbol : $money->currency()->code;
        return $this->currencyAfter
            ? $number.$this->currencySpacing.$label
            : $label.$this->currencySpacing.$number;
    }
}
