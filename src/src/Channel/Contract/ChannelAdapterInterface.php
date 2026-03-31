<?php

declare(strict_types=1);

namespace App\Channel\Contract;

use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryResult;

interface ChannelAdapterInterface
{
    public function send(DeliveryPayload $payload): DeliveryResult;

    public function supports(string $type): bool;
}
