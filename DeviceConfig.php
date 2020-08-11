<?php

namespace Tobuli\Entities;

use Eloquent;
use Hash;

class DeviceConfig extends Eloquent
{
    protected $table = 'device_config';

    protected $fillable = [
        'brand',
        'model',
        'commands',
        'edited',
        'active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'commands' => 'array',
    ];

    public function getFullNameAttribute()
    {
        return trim($this->brand.' '.$this->model);
    }

    public function scopeNotEdited($query)
    {
        return $query->where('edited', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
