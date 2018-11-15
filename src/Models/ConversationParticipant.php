<?php

namespace Cmgmyr\Messenger\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationParticipant extends Eloquent
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'participants';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['thread_id', 'user_id', 'last_read'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'last_read'];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('participants');

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
}
