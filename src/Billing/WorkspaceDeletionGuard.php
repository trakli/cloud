<?php

namespace Trakli\Cloud\Billing;

use App\Models\Workspace;
use App\Services\WorkspaceFinance;

class WorkspaceDeletionGuard
{
    public function deleting(Workspace $workspace): void
    {
        if (! app(WorkspaceSubscription::class)->canDelete($workspace)) {
            app(WorkspaceFinance::class)->deny('End the workspace subscription and cancel any pending checkout before deleting this space.', 409);
        }
    }
}
