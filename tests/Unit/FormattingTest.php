<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormattingTest extends TestCase
{
    public function test_currency_formatting(): void
    {
        $this->assertEquals('Rp 1.000.000', Format::currency(1000000));
        $this->assertEquals('Rp 0', Format::currency(0));
        $this->assertEquals('Rp 25.000', Format::currency(25000));
        $this->assertEquals('-Rp 50.000', Format::currency(-50000));
        $this->assertEquals('Rp 1.500.000', Format::currency('1500000.00'));
    }

    public function test_quantity_formatting(): void
    {
        $this->assertEquals('3', Format::quantity(3.00));
        $this->assertEquals('3', Format::quantity('3.00'));
        $this->assertEquals('3,5', Format::quantity(3.50));
        $this->assertEquals('3,25', Format::quantity(3.25));
        $this->assertEquals('100', Format::quantity(100));
    }

    public function test_percentage_formatting(): void
    {
        $this->assertEquals('30%', Format::percentage(30.00));
        $this->assertEquals('0%', Format::percentage(0));
        $this->assertEquals('12,5%', Format::percentage(12.50));
        $this->assertEquals('15,75%', Format::percentage(15.75));
    }
}
