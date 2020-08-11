<?php
/**
 * Created by PhpStorm.
 * User: antanas
 * Date: 18.3.12
 * Time: 15.05
 */

namespace Tobuli\Entities;

use Eloquent;
use Illuminate\Support\Facades\File;

class TaskStatus extends Eloquent {

    protected $table = 'task_status';

    const STATUS_NEW = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_FAILED = 3;
    const STATUS_DELAY = 4;
    const STATUS_COMPLETED = 9;

    public static $statuses = [
        self::STATUS_NEW         => 'front.task_new',
        self::STATUS_IN_PROGRESS => 'front.task_in_progress',
        self::STATUS_COMPLETED   => 'front.task_completed',
        self::STATUS_FAILED      => 'front.task_failed',
        self::STATUS_DELAY      => 'front.task_delay'
        
    ];

    protected $fillable = ['task_id', 'status', 'comment'];

    protected $mediaDir;

    public $signatureBase64;

    public function __construct()
    {
        parent::__construct();

        $this->mediaDir = base_path('public/images/taskSignatures/');
    }

    public static function boot()
    {
        parent::boot();

        static::saved(function($model)
        {
            $directory = $model->mediaDir;

            if ( ! is_dir($directory)) {
                mkdir($directory);
            }

            $filePath = $directory . $model->getSignatureName();

            File::put($filePath, base64_decode($model->signatureBase64));

            return true;
        });

        static::deleting(function($model)
        {
            $filePath = $model->mediaDir . $model->getSignatureName();

            if (File::exists($filePath))
                File::delete($filePath);

            return true;
        });
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function getSignatureName()
    {
        return md5($this->id)  . '.jpeg';
    }

    public function getSignatureUrlAttribute()
    {
        return route('tracker.task.signature', ['id' => $this->id]);
    }

    public function getSignatureAttribute()
    {
        $filePath = $this->mediaDir . $this->getSignatureName();

        if ( ! File::exists($filePath))
            return null;

        return File::get($filePath);
    }
}