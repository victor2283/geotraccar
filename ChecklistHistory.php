<?php namespace Tobuli\Entities;

use Tobuli\Entities\ChecklistTemplate;
use Eloquent;

class ChecklistHistory extends Eloquent
{
    protected $table = 'checklist_history';

    protected $fillable = [
        'template_id',
        'checklist_id',
        'service_id',
        'name',
        'signature',
        'completed_at',
    ];

    public function template()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistTemplate', 'template_id', 'id');
    }

    public function checklist()
    {
        return $this->belongsTo('Tobuli\Entities\Checklist', 'checklist_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo('Tobuli\Entities\DeviceService', 'service_id', 'id');
    }

    public function rows()
    {
        return $this->hasMany('Tobuli\Entities\ChecklistRowHistory', 'checklist_history_id');
    }
}
