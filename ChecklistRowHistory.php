<?php namespace Tobuli\Entities;

use Eloquent;

class ChecklistRowHistory extends Eloquent
{
    protected $table = 'checklist_row_history';

    protected $fillable = [
        'checklist_history_id',
        'checklist_id',
        'checklist_row_id',
        'template_row_id',
        'activity',
        'completed',
        'completed_at',
    ];

    public $timestamps = false;

    public function checklistHistory()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistHistory', 'checklist_history_id', 'id');
    }

    public function checklist()
    {
        return $this->belongsTo('Tobuli\Entities\Checklist', 'checklist_id', 'id');
    }

    public function templateRow()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistTemplate', 'template_row_id', 'id');
    }

    // public function images()
    // {
    //     return $this
    //         ->hasMany('Tobuli\Entities\ChecklistImage', 'row_id', 'checklist_row_id')
    //         ->where('checklist_history_id', $this->checklist_history_id);
    // }
}
