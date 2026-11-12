<?php

namespace App\Support;

/**
 * Built-in commission bands (commission per kg by sale price per kg).
 *
 * These are the default general commissions and are applied even when the
 * commissions table is empty or unreachable. Item/supplier specific rules in
 * the commissions table still override these; its general rules are not used.
 *
 * Schedule: Rs. 1 at 0-30, Rs. 2 at 31-49, Rs. 3 at 50-99, Rs. 5 at 100-199,
 * Rs. 10 at 200-299, then Rs. 5 more for every further Rs. 100 (300-399 = 15,
 * 400-499 = 20, ...) up to 1900-1999 = 95. The schedule ends at 1900-1999, so
 * any price of Rs. 1900 or more takes that band's Rs. 95.
 *
 * Each band is [starting_price, end_price, commission_amount]; a null end
 * price means "and above".
 */
class DefaultCommissions
{
    public const BANDS = [
        [0.00,    30.00,   1.00],
        [31.00,   49.00,   2.00],
        [50.00,   99.00,   3.00],
        [100.00,  199.00,  5.00],
        [200.00,  299.00,  10.00],
        [300.00,  399.00,  15.00],
        [400.00,  499.00,  20.00],
        [500.00,  599.00,  25.00],
        [600.00,  699.00,  30.00],
        [700.00,  799.00,  35.00],
        [800.00,  899.00,  40.00],
        [900.00,  999.00,  45.00],
        [1000.00, 1099.00, 50.00],
        [1100.00, 1199.00, 55.00],
        [1200.00, 1299.00, 60.00],
        [1300.00, 1399.00, 65.00],
        [1400.00, 1499.00, 70.00],
        [1500.00, 1599.00, 75.00],
        [1600.00, 1699.00, 80.00],
        [1700.00, 1799.00, 85.00],
        [1800.00, 1899.00, 90.00],
        [1900.00, null,    95.00],
    ];

    /**
     * Commission per kg for a sale price, or null when the price is not positive.
     * A price between two bands (e.g. 30.50 between 0-30 and 31-49) uses the lower band.
     */
    public static function forPrice(float $pricePerKg): ?float
    {
        if ($pricePerKg <= 0) {
            return null;
        }

        $match = null;
        foreach (self::BANDS as [$start, $end, $amount]) {
            if ($start > $pricePerKg) {
                continue;
            }
            if ($end !== null && $end < floor($pricePerKg)) {
                continue;
            }
            if ($match === null || $start > $match[0]) {
                $match = [$start, $amount];
            }
        }

        return $match === null ? null : (float) $match[1];
    }
}
