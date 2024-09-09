<?php

declare(strict_types=1);

namespace DotTest\Log\Filter;

use Dot\Log\Exception\InvalidArgumentException;
use Dot\Log\Filter\SuppressFilter;
use PHPUnit\Framework\TestCase;

class SuppressFilterTest extends TestCase
{
    private SuppressFilter $subject;

    public function setUp(): void
    {
        $this->subject = new SuppressFilter();
    }

    public function testWillInstantiate(): void
    {
        $this->assertInstanceOf(SuppressFilter::class, $this->subject);
    }

    public function testWillNotInstantiate(): void
    {
        $this->expectExceptionMessage(
            'Suppress must be a boolean; received "string"'
        );
        $this->expectException(InvalidArgumentException::class);
        new SuppressFilter(['suppress' => 'oups']);
    }

    public function testFilter(): void
    {
        $result = $this->subject->filter([]);

        $this->assertIsBool($result);
    }

    public function testSuppress(): void
    {
        $this->assertNull($this->subject->suppress(true));
    }
}
