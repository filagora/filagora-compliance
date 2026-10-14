<?php

declare(strict_types=1);

use Filagora\Compliance\Tests\NoEntitlementsTestCase;
use Filagora\Compliance\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(NoEntitlementsTestCase::class)->use(RefreshDatabase::class)->in('NoEntitlements');
