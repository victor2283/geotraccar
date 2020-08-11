<?php namespace Tobuli\Entities;

use Eloquent;
use Tobuli\Services\NotificationService;

class Popup extends Eloquent {
	protected $table = 'popups';

    protected $fillable = ['title','content','position','active', 'show_every_days'];

    protected $hidden = ['active','show_every_days'];

    public $timestamps = false;


    public static function getPositions() {
        return [
            'top' => trans('global.top'),
            'top_right' => trans('global.top_right'),
            'top_left'=> trans('global.top_left'),
            'bottom_left'=> trans('global.bottom_left'),
            'bottom_right'=> trans('global.bottom_right'),
            'center'=> trans('global.center')
        ];
    }

    public function getPossibleShortcodes()
    {
        $fields = [];

        foreach (NotificationService::$ruleCollection as $rule) {
            $fields = array_merge(
                array_keys((new $rule())->shortcodes),
                $fields
            );
        }

        return array_unique($fields);
    }


    public function rules() {
        return $this->hasMany('Tobuli\Entities\PopupRule', 'popup_id');
    }

    public function getForm() {
        $fields = [];

        foreach (NotificationService::$ruleCollection as $rule) {
            $active = false;
            if ($instance = $this->isSaved($rule)) {
                $class = $rule::load($instance);
                $active = true;
            } else {
                $class = new $rule();
            }

            $fields[] = array_merge( $class->getActiveField($active), $class->getFields());
        }

        return view('admin::Popups._ruleFields')->with(['fields'=> $fields]);
    }

    public function isSaved($rule) {
        foreach ($this->rules as $ruleInstance) {
            if ($ruleInstance->rule_name != $rule)
                continue;

            return $ruleInstance;
        }

        return null;
    }
}
