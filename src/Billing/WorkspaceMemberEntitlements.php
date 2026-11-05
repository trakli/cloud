<?php

namespace Trakli\Cloud\Billing;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Entitlements\Support\AccessResult;
use Whilesmart\Entitlements\Support\PlanEntitlements;

class WorkspaceMemberEntitlements extends PlanEntitlements
{
    public function check(?Model $owner, string $feature): AccessResult
    {
        if ($feature === 'shared_workspaces') {
            if ($owner instanceof User) {
                return AccessResult::allow($feature);
            }
            if ($owner instanceof Workspace) {
                $capacity = app(WorkspaceSubscription::class)->capacity($owner);

                return $capacity > 0 && $owner->members()->distinct()->count('users.id') <= $capacity
                    ? parent::check($owner, $feature)
                    : AccessResult::deny($feature, 'workspace_subscription_inactive');
            }
        }
        $result = parent::check($owner, $feature);
        if ($result->allowed || ! in_array($feature, config('workspace-billing.member_feature_keys', []), true)) {
            return $result;
        }

        return $this->hasWorkspaceAccess($owner, $feature) ? AccessResult::allow($feature) : $result;
    }

    public function limit(?Model $owner, string $key): ?int
    {
        if ($owner instanceof Workspace && $key === 'workspace_members') {
            return app(WorkspaceSubscription::class)->capacity($owner);
        }
        if (array_key_exists($key, config('workspace-billing.limits', [])) && $this->hasWorkspaceAccess($owner)) {
            return config('workspace-billing.limits.'.$key);
        }

        return parent::limit($owner, $key);
    }

    private function hasWorkspaceAccess(?Model $owner, ?string $feature = null): bool
    {
        if (! $owner instanceof User) {
            return false;
        }
        foreach ($owner->workspaces()->where('is_active', true)->where('is_personal', false)->distinct()->get() as $workspace) {
            if ($this->allows($workspace, 'shared_workspaces') && ($feature === null || parent::check($workspace, $feature)->allowed)) {
                return true;
            }
        }

        return false;
    }
}
