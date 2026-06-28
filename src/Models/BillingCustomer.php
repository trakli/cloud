<?php

namespace Trakli\Cloud\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Billable;

class BillingCustomer extends Model
{
    use Billable;

    protected $table = 'billing_customers';

    protected $fillable = [
        'user_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the default foreign key name for the model.
     * Overridden to use 'user_id' for Cashier relations (like subscriptions).
     */
    public function getForeignKey()
    {
        return 'user_id';
    }
}
