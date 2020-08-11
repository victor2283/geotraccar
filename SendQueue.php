<?php

namespace Tobuli\Entities;


use Eloquent;


class SendQueue extends Eloquent
{
    protected $table = 'send_queue';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'data_type',
        'channels'
    ];

    protected $casts = [
        'data'     => 'object',
        'channels' => 'array'
    ];

    protected $appends = [];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function target()
    {
        return $this->morphTo();
    }

    public function setDataAttribute($value)
    {
        if ($class = get_class($value))
            $this->data_type = $class;

        if ($value instanceof Eloquent)
            $value = $value->toArray();

        unset($value['geofence']);

        $this->attributes['data'] = json_encode($value);
    }

    public function getDataAttribute($value)
    {
        $data = json_decode($value, true);

        if ( ! $data)
            return null;

        if ($this->data_type)
            return new $this->data_type($data);

        return $data;
    }

    public function toArrayMassInsert()
    {
        return array_intersect_key(
            $this->getAttributes(),
            array_flip($this->getFillable())
        );
    }
}
