<?php

namespace Prm\Messages;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int $unread_count
 * @property Carbon|null $last_read_at
 * @property Carbon|null $hidden_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Conversation $conversation
 * @property-read User $user
 */
class ConversationState extends AbstractModel
{
    protected $table = 'pm_conversation_states';

    protected $guarded = [];

    public $timestamps = true;

    protected $dates = ['created_at', 'updated_at', 'last_read_at', 'hidden_at'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
