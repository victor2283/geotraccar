<?php namespace Tobuli\Entities;

use Carbon\Carbon;
use Facades\Repositories\UserRepo;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Tobuli\Traits\Filterable;
use Tobuli\Traits\Searchable;

class Sharing extends Model {
    use Searchable, Filterable;

    private $searchable = [];
    private $filterables = [
        'devices.id',
    ];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sharing';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    protected $fillable = ['name', 'expiration_date', 'active', 'delete_after_expiration'];

    public function user()
    {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function devices()
    {
        return $this
            ->belongsToMany('Tobuli\Entities\Device', 'sharing_device_pivot', 'sharing_id', 'device_id')
            ->withPivot('user_id', 'expiration_date', 'active')
            ->join('user_device_pivot', function ($join) {
                $join
                    ->on('user_device_pivot.device_id', '=', 'sharing_device_pivot.device_id')
                    ->on('user_device_pivot.user_id', '=', 'sharing_device_pivot.user_id');
            })
            ->groupBy('devices.id');
    }

    public function activeDevices()
    {
        return $this
            ->devices()
            ->where('sharing_device_pivot.active', 1)
            ->where(function ($query) {
                $query->whereNull('sharing_device_pivot.expiration_date')
                ->orWhere('sharing_device_pivot.expiration_date', '>', Carbon::now());
            });
    }

    public function getDeviceById($deviceId)
    {
        return $this->devices()->find($deviceId);
    }

    public function generateHash()
    {
        do {
            $this->hash = md5(uniqid(), false);
        } while (Sharing::where('hash', $this->hash)->first());
    }

    public function isActive()
    {
        return $this->active && (is_null($this->expiration_date) || (new Carbon($this->expiration_date))->isFuture());
    }

    public function getLinkAttribute()
    {
        return route('sharing', ['hash' => $this->hash]);
    }

    public function setExpirationDateAttribute($value)
    {
        if (empty($value))
            $value = null;

        $this->attributes['expiration_date'] = $value;
    }

    public function scopeWithoutDevices($query, $deviceIds)
    {
        return $query
            ->whereDoesntHave('devices', function ($query) use ($deviceIds) {
                $query->whereIn('device_id', $deviceIds);
            });
    }

    public function scopeExpired($query)
    {
        return $query
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', Carbon::now());
    }
}
