<?php namespace Tobuli\Entities;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TraccarDevice extends Eloquent {
    protected $connection = 'traccar_mysql';

	protected $table = 'devices';

    protected $fillable = array(
        'name',
        'uniqueId',
        'latestPosition_id',
        'lastValidLatitude',
        'lastValidLongitude',
        'device_time',
        'server_time',
        'ack_time',
        'time',
        'speed',
        'other',
        'altitude',
        'power',
        'course',
        'address',
        'protocol',
        'latest_positions'
    );

    public $timestamps = false;

    public function positions()
    {
        $instance = new TraccarPosition();
        $instance->setTable('positions_' . $this->id);

        $foreignKey = $instance->getTable().'.device_id';
        $localKey = 'id';

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    public function getLastConnectionAttribute()
    {
        $timestamp = $this->lastConnectTimestamp;

        if ( ! $timestamp)
            return null;

        return Carbon::createFromTimestamp($timestamp);
    }

    public function getLastConnectTimestampAttribute() {
        return max(strtotime($this->server_time), strtotime($this->ack_time));
    }
}
