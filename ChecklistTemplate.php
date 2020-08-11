<?php namespace Tobuli\Entities;

use Illuminate\Support\Facades\DB;
use Tobuli\Entities\ChecklistTemplateRow;
use Auth;
use Eloquent;

class ChecklistTemplate extends Eloquent
{
    const TYPE_PRE_START = 1;
    const TYPE_SERVICE = 2;

    protected $table = 'checklist_template';

    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        self::creating(function($entity) {
            if (empty($entity->user_id)) {
                $entity->user_id = Auth::user()->id;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function rows()
    {
        return $this->hasMany('Tobuli\Entities\ChecklistTemplateRow', 'template_id');
    }

    public function getTypeNameAttribute()
    {
        return self::getTypes($this->type);
    }

    public function saveRows($rows)
    {
        $newRows = array_filter($rows['new'] ?? []);
        unset($rows['new']);

        $this->rows()->whereNotIn('id', array_keys($rows))->delete();

        $newRows = array_map(function($value) {
            return [
                'activity' => $value,
                'template_id' => $this->id,
            ];
        }, $newRows);
        ChecklistTemplateRow::insert($newRows);

        DB::beginTransaction();

        foreach ($rows as $id => $value) {
            DB::table('checklist_template_row')
                ->where('id', '=', $id)
                ->update([
                    'activity' => $value,
                ]);
        }

        DB::commit();
    }

    public static function getTypes($type = null)
    {
        $types = [
            self::TYPE_PRE_START => trans('global.pre_start_checklist'),
            self::TYPE_SERVICE => trans('global.service_checklist'),
        ];

        return $types[$type] ?? $types;
    }

    public function scopeAvailable($query)
    {
        if (Auth::user()->isManager()) {
            $users = Auth::user()->subusers()->lists('id', 'id')->all();
        }

        $users[] = Auth::user()->id;

        return $query->whereIn('user_id', $users);
    }
}
