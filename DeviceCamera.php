<?php namespace Tobuli\Entities;

use Eloquent;
use App\Jobs\DeviceCameraCreate;
use App\Jobs\DeviceCameraDelete;
use Auth;

class DeviceCamera extends Eloquent {
    protected $table = 'device_cameras';

    protected $fillable = [
        'device_id',
        'name',
        'show_widget',
        'ftp_username',
        'ftp_password',
    ];

    public $timestamps = true;

    protected static function boot() {
        parent::boot();

        static::created(function ($camera) {
            dispatch(new DeviceCameraCreate($camera, Auth::user()));
        });

        static::updated(function ($camera) {
            //@TODO: dispatch update job
        });

        static::deleting(function ($camera) {
            dispatch(new DeviceCameraDelete($camera));
        });
      }

    public function device() {
        return $this->belongsTo('Tobuli\Entities\Device', 'device_id', 'id');
    }
}
