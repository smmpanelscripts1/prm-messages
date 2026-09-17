<?php

namespace Prm\Messages;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read User $user
 */
class PublicMessage extends AbstractModel
{
    const MAX_LENGTH = 1000;

    protected $table = 'pm_public_messages';

    protected $guarded = [];

    public $timestamps = true;

    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
