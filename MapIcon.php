<?php namespace Tobuli\Entities;

use Eloquent;

class MapIcon extends Eloquent
{
    protected $table = 'map_icons';

    protected $fillable = ['path', 'width', 'height'];

    public $timestamps = false;

    protected $appends = ['url'];

    public function getUrlAttribute($value)
    {
        return asset($this->path);
    }
}
