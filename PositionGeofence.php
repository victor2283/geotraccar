<?php namespace Tobuli\Entities;

use Eloquent;

class PositionGeofence extends Eloquent {
	protected $table = 'position_geofence';

    protected $fillable = array('position_id', 'geofence_id');

    public $timestamps = false;
}
