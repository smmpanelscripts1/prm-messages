<?php

namespace Prm\Messages;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int $id
 * @property int $user_low_id
 * @property int $user_high_id
 * @property string|null $subject
 * @property int|null $last_message_id
 * @property int|null $last_user_id
 * @property string|null $last_message_preview
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read User $userLow
 * @property-read User $userHigh
 * @property-read User|null $lastUser
 * @property-read Collection|ConversationState[] $states
 * @property-read Collection|Message[] $messages
 */
class Conversation extends AbstractModel
{
    protected $table = 'pm_conversations';

    protected $guarded = [];

    public $timestamps = true;

    protected $dates = ['created_at', 'updated_at', 'last_message_at'];

    public function userLow()
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function userHigh()
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function lastUser()
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function states()
    {
        return $this->hasMany(ConversationState::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id')->orderBy('created_at');
    }

    public static function idsFor(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public function isParticipant(User $user): bool
    {
        $id = (int) $user->id;

        return $id === (int) $this->user_low_id || $id === (int) $this->user_high_id;
    }

    public function otherParty(User $actor): User
    {
        return (int) $actor->id === (int) $this->user_low_id
            ? $this->userHigh
            : $this->userLow;
    }

    public function attachForActor(User $actor): void
    {
        if (! $this->relationLoaded('userLow') || ! $this->relationLoaded('userHigh')) {
            $this->load(['userLow', 'userHigh']);
        }

        $this->setRelation('otherUser', $this->otherParty($actor));
    }

    public function stateFor(User $user): ?ConversationState
    {
        if ($this->relationLoaded('states')) {
            return $this->states->firstWhere('user_id', $user->id);
        }

        return $this->states()->where('user_id', $user->id)->first();
    }

    public static function startNew(User $a, User $b, ?string $subject = null): self
    {
        [$low, $high] = self::idsFor((int) $a->id, (int) $b->id);

        $conversation = new self();
        $conversation->user_low_id = $low;
        $conversation->user_high_id = $high;
        $conversation->subject = $subject ?: null;
        $conversation->last_message_at = Carbon::now();
        $conversation->save();

        foreach ([$low, $high] as $userId) {
            $state = new ConversationState();
            $state->conversation_id = $conversation->id;
            $state->user_id = $userId;
            $state->unread_count = 0;
            $state->save();
        }

        $conversation->load('states');

        return $conversation;
    }

    public function postMessage(User $actor, string $content): Message
    {
        $now = Carbon::now();
        $preview = mb_substr(trim(preg_replace('/\s+/u', ' ', $content) ?: $content), 0, 140);

        $message = new Message();
        $message->conversation_id = $this->id;
        $message->user_id = $actor->id;
        $message->content = $content;
        $message->save();

        $this->last_message_id = $message->id;
        $this->last_user_id = $actor->id;
        $this->last_message_preview = $preview;
        $this->last_message_at = $now;
        $this->save();

        $this->load('states');

        foreach ($this->states as $state) {
            if ((int) $state->user_id === (int) $actor->id) {
                $state->unread_count = 0;
                $state->last_read_at = $now;
                $state->hidden_at = null;
            } else {
                $state->unread_count = (int) $state->unread_count + 1;
                $state->hidden_at = null;
            }
            $state->save();
        }

        return $message;
    }

    public function markRead(User $actor): void
    {
        $state = $this->stateFor($actor);

        if (! $state) {
            return;
        }

        $state->unread_count = 0;
        $state->last_read_at = Carbon::now();
        $state->save();
    }

    public function hideFor(User $actor): void
    {
        $state = $this->stateFor($actor);

        if (! $state) {
            return;
        }

        $state->hidden_at = Carbon::now();
        $state->unread_count = 0;
        $state->save();
    }
}
