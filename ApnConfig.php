<?php

namespace Tobuli\Entities;

use Eloquent;
use Hash;

class ApnConfig extends Eloquent
{
    protected $table = 'apn_config';

    protected $fillable = [
        'name',
        'apn_name',
        'apn_username',
        'apn_password',
        'edited',
        'active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function scopeNotEdited($query)
    {
        return $query->where('edited', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
