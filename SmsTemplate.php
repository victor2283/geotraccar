<?php namespace Tobuli\Entities;

use Eloquent;
use Tobuli\Helpers\Templates\TemplateManager;

class SmsTemplate extends Eloquent {
	protected $table = 'sms_templates';

    protected $fillable = array('title', 'note');

    public $timestamps = false;

    public function buildTemplate($data, $user = null)
    {
        $template_builder = (new TemplateManager())->loadTemplateBuilder($this->name);

        return $template_builder->setUser($user)->buildTemplate($this, $data);
    }
}
