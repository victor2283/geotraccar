<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceExpense extends Eloquent
{
    protected $table = 'device_expenses';

    protected $guarded = [];

    protected $appends = ['total'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalAttribute()
    {
        return $this->quantity * $this->unit_cost;
    }

    public function type()
    {
        return $this->hasOne(DeviceExpensesType::class, 'id', 'type_id');
    }
}
