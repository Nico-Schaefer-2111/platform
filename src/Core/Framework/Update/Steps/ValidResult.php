<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ValidResult
{
    public function __construct(
        private readonly int $offset,
        private readonly int $total,
        private readonly array $args = []
    ) {
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
