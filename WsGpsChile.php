<?php namespace Tobuli\Entities;

use Eloquent;
 
class WsGpsChile extends Eloquent 
{
	protected $table = 'ws_gps_chile';

    protected $fillable = array(
        'name',
        'client_user',
        'client_password',
        'fleet_code',
        'fleet_name',
        'group_code',
        'group_name',
        'operator_digit',
        'operator_name',
        'operator_rut',
        'provider_digit',
        'provider_name',
        'provider_rut',
        'providergps_digit',
        'providergps_name',
        'providergps_rut',
        'email',
        'manager',
        'note',
        'status',
        'user_id'
    );

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }
}
