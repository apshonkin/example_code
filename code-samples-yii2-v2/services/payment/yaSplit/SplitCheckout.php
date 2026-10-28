<?php

declare(strict_types=1);

namespace app\services\payment\yaSplit;

/** Заказ, созданный в рассрочке: чем он там называется и куда вести клиента. */
final readonly class SplitCheckout
{
    public function __construct(public string $transaction, public string $url)
    {
    }
}
