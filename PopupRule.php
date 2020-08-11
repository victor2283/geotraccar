<?php namespace Tobuli\Entities;

use Eloquent;

class PopupRule extends Eloquent {
	protected $table = 'popup_rules';

    protected $fillable = ['popup_id','field_name', 'field_value', 'rule_name'];

    public $timestamps = false;


}
