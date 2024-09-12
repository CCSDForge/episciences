<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;

class RemindersTest extends TestCase
{
    public function testDeadlineInterval(): void
    {

        $deadlineDateTime = date_create('2024-09-14 00:00:00');
        $current1 = date_create('2024-09-04 01:23:03');
        $current2 = date_create('2024-09-04 01:23:03');

        $current1->setTime(0, 0);

        $interval1 = $current1->diff($deadlineDateTime, true)->format('%a'); // in days
        $interval2 = $current2->diff($deadlineDateTime, true)->format('%a'); // in days

        self::assertEquals(10, $interval1);
        self::assertEquals(9, $interval2);

    }
}