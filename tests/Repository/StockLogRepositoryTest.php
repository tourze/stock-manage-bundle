<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;
use Tourze\StockManageBundle\Repository\StockLogRepository;

/**
 * @internal
 */
#[CoversClass(StockLogRepository::class)]
#[RunTestsInSeparateProcesses]
class StockLogRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepositoryClass(): string
    {
        return StockLogRepository::class;
    }

    protected function getEntityClass(): string
    {
        return StockLog::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    protected function createNewEntity(): object
    {
        $stockLog = new StockLog();
        $stockLog->setType(StockChange::INBOUND);
        $stockLog->setQuantity(100);
        $stockLog->setRemark('测试用途');

        return $stockLog;
    }

    protected function getRepository(): StockLogRepository
    {
        return self::getService(StockLogRepository::class);
    }

    public function testCanSaveAndRetrieveStockLog(): void
    {
        $repository = $this->getRepository();
        $stockLog = new StockLog();

        // 设置必要的字段以满足验证要求
        $stockLog->setType(StockChange::INBOUND);
        $stockLog->setQuantity(100);
        $stockLog->setRemark('测试用途');

        $repository->save($stockLog);

        self::assertGreaterThan(0, $stockLog->getId());

        $found = $repository->find($stockLog->getId());
        self::assertInstanceOf(StockLog::class, $found);
        self::assertSame($stockLog->getId(), $found->getId());
    }
}
