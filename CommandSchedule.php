<?php

namespace Tobuli\Entities;

use Illuminate\Database\Eloquent\Model;
use Tobuli\Traits\SentCommandActor;

class CommandSchedule extends Model
{
    use SentCommandActor;

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function schedule()
    {
        return $this->morphOne(Schedule::class, 'subject');
    }

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'command_schedule_device')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMessageAttribute()
    {
        return array_get($this->parameters, 'message');
    }

    public function getParameter($name)
    {
        return array_get($this->parameters, $name);
    }

    public function getParametersStringAttribute()
    {
        $values = [];

        if (empty($this->parameters))
            return '';

        $parameters = $this->parameters;

        foreach ($parameters as $key => $parameter)
            $values[] = "$key: $parameter";

        return implode(',', $values);
    }

}
