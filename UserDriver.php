<?php namespace Tobuli\Entities;

use Eloquent;

class UserDriver extends Eloquent {

    const IMAGE_PATH = 'images/user_driver/';

	protected $table = 'user_drivers';

    protected $fillable = array(
        'user_id',
        'device_id',
        'photo',
        'name',
        'rfid',
        'phone',
        'email',
        'license',
        'type',
        'expedition_date',
        'expiration_date',
        'description'
    );

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function device() {
        return $this->hasOne('Tobuli\Entities\Device', 'id', 'device_id');
    }

    public function getImageAttribute()
    {
        return !empty($this->photo) ? str_finish(self::IMAGE_PATH, '/') . "{$this->photo}" : "";
    }
}
