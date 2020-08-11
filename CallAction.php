<?php
namespace Tobuli\Entities;

use ModalHelpers\AlertModalHelper;
use Tobuli\Traits\Filterable;
use Auth;
use Eloquent;
use Formatter;

class CallAction extends Eloquent
{
    use Filterable;

    private $filterables = [
        'user_id',
        'device_id',
        'event.type',
        'alert_id',
    ];

    protected $table = 'call_actions';

    protected $fillable = [
        'device_id',
        'user_id',
        'event_id',
        'alert_id',
        'called_at',
        'response_type',
        'remarks',
    ];

    public function user()
    {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function device()
    {
        return $this->belongsTo('Tobuli\Entities\Device', 'device_id', 'id');
    }

    public function event()
    {
        return $this->belongsTo('Tobuli\Entities\Event', 'event_id', 'id');
    }

    public function alert()
    {
        return $this->belongsTo('Tobuli\Entities\Alert', 'alert_id', 'id');
    }

    public function scopeByUser($query, $user = null)
    {
        if (is_null($user)) {
            $user = Auth::user();
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isManager()) {
            $query->whereIn('user_id', function ($q) use ($user) {
                    $q->select('users.id')
                        ->from('users')
                        ->where('users.id', $user->id)
                        ->orWhere('users.manager_id', $user->id);
                });
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public function getResponseTypeTitleAttribute()
    {
        return array_get(self::getResponseType($this->response_type), 'title', '-');
    }

    public function getCalledAtAttribute($value)
    {
        return $value == '0000-00-00 00:00:00' ? null : $value;
    }

    public function getConvertedCalledAtAttribute()
    {
        return $this->called_at ? Formatter::time()->human($this->called_at) : null;
    }

    public static function getResponseTypes()
    {
        $types = [
            [
                'type' => 'answer',
                'title' => trans('front.answer'),
            ],
            [
                'type' => 'no_answer',
                'title' => trans('front.no_answer'),
            ],
            [
                'type' => 'no_response',
                'title' => trans('front.no_response'),
            ],
        ];

        return $types;
    }

    public static function getResponseType($type)
    {
        $types = collect(self::getResponseTypes());

        return $types
            ->where('type', $type)
            ->first();
    }

    public static function getFilterValues()
    {
        $callActions = self::byUser()
            ->with('device', 'event', 'alert')
            ->get();

        return [
            'devices' => $callActions
                ->pluck('device')
                ->pluck('id')
                ->toArray(),
            'events' => $callActions
                ->pluck('event')
                ->pluck('type')
                ->toArray(),
            'alerts' => $callActions
                ->pluck('alert')
                ->pluck('id')
                ->toArray(),
        ];
    }
}
