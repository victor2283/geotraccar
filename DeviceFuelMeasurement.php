<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceFuelMeasurement extends Eloquent {
	protected $table = 'device_fuel_measurements';

    protected $fillable = array('title, fuel_title', 'distance_title', 'lang');
}
