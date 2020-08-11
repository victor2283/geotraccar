<?php namespace Tobuli\Entities;

use Eloquent;

class TraccarPosition extends Eloquent {
    const VIRTUAL_ENGINE_HOURS_KEY = 'enginehours';

    protected $connection = 'traccar_mysql';

	protected $table = 'positions';

    protected $fillable = [
        'altitude',
        'course',
        'latitude',
        'longitude',
        'other',
        'power',
        'speed',
        'time',
        'device_time',
        'server_time',
        'valid',
        'protocol',
        'distance',

        'sensors_values',
        'parameters',
    ];

    public $timestamps = false;

    public function getTable()
    {
        if ($this->device_id) {
            return 'positions_' . $this->device_id;
        }

        if (isset($this->table)) {
            return $this->table;
        }
        return str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));
    }

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'traccar_device_id', 'device_id');
    }

    public function scopeLastest($query)
    {
        return $query->orderBy('time', 'desc');
    }

    public function scopeOrderliness($query)
    {
        return $query->orderBy('time', 'desc')->orderBy('id', 'desc');
    }

    public function getSpeedAttribute($value)
    {
        return float($value);
    }

    public function getParameter($key, $default = null)
    {
        $parameters = $this->parameters;

        return array_key_exists($key, $parameters) ? $this->parameters[$key] : $default;
    }

    public function setParameter($key, $value)
    {
        $parameters = $this->parameters;

        $parameters[$key] = $value;

        $this->parameters = $parameters;
    }

    public function setParametersAttribute($value)
    {
        if ( is_array($value))
        {
            $xml = '<info>';

            foreach ($value as $key => $val)
            {
                if (is_numeric($key)) continue;
                if (is_array($val)) continue;

                $val = is_bool($val) ? ($val ? 'true' : 'false') : $val;
                $val = html_entity_decode($val);
                $xml .= "<{$key}>{$val}</$key>";
            }
            $xml .= '</info>';

            $value = $xml;
        }

        $this->attributes['other'] = $value;
    }

    public function getParametersAttribute()
    {
        if (empty($this->attributes['other']))
            return [];

        $value = $this->attributes['other'];

        return parseXMLToArray($value);
    }

    public function isRfid($rfid)
    {
        if (empty($rfid))
            return false;

        switch ($this->protocol)
        {
            case 'teltonika':
                return $rfid == $this->rfid || $rfid == $this->rfidRaw;
                break;
            default:
                return $rfid == $this->rfid;
        }
    }

    public function getRfids()
    {
        $rfids = [];

        if ( ! $this->rfidRaw)
            return $rfids;

        switch ($this->protocol)
        {
            case 'teltonika':
                $rfids[] = $this->rfid;
                $rfids[] = $this->rfidRaw;
                break;
            case 'meitrack':
                $rfids[] = $this->rfid;
                $rfids[] = $this->rfidRaw;
                break;
            default:
                $rfids[] = $this->rfid;
                break;
        }

        return $rfids;
    }

    public function getRfidAttribute()
    {
        $rfid = $this->getRfidRawAttribute();

        if ($rfid) {
            switch ($this->protocol)
            {
                case 'teltonika':
                    $rfid = teltonikaIbutton($rfid);
                    break;
                case 'meitrack':
                    $rfid = hexdec($rfid);
                    break;
            }
        }

        return (string)$rfid;
    }

    public function getRfidRawAttribute()
    {
        $parameters = $this->parameters;

        $rfid = empty($parameters['rfid']) ? null : $parameters['rfid'];

        if ( ! $rfid)
        {
            switch ($this->protocol)
            {
                case 'teltonika':
                    $rfid = empty($parameters['io78']) ? null : $parameters['io78'];
                    break;
                case 'fox':
                    $rfid = empty($parameters['status-data']) ? null : $parameters['status-data'];
                    break;
                case 'ruptela':
                    $rfid = empty($parameters['io34']) ? null : $parameters['io34'];
                    $rfid = (is_null($rfid) && ! empty($parameters['io171'])) ? $parameters['io171'] : $rfid;
                    break;
            }
        }

        if ( ! $rfid && ! empty($parameters['driveruniqueid']))
            $rfid = $parameters['driveruniqueid'];

        return $rfid;
    }

    public function getSensorsValuesAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setSensorsValuesAttribute($value)
    {
        $this->attributes['sensors_values'] = json_encode($value);
    }

    public function getSensorValue($sensor_id)
    {
        $sensors = $this->sensors_values;

        if (empty($sensors))
            return null;

        if ( ! is_array($sensors))
            return null;

        foreach ($sensors as $sensor)
        {
            if ($sensor['id'] == $sensor_id)
                return $sensor['val'];
        }

        return null;
    }

    public function isValid()
    {
        return $this->valid > 0 ? true : false;
    }

    public function getVirtualEngineHours()
    {
        return $this->getParameter(self::VIRTUAL_ENGINE_HOURS_KEY, 0);
    }
}
