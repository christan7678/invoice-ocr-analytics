<?php

namespace App\Support;

class Currency
{
    public static function common(): array
    {
        return [
            'MYR' => 'Malaysian Ringgit',
            'USD' => 'US Dollar',
            'SGD' => 'Singapore Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'AUD' => 'Australian Dollar',
            'CNY' => 'Chinese Yuan',
            'JPY' => 'Japanese Yen',
            'THB' => 'Thai Baht',
            'IDR' => 'Indonesian Rupiah',
        ];
    }
}
