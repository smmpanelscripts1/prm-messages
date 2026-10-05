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

    protected $casts = [
        'unread_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_read_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
