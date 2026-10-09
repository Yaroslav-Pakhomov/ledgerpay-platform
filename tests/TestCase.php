<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param array<string, mixed> $parameters
     */
    protected function runArtisanSuccessfully(string $command, array $parameters = []): void
    {
        $this->runArtisan($command, $parameters)->assertSuccessful();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function runArtisanFailed(string $command, array $parameters = []): void
    {
        $this->runArtisan($command, $parameters)->assertFailed();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function runArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        Assert::assertInstanceOf(PendingCommand::class, $pending);

        $pending->run();

        return $pending;
    }
}
