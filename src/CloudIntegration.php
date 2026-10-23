<?php

namespace Trakli\Cloud;

use App\Contracts\IntegrationUi;

class CloudIntegration implements IntegrationUi
{
    public function key(): string
    {
        return 'billing';
    }
    public function name(): string
    {
        return 'Premium';
    }
    public function description(): ?string
    {
        return 'Manage your plan, usage and billing.';
    }
    public function category(): string
    {
        return 'billing';
    }
    public function icon(): ?string
    {
        return 'credit-card';
    }
    public function featureKey(): ?string
    {
        return null;
    }
    public function isConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }
    public function ui(): array
    {
        return [
            'slots' => ['settings.integrations', 'settings.tabs'],
            'card' => ['title' => 'Premium', 'cta' => 'Manage plan', 'href' => '/billing'],
            'tab' => [
                'key' => 'billing',
                'title' => 'Plan and billing',
                'component' => 'cloud.settings',
                'order' => 10,
            ],
            'show_when_unconfigured' => true,
        ];
    }
}
