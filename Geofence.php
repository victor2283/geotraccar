<?php namespace Tobuli\Entities;

use Eloquent;

use Tobuli\Helpers\PolygonHelper;

class Geofence extends Eloquent {
	protected $table = 'geofences';

    protected $fillable = array('user_id', 'group_id', 'name', 'active', 'polygon_color', 'type', 'radius', 'center');

    protected $hidden = array('polygon');

    protected $casts = [
        'center' => 'array',
        'radius' => 'float'
    ];

    protected $polygonHelpers = [];

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function getGroupIdAttribute($value)
    {
        if (is_null($value))
            return 0;

        return $value;
    }

    public function setGroupIdAttribute($value)
    {
        if (empty($value))
            $value = null;

        $this->attributes['group_id'] = $value;
    }

    public function pointIn($data)
    {
        if (is_string($data))
        {
            $point = $data;
        }
        elseif (is_object($data))
        {
            $point = $data->latitude . ' ' . $data->longitude;
        }
        elseif (is_array($data))
        {
            $point = $data['latitude'] . ' ' . $data['longitude'];
        }
        else
        {
            return null;
        }

        if ($this->type == 'circle')
            return $this->pointInCircle($point);

        return $this->pointInPolygon($point);
    }

    public function pointOut($data)
    {
        return ! $this->pointIn($data);
    }

    /**
     * @param $point ['latitude' => x, 'longitude' => y]
     * @return float|int
     */
    public function pointAwayBy($point)
    {
        if ($this->pointIn($point))
            return 0;

        $center = $this->getPolygonCenter();

        return getDistance($center['lat'], $center['lng'], $point['latitude'], $point['longitude']);
    }

    private function pointInPolygon($point)
    {
        if ( ! isset($this->polygonHelpers[$this->id]))
        {
            $this->polygonHelpers[$this->id] = new PolygonHelper( parsePolygon(json_decode($this->coordinates, TRUE)) );
        }

        return false !== $this->polygonHelpers[$this->id]->pointInPolygon($point);
    }

    private function pointInCircle($point)
    {
        $center = $this->center;

        list($lat, $lng) = explode(' ', $point);

        return $this->radius > (getDistance($center['lat'], $center['lng'], $lat, $lng) * 1000);
    }

    private function getPolygonCenter()
    {
        if ($this->type == 'circle')
            return $this->center;

        if ( ! isset($this->polygonHelpers[$this->id]))
        {
            $this->polygonHelpers[$this->id] = new PolygonHelper( parsePolygon(json_decode($this->coordinates, TRUE)) );
        }

        return $this->polygonHelpers[$this->id]->getCenter();
    }
}
