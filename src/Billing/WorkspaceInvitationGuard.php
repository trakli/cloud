<?php

namespace Trakli\Cloud\Billing;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Entitlements\Contracts\Entitlements;
use Whilesmart\Workspaces\Contracts\InvitationGuard;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

class WorkspaceInvitationGuard implements InvitationGuard
{
    public function beforeInvite(Workspace $workspace, string $email, string $role, ?Authenticatable $inviter): void
    {
        abort_unless(app(Entitlements::class)->allows($workspace, 'shared_workspaces'), 402, 'Activate the workspace subscription before inviting members.');
    }

    public function beforeAccept(WorkspaceInvitation $invitation, Authenticatable $user): void
    {
        $model = config('workspaces.workspace_model');
        $workspace = $model::whereKey($invitation->workspace_id)->lockForUpdate()->firstOrFail();
        abort_unless(app(Entitlements::class)->allows($workspace, 'shared_workspaces'), 402, 'The workspace subscription is inactive.');
        if ($user->belongsToWorkspace($workspace)) {
            return;
        }
        $capacity = app(Entitlements::class)->limit($workspace, 'workspace_members');
        abort_if($workspace->members()->distinct()->count('users.id') >= $capacity, 402, 'The owner must add a member to the subscription before you can join.');
    }
}
