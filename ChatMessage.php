<?php
/**
 * Created by PhpStorm.
 * User: antanas
 * Date: 18.3.19
 * Time: 13.37
 */

namespace Tobuli\Entities;
use App\Events\NewMessage;
use Eloquent;


class ChatMessage extends Eloquent {

    const TYPE_TEXT = 1;
    const TYPE_PICTURE = 2;

    protected $fillable = ['sender_id', 'chat_id', 'content', 'type' ];
    protected $hidden = ['created_at', 'updated_at', 'sender'];

    protected $appends = ['sender_name', 'chat_url', 'chattable_id'];

    public function chat() {
        return $this->hasOne(Chat::class, 'id','chat_id');
    }

    public function sender() {
        return $this->hasOne(ChatParticipant::class, 'id', 'sender_id');
    }

    public function getChatUrlAttribute() {
        return route('chat.get', [$this->chat_id]);
    }

    public function getSenderNameAttribute() {
        if ( ! $this->sender)
            return 'N/A';

        if ( ! $this->sender->chattable)
            return 'N/A';

        return $this->sender->chattable->getChatableName();
    }

    public function getChattableIdAttribute() {
        if ( ! $this->sender)
            return null;

        if ( ! $this->sender->chattable)
            return null;

        return $this->sender->chattable->id;
    }

    public function isMyMessage($entity)
    {
        if ( ! $this->sender->chattable)
            return false;

        if (get_class($entity) == $this->sender->chattable_type && $this->sender->chattable->id == $entity->id)
            return true;

        return false;
    }

    public function setFrom($entity) {
        $this->sender_id = $entity->chats()->where('chat_participants.chat_id', $this->chat->id)->first()->id;

        return $this;
    }

    public function setTo($entities = null, $chat = null) {

        if ($chat) {
            $this->chat_id = $chat->id;
            return $this;
        }

        $chat = Chat::getRoom($entities);

        $this->chat_id = $chat->id;

        return $this;
    }

    public function setContent( $content, $type = self::TYPE_TEXT )
    {
        $this->content = $content;
        $this->type = $type;

        return $this;
    }

    public function send() {

        $this->save();

        event(new NewMessage($this));
    }
}