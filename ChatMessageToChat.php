<?php
/**
 * Created by PhpStorm.
 * User: antanas
 * Date: 18.4.5
 * Time: 12.00
 */

namespace Tobuli\Entities;


use Eloquent;

class ChatMessageToChat extends Eloquent {

    protected $fillable = ['chat_id', 'message_id'];

}