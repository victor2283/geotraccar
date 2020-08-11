<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceSensor extends Eloquent {
    protected $table = 'device_sensors';

    protected $fillable = array(
        'user_id',
        'device_id',
        'name',
        'type',
        'tag_name',
        'add_to_history',
        'on_value',
        'off_value',
        'shown_value_by',
        'fuel_tank_name',
        'full_tank',
        'full_tank_value',
        'min_value',
        'max_value',
        'formula',
        'odometer_value_by',
        'odometer_value',
        'odometer_value_unit',
        'value',
        'value_formula',
        'show_in_popup',
        'unit_of_measurement',
        'on_tag_value',
        'off_tag_value',
        'on_type',
        'off_type',
        'calibrations',
        'skip_calibration'
    );

    public $timestamps = false;

    private $setflagfields = [];

    private $cacheCalibrations;

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'id', 'device_id');
    }

    public function getTypeTitleAttribute($value)
    {
        if ( ! $this->type)
            return null;

        return config("tobuli.sensors.{$this->type}");
    }

    public function getOdometerValueAttribute($value)
    {
        if ($this->odometer_value_unit == 'mi')
            return kilometersToMiles($value);

        return $value;
    }

    public function getUnitOfMeasurementAttribute($value)
    {
        if ($this->type == 'gsm')
            $value = '%';
        elseif ($this->type == 'battery' && $this->shown_value_by == 'min_max_values')
            $value = '%';

        return $value;
    }

    public function setCalibrationsAttribute($value)
    {
        $this->attributes['calibrations'] = serialize($value);
    }

    public function getCalibrationsAttribute($value)
    {
        return unserialize($value);
    }

    public function getHashAttribute($value)
    {
        return md5($this->type . $this->name);
    }

    public function setValue($value)
    {
        if (is_array($value))
            return false;

        if (is_object($value))
            return false;

        if (env('SENSOR_REMOTE', false))
            $this->sendSensorChange($value);

        $this->value = $value;

        if ($this->type == 'odometer' && $this->odometer_value_by == 'connected_odometer')
            $this->value_formula = $value;

        return true;
    }

    public function getSetflag($field)
    {
        if ( ! empty($this->setflagfields[$this->id]) && array_key_exists($field, $this->setflagfields[$this->id]))
            return $this->setflagfields[$this->id][$field];

        $data = null;

        if ('formula' == $field) {
            preg_match('/\%SETFLAG\[([0-9]+)\,([0-9]+)\]\%/', $this->{$field}, $match);
            if (isset($match['1']) && isset($match['2'])) {
                $data = [
                    'start'   => $match['1'],
                    'count'   => $match['2'],
                    'formula' => str_replace($match['0'], '[value]', $this->{$field})
                ];
            }
        } else {
            preg_match('/\%SETFLAG\[([0-9]+)\,([0-9]+)\,([\s\S]+)\]\%/', $this->{$field}, $match);
            if (isset($match['1']) && isset($match['2']) && isset($match['3'])) {
                $data = [
                    'start' => $match['1'],
                    'count' => $match['2'],
                    'value' => $match['3']
                ];
            }
        }

        return $this->setflagfields[$this->id][$field] = $data;
    }

    public function getValueType()
    {
        switch ($this->type)
        {
            case 'gsm':
            case 'gps':
            case 'fuel_tank':
            case 'fuel_tank_calibration':
            case 'odometer':
            case 'tachometer':
            case 'numerical':
            case 'load':
            case 'speed_ecm':
                return 'integer';
                break;

            case 'battery':
            case 'temperature':
            case 'temperature_calibration':
                return 'float';
                break;

            case 'acc':
            case 'ignition':
            case 'engine':
            case 'door':
            case 'seatbelt':
            case 'drive_business':
            case 'drive_private':
            case 'route_color':
            case 'logical':
            case 'harsh_acceleration':
            case 'harsh_breaking':
                return 'boolean';
                break;
            default:
                return null;
        }
    }

    public function isPositionValue()
    {
        return $this->isBooleanValue() || in_array($this->type, ['odometer', 'engine_hours']);
    }

    public function isUpdatable()
    {
        return $this->isBooleanValue() || in_array($this->type, [
                'numerical',
                'textual',
                'odometer',
                'engine_hours',
                'fuel_tank',
                'fuel_tank_calibration',
                'temperature',
                'temperature_calibration',
                'tachometer',
                'battery',
                'speed_ecm',
        ]);
    }

    public function timeoutValue()
    {
        switch ($this->type)
        {
            case 'gsm':
            case 'gps':
            case 'load':
            case 'numerical':

            case 'battery':
            case 'tachometer':
            case 'temperature':
            case 'temperature_calibration':
            case 'speed_ecm':
                return 300;
                break;

            default:
                return null;
        }
    }

    public function isBooleanValue()
    {
        return 'boolean' == $this->getValueType();
    }

    public function isFloatValue()
    {
        return 'float' == $this->getValueType();
    }

    public function isIntegerValue()
    {
        return 'integer' == $this->getValueType();
    }

    public function getUnit()
    {
        if ($this->type == 'engine_hours' && $this->tag_name = 'enginehours')
            return trans('front.hour_short');

        return $this->unit_of_measurement;
    }

    public function formatValue($value)
    {
        if ($this->type == 'door')
            return $value ? trans('front.opened') : trans('front.closed');

        if ( $this->isBooleanValue() )
            return $value ? trans('front.on') : trans('front.off');

        if (is_null($value))
            return '-';

        if ($this->isFloatValue())
            $value = round($value, 2);

        if ($this->isIntegerValue())
            $value = round($value);

        $unit = $this->getUnit();

        return $value . ($unit ? ' ' . $unit : '');
    }

    public function formatName()
    {
        $description = '';

        if (in_array($this->type, ['fuel_tank', 'fuel_tank_calibration']) && !empty($this->fuel_tank_name))
            $description = '('.$this->fuel_tank_name.')';

        return htmlentities($this->name . ($description ? ' ' . $description : ''));
    }

    public function getPercentage($other = null)
    {
        $percentage = 0;

        if ($this->type == 'fuel_tank' && $this->full_tank)
        {
            $percentage = $this->getValue($other) * 100 / $this->full_tank;
        }

        if ($this->type == 'fuel_tank_calibration')
        {
            $calibrations = $this->getCalibrations();

            if (!empty($calibrations['last_val']))
                $percentage = $this->getValue($other) * 100 / $calibrations['last_val'];
        }

        if ($this->type == 'gsm' || $this->type == 'battery')
        {
            $percentage = $this->getValue($other);
        }

        if ( $percentage < 0 )
            $percentage = 0;

        if ( $percentage > 100 )
            $percentage = 100;

        return round($percentage);
    }

    public function getValueScale($value)
    {
        if ($this->type == 'gsm' || $this->type == 'battery')
        {
            return ceil(($value ? $value : 0) / 20);
        }

        return null;
    }

    public function getValueCurrent($other = null)
    {
        $value = $this->isUpdatable() ? $this->value : null;

        $timeout = $this->timeoutValue();

        if( $timeout && time() - $this->updated_at > $timeout)
            $value = null;

        return $this->getValue($other, true, $value);
    }

    public function getValueFormated($other, $newest = true, $default = null) {
        $value = $this->getValue($other, $newest, $default);

        return $this->formatValue($value);
    }

    public function getValue($other, $newest = true, $default = null)
    {
        if ($this->type == 'odometer' && $this->odometer_value_by == 'virtual_odometer')
            return $this->odometer_value;

        $valueRaw = $this->getValueRaw($other);

        if (is_null($valueRaw))
        {
            if ( ! $newest)
                return $default;

            if (is_null($this->value))
                return null;

            return $this->isBooleanValue() ? (bool)$this->value : $this->value;
        }

        $sensor_value = null;

        switch ($this->type) {
            case 'harsh_breaking':
            case 'harsh_acceleration':
                if ($this->checkLogical($valueRaw, 'on_value', 1))
                    $sensor_value = true;
                break;
            case 'acc':
                if ($this->checkLogical($valueRaw, 'on_value', 1))
                    $sensor_value = true;

                if (is_null($sensor_value) && $this->checkLogical($valueRaw, 'off_value', 1))
                    $sensor_value = false;
                break;

            case 'door':
            case 'ignition':
            case 'engine':
            case 'seatbelt':
            case 'drive_business':
            case 'drive_private':
            case 'route_color':
            case 'logical':
                if ($this->checkLogical($valueRaw, 'on_tag_value', $this->on_type))
                    $sensor_value = true;

                if (is_null($sensor_value) && $this->checkLogical($valueRaw, 'off_tag_value', $this->off_type))
                    $sensor_value = false;

                break;

            case 'battery':
                switch ($this->shown_value_by) {
                    case 'tag_value':
                        $sensor_value = parseNumber($valueRaw);
                        break;
                    case 'min_max_values':
                        $sensor_value = $this->getValueMinMax($valueRaw);
                        break;
                    case 'formula':
                        $sensor_value = $this->getValueFormula($valueRaw);
                        break;
                }
                break;

            case 'gsm':
                $sensor_value = $this->getValueMinMax($valueRaw);
                break;

            case 'odometer':
                switch ($this->odometer_value_by) {
                    case 'connected_odometer':
                        $sensor_value = $this->getValueFormula($valueRaw);
                        break;
                    case 'virtual_odometer':
                        $sensor_value = float($this->odometer_value);
                        break;
                }
                break;

            case 'fuel_tank':
                $value = $this->getValueFormula($valueRaw);

                if (is_numeric($this->full_tank) && is_numeric($this->full_tank_value) && is_numeric($value)) {
                    if ($this->full_tank != $this->full_tank_value)
                        $sensor_value = $this->full_tank * (getPrc($this->full_tank_value, $value) / 100);
                    else
                        $sensor_value = $value;
                }
                break;

            case 'fuel_tank_calibration':
            case 'temperature_calibration':
                $calibrations = $this->getCalibrations();

                $value = $this->getValueFormula($valueRaw);

                if (($value < $calibrations['first'] && $calibrations['order'] == 'dec') ||
                    ($value > $calibrations['first'] && $calibrations['order'] == 'asc'))
                {
                    $sensor_value = $this->skip_calibration ? null : $calibrations['first_val'];
                }
                else {
                    $prev_item = [];
                    foreach ($calibrations['calibrations'] as $x => $y) {
                        if (!empty($prev_item)) {
                            if (($value < $x && $calibrations['order'] == 'dec') ||
                                ($value > $x && $calibrations['order'] == 'asc'))
                            {
                                $sensor_value = calibrate($value, $prev_item['x'], $prev_item['y'], $x, $y);
                                break;
                            }
                        }
                        $prev_item = [
                            'x' => $x,
                            'y' => $y
                        ];
                    }

                    if ( ( ! $this->skip_calibration) && is_null($sensor_value))
                        $sensor_value = $y;
                }

                if ( ! is_null($sensor_value))
                    $sensor_value = round($sensor_value, 2);

                break;

            case 'temperature':
            case 'tachometer':
            case 'numerical':
            case 'load':
            case 'speed_ecm':
                $sensor_value = $this->getValueFormula($valueRaw);
                break;

            case 'engine_hours':
                $sensor_value = $valueRaw;

                if ($this->tag_name == 'enginehours')
                    $sensor_value = round($sensor_value / 3600, 2);

                break;
            case 'satellites':
            case 'textual':
                $sensor_value = $valueRaw;
                break;
        }

        if (is_null($sensor_value))
            return $default;

        return $sensor_value;
    }

    function getValueRaw($other)
    {
        return parseTagValue($other, $this->tag_name);
    }

    protected function checkLogical($value, $field, $type)
    {
        $equal = $this->{$field};

        if ( $setflag = $this->getSetflag($field) ) {
            $value = substr($value, $setflag['start'], $setflag['count']);
            $equal = $setflag['value'];
        }

        return checkCondition($type, $value, $equal);
    }

    protected function getValueFormula($value)
    {
        if (empty($this->formula) || $this->formula == '[value]')
            return parseNumber($value);

        $formula = $this->formula;

        if ($setflag = $this->getSetflag('formula')) {
            $formula = $setflag['formula'];
            $value   = substr($value, $setflag['start'], $setflag['count']);
        }

        return solveEquation(parseNumber($value), $formula);
    }

    protected function getValueMinMax($value)
    {
        $value_number = parseNumber($value);

        if (!(is_numeric($this->max_value) && is_numeric($this->min_value) && is_numeric($value_number)))
            return null;

        if ($value <= $this->min_value)
            return 0;

        if ($value >= $this->max_value)
            return 100;

        return getPrc($this->max_value - $this->min_value, ($value_number - $this->min_value));
    }

    protected function getCalibrations()
    {
        if (!isset($this->cacheCalibrations))
        {
            $calibrations = array_reverse($this->calibrations, TRUE);

            $calibrationsData = [
                'calibrations' => $calibrations,
                'first'        => key($calibrations),
                'first_val'    => current($calibrations),
                'last_val'     => end($calibrations),
                'last'         => key($calibrations),
                'order'        => 'asc'
            ];

            if ($calibrationsData['first_val'] > $calibrationsData['last_val'] &&
                $calibrationsData['first'] < $calibrationsData['last'])
            {
                $calibrationsData['order'] = 'dec';
            }

            $this->cacheCalibrations = $calibrationsData;
        }

        return $this->cacheCalibrations;
    }

    protected function sendSensorChange($value)
    {
        //if sensor value changed
        if ( $this->value == $value)
            return;

        $options = [
            'device'       => $this->device->imei,
            'sensor_id'    => $this->id,
            'sensor_type'  => $this->type,
            'sensor_value' => $this->value
        ];

        $this->sendSensorRemote($options);
    }

    protected function sendSensorRemote($options)
    {
        $token_ip = env('SENSOR_REMOTE_TOKEN');
        $method = env('SENSOR_REMOTE_METHOD');
        $url = env('SENSOR_REMOTE_URL');

        $fields = [
            'token_ip' => $token_ip,
            'method' => $method
        ];

        $data = array_merge($fields, $options);

        $fields_string = http_build_query($data);

        //open connection
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);
    }
}
