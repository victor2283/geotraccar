<?php namespace Tobuli\Entities;

use Eloquent;

class ReportLog extends Eloquent {
	protected $table = 'report_logs';

    protected $fillable = [
        'user_id',
        'email',
        'title',
        'type',
        'format',
        'size',
        'is_send',
        'error',
        'data',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function($model) {
            $model->updateTimestamps();

            $model->saveFile($model->data);

            $model->data = false;

            return true;
        });

        static::updating(function($model) {
            $model->saveFile($model->data);

            $model->data = false;

            return true;
        });

        static::deleting(function($model) {
            $model->deleteFile();

            return true;
        });
    }
	
	public function getDataAttribute( $value ) 
	{
        if (!$this->exists)
            return $value;

        $data = $this->getFile();

		return $data ? $data : base64_decode($value);
	}

    public function user() {
        return $this->belongsTo('Tobuli\Entities\User', 'user_id', 'id');
    }

    public function getFile() {
        $file = $this->getFilePath();

        if ( file_exists($file) )
            return @file_get_contents($file);

        return null;
    }

    public function saveFile($data) {
        $file = $this->getFilePath();

        return file_put_contents($file, $data);
    }

    public function deleteFile() {
        $file = $this->getFilePath();

        @unlink($file);
    }

    public function getFilename()
    {
        $unique = implode('.', [
            $this->user_id,
            $this->title,
            $this->type,
            $this->format,
            date('Y-m-d H:i:s', strtotime($this->created_at))
        ]);

        return 'report.' . md5($unique);
    }

    public function getFilePath() {
        return storage_path('app/' . $this->getFilename());
    }

}
