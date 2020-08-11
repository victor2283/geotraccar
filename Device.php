<?php namespace Tobuli\Entities;

use Carbon\Carbon;
use Formatter;
use App\Jobs\TrackerConfigWithRestart;
use Eloquent;
use Facades\Repositories\TraccarDeviceRepo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Tobuli\Exceptions\ValidationException;
use Tobuli\Traits\Chattable;
use Tobuli\Traits\EventLoggable;
use Tobuli\Traits\Filterable;
use Tobuli\Traits\Includable;
use Tobuli\Traits\Nameable;
use Tobuli\Traits\Searchable;


class Device extends Eloquent
{
    use Chattable, EventLoggable, Searchable, Filterable, Includable, Nameable;

    const STATUS_ACK     = 'ack';
    const STATUS_OFFLINE = 'offline';
    const STATUS_ONLINE  = 'online';
    const STATUS_ENGINE  = 'engine';

    const IMAGE_PATH     = 'images/device_images/';

    protected $table = 'devices';

    protected $fillable = array(
        'deleted',
        'traccar_device_id',
        'timezone_id',
        'name',
        'imei',
        'icon_id',
        'fuel_measurement_id',
        'fuel_quantity',
        'fuel_price',
        'fuel_per_km',
        'sim_number',
        'device_model',
        'plate_number',
        'vin',
        'registration_number',
        'object_owner',
        'additional_notes',
        'expiration_date',
        'tail_color',
        'tail_length',
        'engine_hours',
        'detect_engine',
        'min_moving_speed',
        'min_fuel_fillings',
        'min_fuel_thefts',
        'snap_to_road',
        'gprs_templates_only',
        'valid_by_avg_speed',
        'icon_colors',
        'parameters',
        'currents',
        'active',
        'forward',
        'sim_activation_date',
        'sim_expiration_date',
        'installation_date',
    );

    protected $appends = [
        'stop_duration'
        //'lat',
        //'lng',
        //'speed',
        //'course',
        //'altitude',
        //'protocol',
        //'time'
    ];

    //protected $hidden = ['currents'];

    protected $casts = [
        'currents' => 'array'
    ];

