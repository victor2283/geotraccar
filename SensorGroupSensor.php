<?php namespace Tobuli\Entities;

use Eloquent;

class SensorGroupSensor extends Eloquent {
	protected $table = 'sensor_group_sensors';

    protected $fillable = array(
        'group_id',
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
        'calibrations'
    );

    public $timestamps = false;

    public function setCalibrationsAttribute($value)
    {
        $this->attributes['calibrations'] = serialize($value);
    }

    public function getCalibrationsAttribute($value)
    {
        return unserialize($value);
    }
}
