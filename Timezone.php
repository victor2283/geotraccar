<?php namespace Tobuli\Entities;

use Eloquent;

class Timezone extends Eloquent {

    protected $table = 'timezones';

    protected $fillable = ['title', 'zone', 'order', 'prefix', 'time'];

    protected $zoneReversed;
    protected $zoneDST;
    protected $zoneReversedDST;

    public $timestamps = false;

    public function getZoneAttribute($value)
    {
        return $value ?: '+0hours';
    }

    public function getReversedZoneAttribute()
    {
        if (! isset($this->zoneReversed)) {
            $this->zoneReversed = timezoneReverse($this->zone);
        }

        return $this->zoneReversed;
    }

    public function getDSTZoneAttribute()
    {
        if (! isset($this->zoneDST)) {
            $this->zoneDST = $this->zone.' +1hours';
        }

        return $this->zoneDST;
    }

    public function getReversedDSTZoneAttribute()
    {
        if (! isset($this->zoneReversedDST)) {
            $this->zoneReversedDST = $this->reversedZone.' -1hours';
        }

        return $this->zoneReversedDST;
    }
}
