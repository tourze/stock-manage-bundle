<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

final class BatchNotFoundException extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Batch with ID %s not found', $id));
    }

    public static function withBatchNo(string $batchNo): self
    {
        return new self(sprintf('Batch with number %s not found', $batchNo));
    }
}
