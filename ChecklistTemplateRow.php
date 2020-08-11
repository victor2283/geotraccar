<?php namespace Tobuli\Entities;

use Eloquent;

class ChecklistTemplateRow extends Eloquent
{
    protected $table = 'checklist_template_row';

    protected $fillable = [
        'template_id',
        'activity',
    ];

    public function template()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistTemplate', 'template_id', 'id');
    }
}
