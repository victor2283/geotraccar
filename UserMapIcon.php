<?php namespace Tobuli\Entities;

use Eloquent;

class UserMapIcon extends Eloquent {
	protected $table = 'user_map_icons';

    protected $fillable = array('user_id', 'active', 'map_icon_id', 'name', 'description', 'coordinates');

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }
    
    public function mapIcon()
    {
        return $this->hasOne('Tobuli\Entities\MapIcon', 'id', 'map_icon_id');
    }
}
