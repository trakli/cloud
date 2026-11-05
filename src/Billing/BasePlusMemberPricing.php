<?php

namespace Trakli\Cloud\Billing;

use InvalidArgumentException;
use Trakli\Cloud\Contracts\WorkspacePricing;

class BasePlusMemberPricing implements WorkspacePricing
{
    public function quote(int $members): array
    {
        $config = config('workspace-billing');
        $included = (int) $config['included_members'];
        $base = (int) $config['base_amount_cents'];
        $additional = (int) $config['additional_member_amount_cents'];
        $max = (int) $config['max_members'];
        if ($included < 1 || $base < 1 || $additional < 0 || $max < $included) {
            throw new InvalidArgumentException('Invalid workspace pricing configuration.');
        }
        if ($members < $included || $members > $max) {
            throw new InvalidArgumentException("Choose between {$included} and {$max} members, including the owner.");
        }

        return [
            'currency' => $config['currency'],
            'interval' => 'month',
            'members' => $members,
            'included_members' => $included,
            'max_members' => $max,
            'base_amount_cents' => $base,
            'additional_member_amount_cents' => $additional,
            'amount_cents' => $base + max(0, $members - $included) * $additional,
        ];
    }
}
