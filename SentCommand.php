<?php

namespace Tobuli\Entities;

use Illuminate\Database\Eloquent\Model;

class SentCommand extends Model
{
    protected $table = 'sent_commands';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_imei', 'imei');
    }

    public function actor()
    {
        return $this->morphTo();
    }

    public function template()
    {
        return $this->belongsTo(UserGprsTemplate::class);
    }

    public function stringifiedAttribute($attribute)
    {
        if (empty($this->$attribute))
            return '';

        if (is_string($this->$attribute))
            return $this->$attribute;

        $values = [];

        foreach ($this->$attribute as $key => $parameter)
            $values[] = "$key: $parameter";

        return implode(',', $values);
    }

    public function getCommandTitleAttribute()
    {
        if ($this->command != 'template')
            return $this->command;

        if ( ! $this->template)
            return $this->command;

        return $this->command . ' ' . $this->template->title;
    }
}
