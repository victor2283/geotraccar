<?php namespace Tobuli\Entities;

use Auth;
use Eloquent;
use Formatter;
use Tobuli\Traits\SentCommandActor;

class Alert extends Eloquent
{
    use SentCommandActor;

	protected $table = 'alerts';

    protected $fillable = array(
        'active',
        'user_id',
        'type',
        'name',
        'schedules',
        'notifications',

        'zone',
        'schedule',
        'overspeed',
        'idle_duration',
        'ignition_duration',
        'pre_start_checklist_only',
        'stop_duration',
        'offline_duration',
        'command'
    );

    protected $casts = [
        'data' => 'array',
        'notifications' => 'array',
    ];

    protected $appends = [
        'zone',
        'schedule',
        'command'
    ];

    protected $hidden = [
        'data'
    ];

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function devices() {
        return $this->belongsToMany('Tobuli\Entities\Device')
            // escape deattached users devices
            ->join('alerts', 'alerts.id', '=', 'alert_device.alert_id')
            ->join('user_device_pivot', function ($join) {
                $join
                    ->on('user_device_pivot.device_id', '=', 'alert_device.device_id')
                    ->on('user_device_pivot.user_id', '=', 'alerts.user_id');
            });
    }

    public function geofences() {
        return $this->belongsToMany('Tobuli\Entities\Geofence');
    }

    public function zones() {
        return $this->belongsToMany('Tobuli\Entities\Geofence', 'alert_zone', 'alert_id', 'geofence_id');
    }

    public function fuel_consumptions() {
        return $this->hasMany('Tobuli\Entities\AlertFuelConsumption', 'alert_id');
    }

    public function drivers() {
        return $this->belongsToMany('Tobuli\Entities\UserDriver', 'alert_driver_pivot', 'alert_id', 'driver_id');
    }

    public function events_custom() {
        return $this->belongsToMany('Tobuli\Entities\EventCustom', 'alert_event_pivot', 'alert_id', 'event_id');
    }

    public function scopeActive($query)
    {
        return $query->where('alerts.active', 1);
    }

    public function scopeCheckByPosition($query)
    {
        return $query->whereIn('type', [
            'custom',
            'overspeed',
            'driver',
            'geofence_in',
            'geofence_out',
            'geofence_inout',
            'sos',
            'fuel_change'
        ]);
    }

    public function scopeCheckByTime($query)
    {
        return $query->whereIn('type', ['idle_duration', 'ignition_duration', 'stop_duration', 'offline_duration']);
    }

    public function getChannelsAttribute()
    {
        $notifications = $this->notifications;

        $channels = [
            'push'         => array_get($notifications, 'push.active'),
            'email'        => array_get($notifications, 'email.active') ? array_get($notifications, 'email.input') : null,
            'mobile_phone' => array_get($notifications, 'sms.active') ? array_get($notifications, 'sms.input') : null,
            'webhook'      => array_get($notifications, 'webhook.active') ? array_get($notifications, 'webhook.input') : null,
            'command'      => array_get($this->command, 'active') ? $this->command : null,
        ];

        if (settings('plugins.alert_sharing.status')) {
            if (array_get($notifications, 'sharing_email.active'))
                $channels = array_merge($channels, [
                    'sharing_email' => array_get($notifications, 'sharing_email.input')
                ]);

            if (array_get($notifications, 'sharing_sms.active'))
                $channels = array_merge($channels, [
                    'sharing_sms' => array_get($notifications, 'sharing_sms.input')
                ]);
        }

        return $channels;
    }

    public function getScheduleAttribute()
    {
        return array_get($this->data, 'schedule', 0);
    }

    public function setScheduleAttribute($value)
    {
        $this->setData('schedule', $value);
    }

    public function getCommandAttribute()
    {
        return array_get($this->data, 'command');
    }

    public function setCommandAttribute($value)
    {
        $this->setData('command', $value);
    }

    public function getSchedulesAttribute()
    {
        $schedules = $this->getSchedulesUTC();

        return $this->convertSchedules($schedules, false);
    }

    public function getSchedulesUTC()
    {
        return json_decode($this->attributes['schedules'], true);
    }

    public function setSchedulesAttribute($schedules)
    {
        $schedules = $this->convertSchedules($schedules, true);

        $this->attributes['schedules'] = json_encode($schedules);
    }

    public function getZoneAttribute()
    {
        return array_get($this->data, 'zone', 0);
    }

    public function setZoneAttribute($value)
    {
        $this->setData('zone', $value);
    }

    public function getOverspeedAttribute()
    {
        $overspeed = array_get($this->data, 'overspeed', 0);

        if ( Auth::check() && Auth::user()->unit_of_distance == 'mi')
            $overspeed = kilometersToMiles($overspeed);

        return $overspeed;
    }

    public function setOverspeedAttribute($value)
    {
        if ( Auth::check() && Auth::user()->unit_of_distance == 'mi')
            $value = milesToKilometers($value);

        $this->setData('overspeed', $value);
    }

    public function getIdleDurationAttribute()
    {
        return array_get($this->data, 'idle_duration', 0);
    }

    public function setIdleDurationAttribute($value)
    {
        $this->setData('idle_duration', $value);
    }

    public function getIgnitionDurationAttribute()
    {
        return array_get($this->data, 'ignition_duration', 0);
    }

    public function setIgnitionDurationAttribute($value)
    {
        $this->setData('ignition_duration', $value);
    }

    public function getPreStartChecklistOnlyAttribute()
    {
        return array_get($this->data, 'pre_start_checklist_only', 0);
    }

    public function setPreStartChecklistOnlyAttribute($value)
    {
        $this->setData('pre_start_checklist_only', $value);
    }

    public function getStopDurationAttribute()
    {
        return array_get($this->data, 'stop_duration', 0);
    }

    public function setStopDurationAttribute($value)
    {
        $this->setData('stop_duration', $value);
    }

    public function getOfflineDurationAttribute()
    {
        return array_get($this->data, 'offline_duration', 0);
    }

    public function setOfflineDurationAttribute($value)
    {
        $this->setData('offline_duration', $value);
    }

    private function setData($key, $value)
    {
        $data = $this->data;

        if ($value)
            array_set($data, $key, $value);
        else
            array_forget($data, $key);

        $this->data = $data;
    }

    private function convertSchedules($schedules, $reverse = false)
    {
        if (empty($schedules))
            return null;

        if ( ! (Auth::check() && Auth::user()->timezone_id != 57))
            return $schedules;

        $result = [];

        foreach($schedules as $weekday => $times) {
            foreach ($times as $time) {
                $_time = strtotime($weekday . ' ' . $time);

                if ($reverse) {
                    $_time = Formatter::time()->reverse(date('Y-m-d H:i:s', $_time), 'l H:i');
                } else {
                    $_time = Formatter::time()->convert(date('Y-m-d H:i:s', $_time), 'l H:i');
                }

                list($_weekday, $_time) = explode(' ', $_time);

                $_weekday = strtolower($_weekday);

                $result[$_weekday][] = $_time;
            }
        }

        return $result;
    }
}
