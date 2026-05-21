<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public function brokerPriority(): int
    {
        return match ($this) {
            self::Transactional => 10,
            self::Marketing => 1,
        };
    }
}