    protected $searchable = [
        'name',
        'imei',
        'sim_number',
        'vin',
        'plate_number'
    ];
    protected $filterables = [
        'id',
        'imei',
        'sim_number',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            $traccar_item = TraccarDeviceRepo::create([
                'name' => $device->name,
                'uniqueId' => $device->imei
            ]);

            $device->traccar_device_id = $traccar_item->id;
        });

        static::updated(function ($device) {
            TraccarDeviceRepo::update($device->traccar_device_id, [
                'name' => $device->name,
                'uniqueId' => $device->imei
            ]);
        });

        static::saved(function ($device) {
            if ($device->isDirty('imei'))
                UnregisteredDevice::where('imei', $device->imei)->delete();

            if ($device->isDirty('forward'))
                dispatch((new TrackerConfigWithRestart()));
        });
    }

    public function positions()
    {
        $instance = new \Tobuli\Entities\TraccarPosition();
        $instance->setTable('positions_' . $this->traccar_device_id);

        $foreignKey = $instance->getTable().'.device_id';
        $localKey = 'traccar_device_id';

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    public function positionTraccar()
    {
        if ( ! $this->traccar) {
            return null;
        }

        return new \Tobuli\Entities\TraccarPosition([
            'id' => $this->traccar->lastestPosition_id,
            'device_id' => $this->traccar->id,
            'latitude' => $this->traccar->lastValidLatitude,
            'longitude' => $this->traccar->lastValidLongitude,
            'other' => $this->traccar->other,
            'speed' => $this->traccar->speed,
            'altitude' => $this->traccar->altitude,
            'course' => $this->traccar->course,
            'time' => $this->traccar->time,
            'device_time' => $this->traccar->device_time,
            'server_time' => $this->traccar->server_time,
            'protocol' => $this->traccar->protocol,
            'valid' => true
        ]);
    }

    public function createPositionsTable()
    {
        if (Schema::connection('traccar_mysql')->hasTable('positions_' . $this->traccar_device_id))
            throw new ValidationException(['id' => trans('global.cant_create_device_database')]);

        Schema::connection('traccar_mysql')->create('positions_' . $this->traccar_device_id, function(Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->bigInteger('device_id')->unsigned()->index();
            $table->double('altitude')->nullable();
            $table->double('course')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->text('other')->nullable();
            $table->double('power')->nullable();
            $table->double('speed')->nullable()->index();
            $table->datetime('time')->nullable()->index();
            $table->datetime('device_time')->nullable();
            $table->datetime('server_time')->nullable()->index();
            $table->text('sensors_values')->nullable();
            $table->tinyInteger('valid')->nullable();
            $table->double('distance')->nullable();
            $table->string('protocol', 20)->nullable();
        });
    }

    public function icon()
    {
        return $this->hasOne('Tobuli\Entities\DeviceIcon', 'id', 'icon_id');
    }

    public function getIconAttribute()
    {
        $icon = $this->getRelationValue('icon');

        return $icon ? $icon->setStatus($this->getStatus()) : null;
    }

    public function traccar()
    {
        return $this->hasOne('Tobuli\Entities\TraccarDevice', 'id', 'traccar_device_id');
    }

    public function alerts()
    {
        return $this->belongsToMany('Tobuli\Entities\Alert', 'alert_device', 'device_id', 'alert_id')
            // escape deattached users devices
            ->join('user_device_pivot', function ($join) {
                $join
                    ->on('user_device_pivot.device_id', '=', 'alert_device.device_id')
                    ->on('user_device_pivot.user_id', '=', 'alerts.user_id');
            });
    }

    public function events()
    {
        return $this->hasMany('Tobuli\Entities\Event', 'device_id');
    }

    public function last_event()
    {
        return $this->hasOne('Tobuli\Entities\Event', 'device_id')->orderBy('id', 'desc');
    }

    public function users() {
        return $this->belongsToMany('Tobuli\Entities\User', 'user_device_pivot', 'device_id', 'user_id')->withPivot('group_id', 'current_driver_id', 'current_events');
    }

    public function driver() {
        //return $this->belongsToMany('Tobuli\Entities\UserDriver', 'user_device_pivot', 'device_id', 'current_driver_id');
        return $this->hasOne('Tobuli\Entities\UserDriver', 'id', 'current_driver_id');
    }

    public function sensors() {
        return $this->hasMany('Tobuli\Entities\DeviceSensor', 'device_id');
    }

    public function services() {
        return $this->hasMany('Tobuli\Entities\DeviceService', 'device_id');
    }

    public function expenses()
    {
        return $this->hasMany('Tobuli\Entities\DeviceExpense', 'device_id');
    }

    public function timezone()
    {
        return $this->hasOne('Tobuli\Entities\Timezone', 'id', 'timezone_id');
    }

    public function deviceCameras() {
        return $this->hasMany('Tobuli\Entities\DeviceCamera', 'device_id');
    }

    public function group()
    {
        return $this->hasOne('Tobuli\Entities\Timezone', 'id', 'timezone_id');
    }

    public function setTimezoneIdAttribute($value)
    {
        $this->attributes['timezone_id'] = empty($value) ? null : $value;
    }

    public function setIconColorsAttribute($value)
    {
        $this->attributes['icon_colors'] = json_encode($value);
    }

    public function getIconColorsAttribute($value)
    {
        return json_decode($value, TRUE);
    }

    public function setForwardAttribute($value)
    {
        if (array_get($value, 'active'))
            $this->attributes['forward'] = json_encode($value);
        else
            $this->attributes['forward'] = null;
    }

    public function getForwardAttribute($value)
    {
        return json_decode($value, TRUE);
    }

    public function isExpired()
    {
        if ( ! $this->hasExpireDate())
            return false;

        return  strtotime($this->expiration_date) < strtotime(date('Y-m-d'));
    }

    public function hasExpireDate()
    {
        if ( ! $this->expiration_date)
            return false;

        if ($this->expiration_date == '0000-00-00')
            return false;

        return  true;
    }

    public function isConnected()
    {
        return Redis::get('connected.' . $this->imei) ? true : false;
    }

    public function getParameters($key = null)
    {
        if ( ! isset($this->traccar->other))
            return is_null($key) ? [] : null;

        $parameters = parseXMLToArray($this->traccar->other);

        if (is_null($key))
            return $parameters;

        return array_get($parameters, $key);
    }

    public function getTotalDistance()
    {
        $distance = $this->getParameters('totaldistance') / 1000;

        return Formatter::distance()->format($distance);
    }

    public function getSpeed() {
        $speed = 0;

        if (isset($this->traccar->speed) && $this->getStatus() == 'online')
            $speed = $this->traccar->speed;

        return Formatter::speed()->format($speed);
    }

    public function getTimeoutStatus()
    {
        $minutes = settings('main_settings.default_object_online_timeout') * 60;

        $status = self::STATUS_OFFLINE;

        if ((time() - $minutes) < strtotime($this->getAckTime()))
            $status = self::STATUS_ACK;
        if ((time() - $minutes) < strtotime($this->getServerTime()))
            $status = self::STATUS_ONLINE;

        return $status;
    }

    public function getStatusAttribute() {
        return $this->getStatus();
    }

    public function getStatus()
    {
        if ($this->isExpired())
            return self::STATUS_OFFLINE;
        
        $status = $this->getTimeoutStatus();

        if ($status != self::STATUS_ONLINE)
            return $status;

        $speed  = isset($this->traccar->speed) ? $this->traccar->speed : null;
        $status = self::STATUS_OFFLINE;
        $sensor = $this->getEngineSensor();

        if ( ! empty($sensor)) {

            if ( ! $sensor->getValueCurrent($this->other) ) {
                $status = self::STATUS_ACK;
            }
            else {
                if ($speed < $this->min_moving_speed) {
                    $status = self::STATUS_ENGINE;
                }
                elseif ($speed > $this->min_moving_speed) {
                    $status = self::STATUS_ONLINE;
                }
            }
        }
        else {
            if ($speed < $this->min_moving_speed) {
                $status = self::STATUS_ACK;
            }
            elseif ($speed > $this->min_moving_speed) {
                $status = self::STATUS_ONLINE;
            }
        }

        return $status;
    }

    public function getStatusColorAttribute() {
        return $this->getStatusColor();
    }

    public function getStatusColor()
    {
        switch ($this->getStatus()) {
            case 'online':
                $icon_status = 'moving';
                break;
            case 'ack':
                $icon_status = 'stopped';
                break;
            case 'engine':
                $icon_status = 'engine';
                break;
            default:
                $icon_status = 'offline';
        }

        return array_get($this->icon_colors, $icon_status, 'red');
    }

    public function getSensorsByType($type)
    {
        $sensors = $this->sensors;

        if (empty($this->sensors))
            return null;

        return $this->sensors->filter(function ($sensor) use ($type) {
            return $sensor->type == $type;
        });
    }

    public function getSensorByType($type)
    {
        $sensors = $this->sensors;

        if (empty($sensors))
            return null;

        foreach ($sensors as $sensor) {
            if ($sensor['type'] == $type) {
                $type_sensor = $sensor;
                break;
            }
        }

        if (empty($type_sensor))
            return null;

        return $type_sensor;
    }

    public function getFuelTankSensor()
    {
        $sensor = $this->getSensorByType('fuel_tank');

        if ($sensor)
            return $sensor;

        return $this->getSensorByType('fuel_tank_calibration');
    }

    public function getOdometerSensor()
    {
        return $this->getSensorByType('odometer');
    }

    public function getEngineHoursSensor()
    {
        return $this->getSensorByType('engine_hours');
    }

    public function getEngineSensor()
    {
        $detect_engine = $this->engine_hours == 'engine_hours' ? $this->detect_engine : $this->engine_hours;

        if (empty($detect_engine))
            return null;

        if ($detect_engine == 'gps')
            return null;

        return $this->getSensorByType($detect_engine);
    }

    public function getEngineStatusAttribute()
    {
        return $this->getEngineStatus();
    }

    public function getEngineStatus($formated = false)
    {
        $sensor = $this->getEngineSensor();

        if (empty($sensor))
            return $formated ? '-' : null;

        if ($this->getStatus() == self::STATUS_OFFLINE)
            return false;

        $value = $sensor->getValueCurrent($this->other);

        return $formated ? $sensor->formatValue($value) : $value;
    }

    public function getEngineStatusFrom($date_from) {
        $sensor = $this->getEngineSensor();

        if (empty($sensor))
            return false;

        $position = $this->positions()->where('time', '<=', $date_from)->first();

        if ( ! $position)
            return false;

        return $position->getSensorValue($sensor->id);
    }

    public function getProtocol($user = null)
    {
        $user = $user ?? getActingUser();

        return ($this->protocol && $user->perm('device.protocol', 'view')) ? $this->protocol : null;
    }

    public function setProtocolAttribute($value)
    {
        $this->attributes['protocol'] = $value;
    }

    public function getProtocolAttribute()
    {
        if (array_key_exists('protocol', $this->attributes))
            return $this->attributes['protocol'];

        return isset($this->traccar->protocol) ? $this->traccar->protocol : null;
    }

    public function getDeviceTime()
    {
        return $this->traccar && $this->traccar->device_time ? $this->traccar->device_time : null;
    }

    public function getTime()
    {
        return $this->traccar && $this->traccar->time ? $this->traccar->time : null;
    }

    public function getAckTime()
    {
        return $this->traccar && $this->traccar->ack_time ? $this->traccar->ack_time : null;
    }

    public function getServerTime()
    {
        return $this->traccar && $this->traccar->server_time ? $this->traccar->server_time : null;
    }

    public function getTimeAttribute()
    {
        if ($this->isExpired())
            return trans('front.expired');

        $time = max($this->getTime(), $this->getAckTime());

        if (empty($time) || substr($time, 0, 4) == '0000')
            return trans('front.not_connected');

        return Formatter::time()->human($time);
    }

    public function getOnlineAttribute() {
        return $this->getStatus();
    }

    public function getLatAttribute()
    {
        if ($this->isExpired())
            return null;

        return cord(isset($this->traccar->lastValidLatitude) ? $this->traccar->lastValidLatitude : 0);
    }

    public function getLngAttribute()
    {
        if ($this->isExpired())
            return null;

        return cord(isset($this->traccar->lastValidLongitude) ? $this->traccar->lastValidLongitude : 0);
    }

    public function getLatitudeAttribute()
    {
        return cord(isset($this->traccar->lastValidLatitude) ? $this->traccar->lastValidLatitude : 0);
    }

    public function getLongitudeAttribute()
    {
        return cord(isset($this->traccar->lastValidLongitude) ? $this->traccar->lastValidLongitude : 0);
    }

    public function getCourseAttribute() {
        $course = 0;

        if (isset($this->traccar->course))
            $course = $this->traccar->course;

        return round($course);
    }

    public function getAltitudeAttribute() {
        $altitude = 0;

        if (isset($this->traccar->altitude))
            $altitude = $this->traccar->altitude;

        return Formatter::altitude()->format($altitude);
    }

    public function getTailAttribute() {
        $tail_length = $this->getStatus() ? $this->tail_length : 0;

        return prepareDeviceTail(isset($this->traccar->latest_positions) ? $this->traccar->latest_positions : '', $tail_length);
    }

    public function getLatestPositionsAttribute() {
        return isset($this->traccar->latest_positions) ? $this->traccar->latest_positions : null;
    }

    public function getTimestampAttribute() {
        if ($this->isExpired())
            return 0;

        return isset($this->traccar->server_time) ? strtotime($this->traccar->server_time) : 0;
    }

    public function getServerTimestampAttribute() {
        if ($this->isExpired())
            return 0;

        return isset($this->traccar->server_time) ? strtotime($this->traccar->server_time) : 0;
    }

    public function getAckTimestampAttribute() {
        if ($this->isExpired())
            return 0;

        return isset($this->traccar->ack_time) ? strtotime($this->traccar->ack_time) : 0;
    }

    public function getAckTimeAttribute() {
        if ($this->isExpired())
            return null;

        return isset($this->traccar->ack_time) ? $this->traccar->ack_time : null;
    }

    public function getServerTimeAttribute() {
        if ($this->isExpired())
            return null;

        return isset($this->traccar->server_time) ? $this->traccar->server_time : null;
    }

    public function getMovedAtAttribute() {
        if ($this->isExpired())
            return null;

        return isset($this->traccar->moved_at) ? $this->traccar->moved_at : null;
    }

    public function getMovedTimestampAttribute() {
        return $this->moved_at ? strtotime($this->moved_at) : 0;
    }

    public function getLastConnectTimeAttribute() {
        $lastConnect = $this->getLastConnectTimestampAttribute();

        return $lastConnect ? date('Y-m-d H:i:s', $lastConnect) : null;
    }

    public function getLastConnectTimestampAttribute() {
        return max($this->server_timestamp, $this->ack_timestamp);
    }

    public function getOtherAttribute() {
        return isset($this->traccar->other) ? $this->traccar->other : null;
    }

    public function getIdleDuration()
    {
        $engine_off_at = isset($this->traccar->engine_off_at) ? strtotime($this->traccar->engine_off_at) : 0;
        $engine_on_at  = isset($this->traccar->engine_on_at) ? strtotime($this->traccar->engine_on_at) : 0;
        $moved_at      = isset($this->traccar->moved_at) ? strtotime($this->traccar->moved_at) : 0;
        $time          = isset($this->traccar->time) ? strtotime($this->traccar->time) : 0;
        $server_time   = isset($this->traccar->server_time) ? strtotime($this->traccar->server_time) : 0;

        if ( ! $moved_at)
            return 0;

        if ( ! $engine_off_at)
            return 0;

        if ($engine_on_at < $engine_off_at)
            return 0;

        $check_at = max($engine_off_at, $moved_at);

        //device send incorrcet self timestamp
        if ($server_time > $time )
            return time() - $check_at + ($time - $server_time);

        return time() - $check_at;
    }

    public function getIdleDurationAttribute()
    {
        $duration = $this->getIdleDuration();

        return Formatter::duration()->human($duration);
    }

    public function getIgnitionDuration()
    {
        $engineOff  = isset($this->traccar->engine_off_at) ? strtotime($this->traccar->engine_off_at) : 0;
        $engineOn   = isset($this->traccar->engine_on_at) ? strtotime($this->traccar->engine_on_at) : 0;
        $time       = isset($this->traccar->time) ? strtotime($this->traccar->time) : 0;
        $serverTime = isset($this->traccar->server_time) ? strtotime($this->traccar->server_time) : 0;

        if (! $engineOn || ! $engineOff) {
            return 0;
        }

        if ($engineOff >= $engineOn) {
            return 0;
        }

        //device sent incorrcet self timestamp
        if ($serverTime > $time) {
            return time() - $engineOff + ($time - $serverTime);
        }

        return time() - $engineOff;
    }

    public function getIgnitionDurationAttribute()
    {
        $duration = $this->getIgnitionDuration();

        return Formatter::duration()->human($duration);
    }

    public function getStopDuration()
    {
        $moved_at    = isset($this->traccar->moved_at) ? strtotime($this->traccar->moved_at) : 0;
        $time        = isset($this->traccar->time) ? strtotime($this->traccar->time) : 0;
        $server_time = isset($this->traccar->server_time) ? strtotime($this->traccar->server_time) : 0;

        if ( ! $moved_at)
            return 0;

        //device send incorrcet self timestamp
        if ($server_time > $time )
            return time() - $moved_at + ($time - $server_time);

        return time() - $moved_at;
    }

    public function getStopDurationAttribute()
    {
        $duration = $this->getStopDuration();

        if ($duration < 5)
            $duration = 0;

        return Formatter::duration()->human($duration);
    }

    public function getFormatSensors()
    {
        if ($this->isExpired())
            return null;

        $result = [];

        foreach ($this->sensors as $sensor) {
            if ($sensor->type == 'harsh_acceleration' || $sensor->type == 'harsh_breaking')
                continue;

            $value = $sensor->getValueCurrent($this->other);

            $result[] = [
                'id'            => $sensor->id,
                'type'          => $sensor->type,
                'name'          => $sensor->formatName(),
                'show_in_popup' => $sensor->show_in_popup,

                //'text'          => htmlentities( $sensor->formatValue($value) ),
                'value'         => htmlentities( $sensor->formatValue($value) ),
                'val'           => $value,
                'scale_value'   => $sensor->getValueScale($value)
            ];
        }

        return $result;
    }

    public function getFormatServices()
    {
        if ($this->isExpired())
            return null;

        $result = [];

        foreach ($this->services as $service)
        {
            $service->setSensors($this->sensors);

            $result[] = [
                'id'       => $service->id,
                'name'     => $service->name,
                'value'    => $service->expiration(),
                'expiring' => $service->isExpiring()
            ];
        }

        return $result;
    }

    public function generateTail() {
        $limit = 15;

        $positions = DB::connection('traccar_mysql')
            ->table('positions_'.$this->traccar_device_id)
            ->where('distance', '>', 0.02)
            ->orderBy('time', 'desc')
            ->limit($limit)
            ->get();

        $tail_positions = [];

        foreach ($positions as $position) {
            $tail_positions[] = $position->latitude.'/'.$position->longitude;
        }

        $this->traccar->update([
            'latest_positions' => implode(';', $tail_positions)
        ]);
    }


    public function isCurrentGeofence($geofence)
    {
        $currents = $this->currents ? $this->currents : [];

        if (empty($currents))
            return false;

        if (empty($currents['geofences']))
            return false;

        if ( ! in_array($geofence->id, $currents['geofences']))
            return false;

        return true;
    }

    public function setCurrentGeofences($geofences)
    {
        $currents = $this->currents ? $this->currents : [];

        $this->currents = array_merge($currents, ['geofences' => $geofences]);
    }

    public function applyPositionsTimezone()
    {
        if ( ! $this->timezone ) {
            $value = 'device_time';
        } elseif ( $this->timezone->id == 57) {
            $value = 'device_time';
        } else {
            list($hours, $minutes) = explode(' ', $this->timezone->time);

            if ($this->timezone->prefix == 'plus')
                $value = "DATE_ADD(device_time, INTERVAL '$hours:$minutes' HOUR_MINUTE)";
            else
                $value = "DATE_SUB(device_time, INTERVAL '$hours:$minutes' HOUR_MINUTE)";
        }

        $this->traccar()->update(['time' => DB::raw($value)]);
        $this->positions()->update(['time' => DB::raw($value)]);
    }

    public function isCorrectUTC()
    {
        $change = 900; //15 mins

        $ack_time    = strtotime( $this->getAckTime() );
        $server_time = strtotime( $this->getServerTime() );
        $device_time = strtotime( $this->getDeviceTime() );

        $last = max($ack_time, $server_time);

        if ($last && (abs($last - $device_time) < $change))
            return true;

        return false;
    }

    public function canChat()
    {
        $protocol = isset($this->traccar->protocol) ? $this->traccar->protocol : null;

        return $protocol == 'osmand';
    }

    public function scopeNPerGroup($query, $group, $n = 10)
    {
        // queried table
        $table = ($this->getTable());

        // initialize MySQL variables inline
        $query->from( DB::raw("(SELECT @rank:=0, @group:=0) as vars, {$table}") );

        // if no columns already selected, let's select *
        if ( ! $query->getQuery()->columns)
        {
            $query->select("{$table}.*");
        }

        // make sure column aliases are unique
        $groupAlias = 'group_'.md5(time());
        $rankAlias  = 'rank_'.md5(time());

        // apply mysql variables
        $query->addSelect(DB::raw(
            "@rank := IF(@group = {$group}, @rank+1, 1) as {$rankAlias}, @group := {$group} as {$groupAlias}"
        ));

        // make sure first order clause is the group order
        $query->getQuery()->orders = (array) $query->getQuery()->orders;
        array_unshift($query->getQuery()->orders, ['column' => $group, 'direction' => 'asc']);

        // prepare subquery
        $subQuery = $query->toSql();

        // prepare new main base Query\Builder
        $newBase = $this->newQuery()
            ->from(DB::raw("({$subQuery}) as {$table}"))
            ->mergeBindings($query->getQuery())
            ->where($rankAlias, '<=', $n)
            ->getQuery();

        // replace underlying builder to get rid of previous clauses
        $query->setQuery($newBase);
    }

    public function changeDriver($driver)
    {
        $this->current_driver_id = $driver->id;
        $this->save();

        DB::table('user_driver_position_pivot')->insert([
            'device_id' => $this->id,
            'driver_id' => $driver->id,
            'date' => date('Y-m-d H:i:s')
        ]);

        $position = $this->positionTraccar();

        if (is_null($position))
            return;

        $alerts = $this->alerts->filter(function($item){
            return $item->type == 'driver';
        });

        foreach ($alerts as $alert) {
            $event = $this->events()->create([
                'type'         => 'driver',
                'user_id'      => $alert->user_id,
                'alert_id'     => $alert->id,
                'device_id'    => $this->id,
                'geofence_id'  => null,
                'position_id'  => $position->id,
                'altitude'     => $position->altitude,
                'course'       => $position->course,
                'latitude'     => $position->latitude,
                'longitude'    => $position->longitude,
                'speed'        => $position->speed,
                'time'         => $position->time,
                'message'      => $driver->name,
                'additional'   => [
                    'driver_id'   => $driver->id,
                    'driver_name' => $driver->name
                ]
            ]);

            SendQueue::create([
                'user_id'   => $event->user_id,
                'type'      => $event->type,
                'data'      => $event,
                'channels'  => $alert->channels
            ]);
        }
    }

    public function setExpirationDateAttribute($value)
    {
        $this->attributes['expiration_date'] = is_null($value) ? '0000-00-00' : $value;
    }

    public function getExpirationDateAttribute($value)
    {
        if ($value == '0000-00-00')
            return null;

        return $value;
    }

    public function getWidgetCameras()
    {
        return $this->deviceCameras()->where('show_widget', 1)->get();
    }

    public function scopeIsExpiringAfter($query, $days)
    {
        return $query
            ->where('expiration_date', '!=', '0000-00-00')
            ->where('expiration_date', '!=', '0000-00-00 00:00:00')
            ->where('expiration_date', '>=', Carbon::now())
            ->where('expiration_date', '<=', Carbon::now()->addDays($days));
    }

    public function scopeIsExpiredBefore($query, $days)
    {
        return $query
            ->where('expiration_date', '!=', '0000-00-00')
            ->where('expiration_date', '!=', '0000-00-00 00:00:00')
            ->where('expiration_date', '<=', Carbon::now()->subDays($days));
    }

    public function scopeExpired($query) {
        return $query
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '!=', '0000-00-00')
            ->where('expiration_date', '<=', Carbon::now());
    }

    public function scopeFilterUserAbility($query, User $user, $ability = 'own') {
        return $query->with('users')->get()->filter(function($device) use ($user, $ability) {
            return $user->can($ability, $device);
        });
    }

    public static function getFields()
    {
        $fields = [
            'name' => trans('validation.attributes.name'),
            'imei' => trans('validation.attributes.imei'),
            'sim_number' => trans('validation.attributes.sim_number'),
            'vin' => trans('validation.attributes.vin'),
            'device_model' => trans('validation.attributes.device_model'),
            'plate_number' => trans('validation.attributes.plate_number'),
            'registration_number' => trans('validation.attributes.registration_number'),
            'object_owner' => trans('validation.attributes.object_owner'),
            'additional_notes' => trans('validation.attributes.additional_notes'),

            //'fuel_quantity' => trans('validation.attributes.fuel_quantity'),
            //'fuel_price' => trans('validation.attributes.fuel_price'),

            'users_emails' => trans('admin.users'),
            'protocol' => trans('front.protocol'),
            'latitude' => trans('front.latitude'),
            'longitude' => trans('front.longitude'),
            'altitude' => trans('front.altitude'),
            'course' => trans('front.course'),
            'speed' => trans('front.speed'),
            'last_connect_time' => trans('admin.last_connection'),
            'stop_duration' => trans('front.stop_duration'),

            'expiration_date' => trans('validation.attributes.expiration_date'),
        ];

        if (settings('plugins.additional_installation_fields.status')) {
            $fields['sim_activation_date'] = trans('validation.attributes.sim_activation_date');
            $fields['sim_expiration_date'] = trans('validation.attributes.sim_expiration_date');
            $fields['installation_date']   = trans('validation.attributes.installation_date');
        }

        return $fields;
    }

    public function getUsersEmailsAttribute()
    {
        return $this
            ->users
            ->filter(function($user){
                return auth()->user()->can('show', $user);
            })
            ->implode('email', ', ');
    }

    public function getImageAttribute()
    {
        $path = str_finish(self::IMAGE_PATH, '/') . "{$this->id}.*";

        return File::glob($path)[0] ?? null;
    }

    public function getNameWithSimNumberAttribute()
    {
        return $this->name." ({$this->sim_number})";
    }

    public function isMove() {
        return $this->getStatus() == self::STATUS_ONLINE;
    }

    public function isIdle() {
        return $this->getStatus() == self::STATUS_ENGINE;
    }

    public function isStop() {
        return $this->getStatus() == self::STATUS_ACK;
    }

    public function isOffline() {
        return $this->getTimeoutStatus() === self::STATUS_OFFLINE;
    }

    public function isOfflineFrom($date) {
        $time = max($this->getTime(), $this->getAckTime());

        return Carbon::parse($date)->timestamp > $time;
    }

    public function isInactive()
    {
        $time = max($this->getTime(), $this->getAckTime());

        return Carbon::now()->subMinutes(settings('main_settings.default_object_inactive_timeout'))->timestamp > $time;
    }

    public function isNeverConnected() {
        return is_null($this->getServerTime()) && is_null($this->getAckTime());
    }

    public function scopeTraccarJoin($query) {
        $traccar_db = config('database.connections.traccar_mysql.database');

        if ($query->getQuery()->isJoined("$traccar_db.devices as traccar"))
            return $query;

        return $query->leftJoin("$traccar_db.devices as traccar", 'devices.traccar_device_id', '=', 'traccar.id');
    }

    public function scopeWasConnected($query) {
        return $query
            ->traccarJoin()
            ->where(function($q) {
                $q->whereNotNull('traccar.server_time');
                $q->orWhereNotNull('traccar.ack_time');
            });
    }

    public function scopeNeverConnected($query) {
        return $query
            ->traccarJoin()
            ->whereNull('traccar.server_time')
            ->whereNull('traccar.ack_time');
    }

    public function scopeConnected($query, $minutes = null)
    {
        if (is_null($minutes))
            $minutes = config('tobuli.device_offline_minutes');

        $time = Carbon::now()->subMinutes($minutes);

        return $query
            ->traccarJoin()
            ->where(function($query) use ($time) {
                $query->where('traccar.server_time', '>', $time);
                $query->orWhere('traccar.ack_time', '>', $time);
            });
    }

    public function scopeOnline($query, $minutes = null) {
        if (is_null($minutes))
            $minutes = settings('main_settings.default_object_online_timeout');

        $time = Carbon::now()->subMinutes($minutes);

        return $query
            ->traccarJoin()
            ->where(function($q) use ($time){
                $q->where('traccar.server_time', '>', $time);
                $q->orWhere('traccar.ack_time', '>', $time);
            });
    }

    public function scopeOffline($query, $minutes = null) {
        if (is_null($minutes))
            $minutes = settings('main_settings.default_object_online_timeout');

        $time = Carbon::now()->subMinutes($minutes);

        return $query
            ->traccarJoin()
            ->where(function($q) use ($time){
                $q->where('traccar.server_time', '<', $time);
                $q->orWhere('traccar.ack_time', '<', $time);
            });
    }

    public function scopeMove($query) {

        return $query
            ->traccarJoin()
            ->engineOn()
            ->online()
            ->whereRaw('traccar.speed > devices.min_moving_speed');
    }

    public function scopeStop($query) {
        return $query
            ->traccarJoin()
            ->online()
            ->whereRaw('traccar.speed < devices.min_moving_speed');
    }

    public function scopeEngineOn($query) {
        return $query
            ->traccarJoin()
            ->online()
            ->whereRaw('traccar.engine_on_at > traccar.engine_off_at');
    }

    public function scopeEngineOff($query) {
        return $query
            ->traccarJoin()
            ->online()
            ->whereRaw('traccar.engine_off_at > traccar.engine_on_at');
    }

    public function scopeIdle($query) {
        return $query
            ->engineOn()
            ->stop()
            ->online();
    }
}
