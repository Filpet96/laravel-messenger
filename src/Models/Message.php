<?php

namespace Cmgmyr\Messenger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Eloquent
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'messages';

    /**
     * The relationships that should be touched on save.
     *
     * @var array
     */
    protected $touches = ['conversation'];

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['thread_id', 'user_id', 'body'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('messages');

        parent::__construct($attributes);
    }

    /**
     * Conversation relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @codeCoverageIgnore
     */
    public function conversation()
    {
        return $this->belongsTo(Models::classname(Conversation::class), 'thread_id', 'id');
    }

    /**
     * User relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @codeCoverageIgnore
     */
    public function user()
    {
        return $this->belongsTo(Models::user(), 'user_id');
    }

    /**
     * Participants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function participants()
    {
        return $this->hasMany(Models::classname(ConversationParticipant::class), 'thread_id', 'thread_id');
    }

    /**
     * Recipients of this message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recipients()
    {
        return $this->participants()->where('user_id', '!=', $this->user_id);
    }

    /**
     * Returns unread messages given the userId.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnreadForUser(Builder $query, $userId)
    {
        return $query->has('conversation')
            ->where('user_id', '!=', $userId)
            ->whereHas('participants', function (Builder $query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where(function (Builder $q) {
                        $q->where('last_read', '<', $this->getConnection()->raw($this->getConnection()->getTablePrefix() . $this->getTable() . '.created_at'))
                            ->orWhereNull('last_read');
                    });
            });
    }
}
