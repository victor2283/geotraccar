<?php namespace Tobuli\Entities;

use Eloquent;
use Tobuli\Helpers\Payments\Payments;

class Subscription extends Eloquent
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'gateway',
        'gateway_id',
        'billing_plan_id',
        'expiration_date',
        'active'
    ];

    public function user()
    {
        return $this->hasOne('Tobuli\Entities\User', 'id', 'user_id');
    }

    public function billing_plan()
    {
        return $this->hasOne('Tobuli\Entities\BillingPlan', 'id', 'billing_plan_id');
    }

    public function scopeSubscribable($query)
    {
        $not_subscribable = ['paydunya'];

        return $query->whereNotIn('gateway', $not_subscribable);
    }

    public function cancel()
    {
        $payments = new Payments($this->gateway);

        $success = $payments->cancelSubscription($this);

        if ($success)
            $this->update(['active' => 0]);
    }
}
