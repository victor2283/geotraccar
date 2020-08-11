<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceGroup extends Eloquent {
	protected $table = 'device_groups';

    protected $fillable = array('title', 'user_id');

    public $timestamps = false;

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User');
    }

    public function devices() {
        return $this->belongsToMany('Tobuli\Entities\Device', 'user_device_pivot', 'group_id', 'device_id');
    }
}
