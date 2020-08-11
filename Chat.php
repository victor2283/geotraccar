<?php
/**
 * Created by PhpStorm.
 * User: antanas
 * Date: 18.3.19
 * Time: 13.37
 */

namespace Tobuli\Entities;

use Eloquent;
use Illuminate\Support\Collection;


class Chat extends Eloquent {

    public function participants() {
        return $this->hasMany(ChatParticipant::class, 'chat_id', 'id');
    }

    public function messages() {
        return $this->hasMany(ChatMessage::class)->with(['sender'])->orderBy('id','desc');
    }

    public function getLastMessages()
    {
        $messages = $this->messages()->paginate();

        $_messages = $messages->items();
        $reversed  = $messages->reverse();

        foreach ($_messages as $key => $message)
            $messages->forget($key);

        foreach ($reversed as $key => $message)
            $messages->put($key, $message);

        return $messages;
    }

    public function getTitleAttribute() {
        $title = [];
        foreach ($this->participants as $participant)
        {
            if ( ! $participant->chattable)
                continue;

            $title[] = ($participant->chattable->name ? $participant->chattable->name : $participant->chattable->email );
        }

        return implode(' | ', $title);
    }

    public function addParticipant($participant)
    {
        return $this
            ->participants()
            ->firstOrCreate(['chattable_id' => $participant->id, 'chattable_type' => get_class($participant)]);
    }

    public function addParticipants($participants)
    {
        foreach ($participants as $participant)
        {
            $this->addParticipant($participant);
        }
    }

    public function scopeGetByDevice($query, Device $device)
    {
        return $query->getByParticipants([$device]);
    }

    public function scopeGetByParticipants($query, $participants)
    {
        foreach ($participants as $entity)
        {
            $query->whereHas('participants', function ($query) use ($entity) {
                $query->where('chattable_id', '=', $entity->id)->where('chattable_type', get_class($entity));
            });
        }

        return $query;
    }

    public static function getRoom($participants) {
        $chat = self::getByParticipants($participants)->first();

        if ( ! $chat) {
            $chat = self::createRoom($participants);
        }

        return $chat;
    }

    public static function getRoomByDevice(Device $device) {
        $chat = self::getByDevice($device)->first();

        if ( ! $chat) {
            $participants = new Collection();
            $participants->push($device);
            $participants = $participants->merge($device->users);
            $participants = $participants->all();

            $chat =  self::createRoom($participants);
        }
/*
        ChatParticipant::whereNotIn('chattable_id', $device->users->pluck('id'))
            ->where('chat_id', $chat->id)
            ->where('chattable_type', User::class)->delete();


        foreach ($device->users as $user) {
            ChatParticipant::firstOrCreate(['chattable_id' => $user->id, 'chattable_type' => User::class, 'chat_id' => $chat->id]);
        }
*/
        return $chat;
    }

    private static function createRoom($participants) {
        $chat = new Chat();
        $chat->save();

        $chat->addParticipants($participants);

        return $chat;
    }

    public function getRoomHashAttribute() {
        return md5('message_for_'. $this->id);
    }

}