<?php namespace Tobuli\Entities;

use Tobuli\Entities\ChecklistTemplate;
use Eloquent;

class Checklist extends Eloquent
{
    protected $table = 'checklist';

    protected $fillable = [
        'template_id',
        'service_id',
        'name',
        'signature',
        'archived',
    ];

    public function template()
    {
        return $this->belongsTo('Tobuli\Entities\ChecklistTemplate', 'template_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo('Tobuli\Entities\DeviceService', 'service_id', 'id');
    }

    public function rows()
    {
        return $this->hasMany('Tobuli\Entities\ChecklistRow', 'checklist_id');
    }

    public function getTypeNameAttribute()
    {
        return ChecklistTemplate::getTypes($this->type);
    }

    public function scopeComplete($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function incompleteRows()
    {
        return $this->rows()
            ->where('completed', 0)
            ->get();
    }

    public static function getAvailableTemplates($serviceId)
    {
        $used = self::where('service_id', $serviceId)->get()->pluck('template_id');

        return ChecklistTemplate::whereNotIn('id', $used)->get();
    }
}
