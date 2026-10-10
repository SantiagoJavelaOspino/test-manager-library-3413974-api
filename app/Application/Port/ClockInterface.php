<?php

declare(strict_types=1);

namespace App\Application\Port;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
