<?php namespace Tobuli\Entities;

use Eloquent;

class GeofenceGroup extends Eloquent {
	protected $table = 'geofence_groups';

    protected $fillable = array('title', 'user_id');

    public $timestamps = false;

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }
}
