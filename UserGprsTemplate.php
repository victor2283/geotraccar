<?php namespace Tobuli\Entities;

use Eloquent;

class UserGprsTemplate extends Eloquent {
	protected $table = 'user_gprs_templates';

    protected $fillable = array(
        'user_id',
        'title',
        'message',
        'protocol'
    );

    public function setProtocolAttribute($value)
    {
        if (empty($value))
            $value = null;

        $this->attributes['protocol'] = $value;
    }
}
