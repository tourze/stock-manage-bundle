<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\StockManageBundle\Service\ReservationService;

#[AsCommand(name: self::NAME, description: '清理过期的库存预占记录')]
class CleanupExpiredReservationsCommand extends Command
{
    public const NAME = 'stock-manage:cleanup-expired-reservations';

    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('此命令用于清理过期的库存预占记录，建议每分钟执行一次');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('开始清理过期的库存预占记录...');

        try {
            $expiredCount = $this->reservationService->releaseExpiredReservations();

            if ($expiredCount > 0) {
                $io->success("清理完成，共处理 {$expiredCount} 条过期记录");
            } else {
                $io->note('没有发现过期记录');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('清理过期预占记录失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
