<?php namespace Tobuli\Entities;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'events_log';

    protected $guarded = [];

    public $timestamps = false;

    public function object()
    {
        return $this->morphTo();
    }
}
