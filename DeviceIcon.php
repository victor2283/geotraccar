<?php namespace Tobuli\Entities;

use Eloquent;

class DeviceIcon extends Eloquent
{
    protected $table = 'device_icons';

    protected $fillable = ['path', 'width', 'height', 'type', 'by_status'];

    protected $casts = ['id' => 'integer', 'order' => 'integer', 'width' => 'float', 'height' => 'float'];

    public $timestamps = false;

    public $deviceStatus;

    public function user()
    {
        return $this->belongsTo('Tobuli\Entities\User');
    }

    public function device()
    {
        return $this->belongsTo('Tobuli\Entities\Device');
    }

    public function setStatus($status)
    {
        $this->deviceStatus = $status;

        return $this;
    }

    public function getPathAttribute($value)
    {
        if ( ! $this->by_status)
            return $value;

        if ( ! $this->deviceStatus)
            return $value;

        $path_info = pathinfo($value);

        $filename_parts = explode('_', $path_info['filename']);
        array_pop($filename_parts);
        $filename_parts[] = $this->deviceStatus;

        $status_icons = glob($path_info['dirname'] . '/' . implode('_', $filename_parts) . '.*');

        return empty($status_icons) ? $value : current($status_icons);
    }
}
