<?php 

namespace Tobuli\Entities;

use Eloquent;

class WsGpsChileDevice extends Eloquent 
{
	protected $table = 'ws_gps_chile_devices';
    protected $fillable = array(
        'user_id',
        'device_id'
    );

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'id', 'device_id');
    }
}
