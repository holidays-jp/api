<?php

use Cake\Chronos\Chronos;
use HolidaysJP\holidaysJP;
use PHPUnit\Framework\TestCase;


/**
 * Class SportsDayTest
 */
class SportsDayTest extends TestCase
{
    public function testSportsDay()
    {
        // 2019年までは体育の日、2020年以降はスポーツの日
        $holidays = [];
        foreach (range(2018, 2021) as $year) {
            $holidays[$year] = file_get_contents(__DIR__ . "/../docs/v1/{$year}/date.json");
        }

        $this->assertStringContainsString('体育の日', $holidays[2018]);
        $this->assertStringContainsString('体育の日', $holidays[2019]);
        $this->assertStringNotContainsString('体育の日', $holidays[2020]);
        $this->assertStringNotContainsString('体育の日', $holidays[2021]);
        $this->assertStringNotContainsString('スポーツの日', $holidays[2018]);
        $this->assertStringNotContainsString('スポーツの日', $holidays[2019]);
        $this->assertStringContainsString('スポーツの日', $holidays[2020]);
        $this->assertStringContainsString('スポーツの日', $holidays[2021]);
    }
}
