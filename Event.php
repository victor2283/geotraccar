<?php namespace Tobuli\Entities;

use Eloquent;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Formatter;

class Event extends Eloquent
{
    const TYPE_FUEL_FILL  = 'fuel_fill';
    const TYPE_FUEL_THEFT = 'fuel_theft';
    const TYPE_EXPIRED_DEVICE = 'expired_device';
    const TYPE_EXPIRING_DEVICE = 'expiring_device';
    const TYPE_EXPIRING_USER = 'expiring_user';
    const TYPE_EXPIRED_USER = 'expired_user';
    const TYPE_ZONE_IN = 'zone_in';
    const TYPE_ZONE_OUT = 'zone_out';
    const TYPE_DRIVER = 'driver';
    const TYPE_OVERSPEED = 'overspeed';
    const TYPE_OFFLINE_DURATION = 'offline_duration';
    const TYPE_STOP_DURATION = 'stop_duration';
    const TYPE_IDLE_DURATION = 'idle_duration';
    const TYPE_IGNITION_DURATION = 'ignition_duration';
    const TYPE_SOS = 'sos';
    const TYPE_CUSTOM = 'custom';

    protected $table = 'events';

    protected $fillable = array(
        'user_id',
        'geofence_id',
        'position_id',
        'alert_id',
        'device_id',
        'type',
        'message',
        'latitude',
        'longitude',
        'time',
        'speed',
        'altitude',
        'power',
        'address',
        'deleted',
        'additional'
    );

    protected $casts = [
        'additional' => 'array'
    ];

    protected $appends = [
        'name',
        'detail'
    ];

    public function geofence() {
        return $this->hasOne('Tobuli\Entities\Geofence', 'id', 'geofence_id');
    }

    public function alert() {
        return $this->hasOne('Tobuli\Entities\Alert', 'id', 'alert_id');
    }

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'id', 'device_id');
    }

    public function position()
    {
        $instance = new \Tobuli\Entities\TraccarPosition();

        $instance->setTable('positions_' . $this->device->traccar_device_id);

        return new HasOne($instance->newQuery(), $this, 'id', 'position_id');
    }

    public function getMessageAttribute($value)
    {
        return $this->formatMessage();
    }

    public function getDetailAttribute() {
        $detail = null;

        switch($this->type) {
            case Event::TYPE_ZONE_IN:
            case Event::TYPE_ZONE_OUT:
                $detail = $this->geofence ? $this->geofence['name'] : null;
                break;
            case Event::TYPE_DRIVER:
                $detail = array_get($this->additional, 'driver_name');
                break;
            case Event::TYPE_OVERSPEED:
                $speed  = array_get($this->additional, 'overspeed_speed', 0);
                $detail = Formatter::speed()->human($speed);
                break;
            case Event::TYPE_STOP_DURATION:
                $duration = array_get($this->additional, 'stop_duration', 0);
                $detail   = $duration.' '. trans('front.minutes');
                break;
            case Event::TYPE_OFFLINE_DURATION:
                $duration = array_get($this->additional, 'offline_duration', 0);
                $detail   = $duration.' '. trans('front.minutes');
                break;
            case Event::TYPE_IDLE_DURATION:
                $duration = array_get($this->additional, 'idle_duration', 0);
                $detail   = $duration.' '. trans('front.minutes');
                break;
            case Event::TYPE_IGNITION_DURATION:
                $duration = array_get($this->additional, 'ignition_duration', 0);
                $detail   = $duration.' '. trans('front.minutes');
                break;
            case Event::TYPE_FUEL_FILL:
            case Event::TYPE_FUEL_THEFT:
                $difference = array_get($this->additional, 'difference', 0);
                $detail     = $difference;
                break;
        }

        return $detail;
    }

    public function getNameAttribute()
    {
        switch($this->type) {
            case Event::TYPE_ZONE_IN:
            case Event::TYPE_ZONE_OUT:
                $name = trans('front.'.$this->type);
                break;
            case Event::TYPE_DRIVER:
                $name = trans('front.driver');
                break;
            case Event::TYPE_OVERSPEED:
                $name = trans('front.overspeed');
                break;
            case Event::TYPE_STOP_DURATION:
                $name = trans('validation.attributes.stop_duration_longer_than');
                break;
            case Event::TYPE_OFFLINE_DURATION:
                $name = trans('validation.attributes.offline_duration_longer_than');
                break;
            case Event::TYPE_IDLE_DURATION:
                $name = trans('validation.attributes.idle_duration_longer_than');
                break;
            case Event::TYPE_IGNITION_DURATION:
                $name = trans('validation.attributes.ignition_duration_longer_than');
                break;
            case Event::TYPE_FUEL_FILL:
                $name = trans('front.fuel_fillings');
                break;
            case Event::TYPE_FUEL_THEFT:
                $name = trans('front.fuel_thefts');
                break;
            case Event::TYPE_SOS:
                $name = 'SOS';
                break;
            default:
                $name = empty($this->attributes['message']) ? null : $this->attributes['message'];
        }

        return $name;
    }

    public function getTypeTitleAttribute()
    {
        return array_get(self::getTypeTitle($this->type), 'title', '-');
    }

    public function getTimeWithMessageAttribute()
    {
        return Formatter::time()->human($this->time) . ' - ' . $this->message;
    }

    public static function getTypeTitle($type)
    {
        $types = collect(self::getTypeTitles());

        return $types
            ->where('type', $type)
            ->first();
    }

    public static function getTypeTitles()
    {
        return [
            [
                'type' => Event::TYPE_ZONE_IN,
                'title' => trans('front.'.Event::TYPE_ZONE_IN),
            ],
            [
                'type' => Event::TYPE_ZONE_OUT,
                'title' => trans('front.'.Event::TYPE_ZONE_OUT),
            ],
            [
                'type' => Event::TYPE_DRIVER,
                'title' => trans('front.driver'),
            ],
            [
                'type' => Event::TYPE_OVERSPEED,
                'title' => trans('front.overspeed'),
            ],
            [
                'type' => Event::TYPE_STOP_DURATION,
                'title' => trans('front.stop_duration'),
            ],
            [
                'type' => Event::TYPE_OFFLINE_DURATION,
                'title' => trans('front.offline_duration'),
            ],
            [
                'type' => Event::TYPE_IDLE_DURATION,
                'title' => trans('front.idle_duration'),
            ],
            [
                'type' => Event::TYPE_IGNITION_DURATION,
                'title' => trans('front.ignition_duration'),
            ],
            [
                'type' => Event::TYPE_FUEL_FILL,
                'title' => trans('front.fuel_fillings'),
            ],
            [
                'type' => Event::TYPE_FUEL_THEFT,
                'title' => trans('front.fuel_thefts'),
            ],
            [
                'type' => Event::TYPE_SOS,
                'title' => 'SOS',
            ],
            [
                'type' => Event::TYPE_CUSTOM,
                'title' => trans('front.custom_events'),
            ],
        ];
    }

    public function formatMessage()
    {
        $detail = $this->detail;

        return $this->name . ($detail ? " ($detail)" : "");
    }

    public function toArrayMassInsert()
    {
        return array_intersect_key(
            $this->getAttributes(),
            array_flip(array_merge($this->getFillable(), $this->getDates()))
        );
    }
}
