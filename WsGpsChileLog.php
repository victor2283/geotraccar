<?php namespace Tobuli\Entities;

use Eloquent;
 
class WsGpsChileLog extends Eloquent 
{
	protected $table = 'ws_gps_chile_log';

    protected $fillable = array(
        'name',
        'client_user',
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
        'ws_response',
        'user_id'
    );

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }
}
