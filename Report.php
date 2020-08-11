<?php namespace Tobuli\Entities;

use Eloquent;
use Carbon\Carbon;

class Report extends Eloquent {
	protected $table = 'reports';

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'format',
        'show_addresses',
        'zones_instead',
        'stops',
        'speed_limit',
        'daily',
        'daily_time',
        'weekly',
        'weekly_time',
        'monthly',
        'monthly_time',
        'email',
        'weekly_email_sent',
        'daily_email_sent',
        'monthly_email_sent',
        'from_format',
        'to_format',
        'parameters',
        'metas',
    ];

    protected $appends = array('from_formated', 'to_formated');

    protected $casts = [
        'parameters' => 'array',
        'metas' => 'array'
    ];

    public $timestamps = false;

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function devices() {
        return $this->belongsToMany('Tobuli\Entities\Device', 'report_device_pivot', 'report_id', 'device_id');
    }

    public function geofences() {
        return $this->belongsToMany('Tobuli\Entities\Geofence', 'report_geofence_pivot', 'report_id', 'geofence_id');
    }

    public function getFromFormatedAttribute()
    {
        if ($this->from_format)
            return Carbon::parse( $this->from_format )->format('Y-m-d H:i');
        else
            return Carbon::parse( '00:00:00' )->format('Y-m-d H:i');
    }

    public function getFormatAttribute($value)
    {
        if (empty($value))
            $value = 'html';

        return $value;
    }

    public function getToFormatedAttribute()
    {
        if ($this->to_format)
            return Carbon::parse( $this->to_format )->format('Y-m-d H:i');
        else
            return Carbon::parse( '23:45:00' )->format('Y-m-d H:i');
    }

    public function isSchedule()
    {
        return $this->daily || $this->weekly || $this->monthly;
    }
}
