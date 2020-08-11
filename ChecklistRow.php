<?php namespace Tobuli\Entities;

use Eloquent;

class ChecklistRow extends Eloquent
{
    protected $table = 'checklist_row';

    protected $fillable = [
        'checklist_id',
        'template_row_id',
        'completed',
        'completed_at',
    ];

    public $timestamps = false;

    public function checklist()
    {
        return $this->belongsTo('Tobuli\Entities\Checklist', 'checklist_id', 'id');
    }

    public function templateRow()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistTemplate', 'template_row_id', 'id');
    }

    public function images()
    {
        return $this
            ->hasMany('Tobuli\Entities\ChecklistImage', 'row_id')
            ->whereNull('checklist_history_id');
    }

    public function saveImage($path)
    {
        $image = new ChecklistImage([
            'checklist_id' => $this->checklist_id,
            'row_id' => $this->id,
            'path' => $path,
        ]);
        $image->save();

        return $image;
    }
}
