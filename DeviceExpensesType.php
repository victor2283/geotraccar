<?php namespace Tobuli\Entities;

use Eloquent;
use Illuminate\Support\Facades\Cache;

class DeviceExpensesType extends Eloquent
{

    public static function boot()
    {
        parent::boot();

        static::creating(function($model) {
            Cache::forget('expenses_types_count');
        });

        static::deleting(function($model) {
            Cache::forget('expenses_types_count');
        });
    }

    protected $table = 'device_expense_types';

    protected $guarded = [];
}
