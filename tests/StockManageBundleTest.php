<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\StockManageBundle\StockManageBundle;

/**
 * @internal
 */
#[CoversClass(StockManageBundle::class)]
#[RunTestsInSeparateProcesses]
final class StockManageBundleTest extends AbstractBundleTestCase
{
}
