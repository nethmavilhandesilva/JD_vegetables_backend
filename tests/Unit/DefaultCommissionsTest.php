<?php

use App\Support\DefaultCommissions;

test('default commission bands match the agreed price table', function (float $price, ?float $expected) {
    expect(DefaultCommissions::forPrice($price))->toBe($expected);
})->with([
    [0.0, null],      // no price, no commission
    [0.5, 1.00],
    [1.0, 1.00],
    [30.0, 1.00],
    [30.5, 1.00],     // gap between bands uses the lower band
    [31.0, 2.00],
    [49.0, 2.00],
    [49.99, 2.00],
    [50.0, 3.00],
    [99.0, 3.00],
    [100.0, 5.00],
    [199.9, 5.00],
    [200.0, 10.00],
    [299.99, 10.00],
    [300.0, 15.00],
    [399.5, 15.00],
    [400.0, 20.00],
    [500.0, 25.00],
    [600.0, 30.00],
    [700.0, 35.00],
    [800.0, 40.00],
    [899.0, 40.00],
    [900.0, 45.00],
    [1000.0, 50.00],
    [1100.0, 55.00],
    [1200.0, 60.00],
    [1300.0, 65.00],
    [1400.0, 70.00],
    [1500.0, 75.00],
    [1600.0, 80.00],
    [1700.0, 85.00],
    [1800.0, 90.00],
    [1899.9, 90.00],
    [1900.0, 95.00],
    [1999.0, 95.00],
    [2000.0, 95.00],  // the schedule ends at 1900-1999; higher prices keep its amount
    [5000.0, 95.00],
]);

test('every band pays Rs. 5 more per Rs. 100 from 300 up to 1900', function () {
    foreach (range(300, 1900, 100) as $start) {
        expect(DefaultCommissions::forPrice((float) $start))->toBe((float) ($start / 20));
    }
});
