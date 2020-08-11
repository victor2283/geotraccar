<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceService extends Eloquent {
	protected $table = 'device_services';

    protected $fillable = array(
        'user_id',
        'device_id',
        'name',
        'expiration_by',
        'interval',
        'last_service',
        'trigger_event_left',
        'renew_after_expiration',
        'expires',
        'expires_date',
        'remind',
        'remind_date',
        'event_sent',
        'expired',
        'email',
        'mobile_phone',
        'description',
    );

    protected $sensor;
    protected $sensors;

    public $timestamps = false;

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'id', 'device_id');
    }

    public function user() {
        return $this->hasOne('Tobuli\Entities\User', 'id', 'user_id');
    }

    public function checklists()
    {
        return $this->hasMany('Tobuli\Entities\Checklist', 'service_id');
    }

    public function setSensors($sensors)
    {
        $this->sensors = $sensors;
    }

    public function getLeftAttribute()
    {
        return $this->getLeft();
    }

    public function getPercentageAttribute()
    {
        return $this->getPercentage();
    }

    public function getLeft()
    {
        switch ($this->expiration_by)
        {
            case 'days':
                return dateDiff($this->expires_date, date('Y-m-d'));

            case 'odometer':
            case 'engine_hours':
                if ( ! $sensor = $this->getSensor())
                    return null;

                return $this->expires - $sensor->getValueCurrent();

            default:
                return null;
        }
    }

    public function left_formated()
    {
        $left  = $this->getLeft();

        if (is_null($left))
            return '-';

        if ($left < 0 && ! env('NEGATIVE_DEVICE_SERVICE'))
            return trans('front.expired');

        switch ($this->expiration_by)
        {
            case 'days':
                return $left . 'd.';

            case 'odometer':
            case 'engine_hours':
                $sensor = $this->getSensor();

                return round($left) . $sensor->unit_of_measurement;

            default:
                return '-';
        }
    }

    public function expires_formated()
    {
        switch ($this->expiration_by)
        {
            case 'days':
                return $this->expires_date;

            case 'odometer':
            case 'engine_hours':
                $sensor = $this->getSensor();

                return round($this->expires) . ($sensor->unit_of_measurement ?? '');

            default:
                return null;
        }
    }

    public function expiration()
    {
        $left   = $this->getLeft();
        $sensor = $this->getSensor();

        switch ($this->expiration_by)
        {
            case 'days':
                return  $left > 0 || env('NEGATIVE_DEVICE_SERVICE')
                    ? trans('validation.attributes.days').' '.trans('front.left').' ('.$this->left_formated().')'
                    : trans('validation.attributes.days').' '.strtolower(trans('front.expired'));

            case 'odometer':
                if ( ! $sensor)
                    return dontExist('front.sensor');

                return  $left > 0 || env('NEGATIVE_DEVICE_SERVICE')
                    ? trans('front.odometer').' '.trans('front.left').' ('.$this->left_formated().')'
                    : trans('front.odometer').' '.strtolower(trans('front.expired'));

            case 'engine_hours':
                if ( ! $sensor)
                    return dontExist('front.sensor');

                return  $left > 0 || env('NEGATIVE_DEVICE_SERVICE')
                    ? trans('validation.attributes.engine_hours').' '.trans('front.left').' ('.$this->left_formated().')'
                    : trans('validation.attributes.engine_hours').' '.strtolower(trans('front.expired'));

            default:
                return null;
        }
    }

    public function isExpiring()
    {
        return $this->getLeft() <= $this->trigger_event_left;
    }

    public function isExpired()
    {
        return $this->getLeft() <= 0;
    }

    public function getPercentage()
    {
        $left = $this->getLeft();

        if (empty($left))
            return 0;

        if (empty($this->interval))
            return 0;

        $percentage = $left * 100 / $this->interval;

        if ( $percentage < 0 )
            $percentage = 0;

        if ( $percentage > 100 )
            $percentage = 100;

        return round($percentage);
    }

    private function getSensor()
    {
        if (isset($this->sensor))
            return $this->sensor;

        if ($this->sensors) {
            switch ($this->expiration_by)
            {
                case 'odometer':
                    return $this->sensor = $this->getSensorByType('odometer');
                case 'engine_hours':
                    return $this->sensor = $this->getSensorByType('engine_hours');
                default:
                    return $this->sensor = null;
            }
        } else {
            switch ($this->expiration_by)
            {
                case 'odometer':
                    return $this->sensor = $this->device->getOdometerSensor();
                case 'engine_hours':
                    return $this->sensor = $this->device->getEngineHoursSensor();
                default:
                    return $this->sensor = null;
            }
        }
    }

    private function getSensorByType($type)
    {
        if (empty($this->sensors))
            return null;

        foreach ($this->sensors as $sensor) {
            if ($sensor['type'] == $type) {
                $type_sensor = $sensor;
                break;
            }
        }

        if (empty($type_sensor))
            return null;

        return $type_sensor;
    }

    public function scopeNotSend($query)
    {
        return $query->where('device_services.event_sent', 0);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('device_services.expired', 0);
    }

    public function scopeExpireByDays($query, $value)
    {
        return $query
            ->select('device_services.*')
            ->join('users', 'device_services.user_id', '=', 'users.id')
            ->join('timezones', 'users.timezone_id', '=', 'timezones.id')
            ->where('device_services.expiration_by', 'days')
            ->whereRaw("
                IF( timezones.prefix = 'plus', 
                NOW() + INTERVAL timezones.time HOUR_MINUTE, 
                NOW() - INTERVAL timezones.time HOUR_MINUTE ) > DATE($value)
                ")
            ->groupBy('device_services.id');
    }

    public function scopeExpireByOdometer($query, $value)
    {
        return $query
            ->select('device_services.*')
            ->join('devices', 'device_services.device_id', '=', 'devices.id')
            ->join('device_sensors as sensors', function ($query) {
                $query->on('devices.id', '=', 'sensors.device_id');
                $query->where('sensors.type', '=', 'odometer');
            })
            ->where('device_services.expiration_by', 'odometer')
            ->whereRaw("((
                sensors.odometer_value_by = 'virtual_odometer' AND ((sensors.odometer_value_unit = 'km' && sensors.odometer_value >= $value) OR (sensors.odometer_value_unit = 'mi' && (sensors.odometer_value * 0.621371192) >= $value))
                ) OR (
                sensors.odometer_value_by = 'connected_odometer' AND sensors.value_formula >= $value
            ))")
            ->groupBy('device_services.id');
    }

    public function scopeExpireByEngineHours($query, $value)
    {
        return $query
            ->select('device_services.*')
            ->join('devices', 'device_services.device_id', '=', 'devices.id')
            ->join('device_sensors as sensors', function ($query) {
                $query->on('devices.id', '=', 'sensors.device_id');
                $query->where('sensors.type', '=', 'engine_hours');
            })
            ->where('device_services.expiration_by', 'engine_hours')
            ->whereRaw("sensors.value >= $value")
            ->groupBy('device_services.id');
    }
}
