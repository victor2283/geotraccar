<?php namespace Tobuli\Entities;

use Eloquent;

class TrackerPort extends Eloquent {
	protected $table = 'tracker_ports';

    protected $fillable = array('active', 'port', 'name', 'extra');

}
