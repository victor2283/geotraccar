<?php namespace Tobuli\Entities;

use Eloquent;
use Tobuli\Helpers\Templates\TemplateManager;

class EmailTemplate extends Eloquent {
	protected $table = 'email_templates';

    protected $fillable = array('title', 'note');

    public $timestamps = false;

    public function buildTemplate($data)
    {
        $template_builder = (new TemplateManager())->loadTemplateBuilder($this->name);

        return $template_builder->buildTemplate($this, $data);
    }
}
