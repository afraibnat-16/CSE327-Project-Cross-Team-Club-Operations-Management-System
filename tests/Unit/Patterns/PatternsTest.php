<?php

namespace Tests\Unit\Patterns;

use App\Patterns\Builder\SponsorBuilder;
use App\Patterns\Singleton\ActivityLogger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

// Tests for the design patterns. These run without Laravel or a database.
class PatternsTest extends TestCase
{
    // Singleton: asking twice must give back the very same object.
    public function test_logger_is_one_shared_instance(): void
    {
        $this->assertSame(ActivityLogger::getInstance(), ActivityLogger::getInstance());
    }

    // Builder: the steps fill in the data, and an empty builder is refused.
    public function test_builder_builds_and_validates(): void
    {
        $data = (new SponsorBuilder)->name('Kite Coffee')->needsElectricity()->build();
        $this->assertSame('Kite Coffee', $data['organization_name']);
        $this->assertTrue($data['requires_electricity']);

        // No name given, so build() must throw this error.
        $this->expectException(InvalidArgumentException::class);
        (new SponsorBuilder)->build();
    }
}
