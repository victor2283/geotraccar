<?php namespace Tobuli\Entities;

use Illuminate\Support\Facades\File;
use Eloquent;

class ChecklistImage extends Eloquent
{
    protected $table = 'checklist_images';

    protected $fillable = [
        'checklist_id',
        'row_id',
        'checklist_history_id',
        'path',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function($entity){
            if (! $entity->path) {
                return;
            }

            $path = public_path($entity->path);

            if (! File::exists($path) || ! File::isFile($path)) {
                return;
            }

            File::delete($path);
        });
    }

    public function checklist()
    {
        return $this->belongsTo('Tobuli\Entities\Checklist', 'checklist_id', 'id');
    }

    public function checklistRow()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistRow', 'row_id', 'id');
    }
}
