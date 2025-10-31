<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\StockManageBundle\Command\CleanupExpiredReservationsCommand;

/**
 * @internal
 */
#[CoversClass(CleanupExpiredReservationsCommand::class)]
#[RunTestsInSeparateProcesses]
class CleanupExpiredReservationsCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        $kernel = static::$kernel;
        if (null === $kernel) {
            self::fail('Kernel not initialized');
        }

        // Get command from service container
        /** @var CleanupExpiredReservationsCommand $command */
        $command = self::getContainer()->get(CleanupExpiredReservationsCommand::class);

        $application = new Application($kernel);
        $application->add($command);

        $command = $application->find('stock-manage:cleanup-expired-reservations');
        $this->commandTester = new CommandTester($command);
    }

    protected function getCommandClass(): string
    {
        return CleanupExpiredReservationsCommand::class;
    }

    public function testExecuteWithNoExpiredReservations(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('开始清理过期的库存预占记录', $commandTester->getDisplay());
    }

    public function testCommandCanExecuteSuccessfully(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertNotEmpty($output);
    }

    public function testCommandConfiguration(): void
    {
        $command = $this->getCommand();
        self::assertSame('stock-manage:cleanup-expired-reservations', $command->getName());
        self::assertSame('清理过期的库存预占记录', $command->getDescription());
        self::assertStringContainsString('此命令用于清理过期的库存预占记录，建议每分钟执行一次', $command->getHelp());
    }

    public function testCommandCanBeRegistered(): void
    {
        $kernel = static::$kernel;
        if (null === $kernel) {
            self::fail('Kernel not initialized');
        }

        $application = new Application($kernel);
        $command = $this->getCommand();
        $application->add($command);

        $foundCommand = $application->find('stock-manage:cleanup-expired-reservations');
        self::assertInstanceOf(CleanupExpiredReservationsCommand::class, $foundCommand);
    }

    private function getCommand(): CleanupExpiredReservationsCommand
    {
        $service = self::getContainer()->get(CleanupExpiredReservationsCommand::class);
        self::assertInstanceOf(CleanupExpiredReservationsCommand::class, $service);

        return $service;
    }
}
