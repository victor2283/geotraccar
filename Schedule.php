<?php

namespace Tobuli\Entities;

use Eloquent;
use Formatter;

class Schedule extends Eloquent
{
    const TYPE_EXACT_TIME = 'exact_time';
    const TYPE_HOURLY = 'hourly';
    const TYPE_DAILY = 'daily';
    const TYPE_WEEKLY = 'weekly';
    const TYPE_MONTHLY = 'monthly';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array'
    ];

    public function getParameter($name)
    {
        return array_get($this->parameters, $name);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function command()
    {
        return $this->hasOne(CommandSchedule::class);
    }

    public function getScheduleAtAttribute($value)
    {
        if (is_null($user = auth()->user()))
            return $value;

        return Formatter::time()->convert($value);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeMustRun($query)
    {
        return $query->where('schedule_at', '<=', date('Y-m-d H:i:s'))
            ->where(function ($q) {
                $q->whereRaw('schedules.schedule_at > schedules.last_run_at')
                    ->orWhereNull('last_run_at');
            });
    }
}
