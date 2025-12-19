<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

final class DuplicateBatchException extends \RuntimeException
{
    public static function withBatchNo(string $batchNo): self
    {
        return new self(sprintf('Batch with number %s already exists', $batchNo));
    }
}
