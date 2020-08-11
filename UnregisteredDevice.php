<?php namespace Tobuli\Entities;

use Eloquent;

class UnregisteredDevice extends Eloquent {
    protected $connection = 'traccar_mysql';

	protected $table = 'unregistered_devices_log';

    protected $primaryKey = 'imei';

    protected $fillable = [
        'imei',
        'port',
        'times',
        'ip',
    ];

    public $timestamps = false;
    public $incrementing = false;

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'imei', 'imei');
    }

    public function scopeLastest($query)
    {
        return $query->orderBy('date', 'desc');
    }
}
