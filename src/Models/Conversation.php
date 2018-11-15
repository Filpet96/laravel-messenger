<?php

namespace Cmgmyr\Messenger\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Eloquent
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'conversations';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $fillable = ['conversation_name'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Internal cache for creator.
     *
     * @var null|Models::user()
     */
    protected $creatorCache = null;
    
    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('conversations');

        parent::__construct($attributes);
    }

    /**
     * Messages relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function messages()
    {
        return $this->hasMany(Models::classname(Message::class), 'conversation_id', 'id');
    }

    /**
     * Returns the latest message from a conversation.
     *
     * @return null|\Cmgmyr\Messenger\Models\Message
     */
    public function getLatestMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * ConversationParticipants relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @codeCoverageIgnore
     */
    public function participants()
    {
        return $this->hasMany(Models::classname(ConversationParticipant::class), 'conversation_id', 'id');
    }

    /**
     * User's relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     *
     * @codeCoverageIgnore
     */
    public function users()
    {
        return $this->belongsToMany(Models::classname('User'), Models::table('conversation_participants'), 'conversation_id', 'user_id');
    }
    
    public function getUsersExcludeUser($userid)
    {
         return $this->users()->where('user_id','!=', $userid)->get();
    }

    /**
     * Returns the user object that created the conversation.
     *
     * @return Models::user()
     */
    public function creator()
    {
        if (is_null($this->creatorCache)) {
            $firstMessage = $this->messages()->withTrashed()->oldest()->first();
            $this->creatorCache = $firstMessage ? $firstMessage->user : Models::user();
        }

        return $this->creatorCache;
    }

    /**
     * Returns all of the latest conversations by updated_at date.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public static function getAllLatest()
    {
        return static::latest('updated_at');
    }

    /**
     * Returns all conversations by subject.
     *
     * @param string $subject
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getBySubject($conversation_name)
    {
        return static::where('conversation_name', 'like', $conversation_name)->get();
    }

    /**
     * Returns an array of user ids that are associated with the conversation.
     *
     * @param null $userId
     *
     * @return array
     */
    public function participantsUserIds($userId = null)
    {
        $users = $this->participants()->withTrashed()->select('user_id')->get()->map(function ($participant) {
            return $participant->user_id;
        });

        if ($userId) {
            $users->push($userId);
        }

        return $users->toArray();
    }

    /**
     * Returns conversations that the user is associated with.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser(Builder $query, $userId)
    {
        $participantsTable = Models::table('conversation_participants');
        $conversationsTable = Models::table('conversations');

        return $query->join($participantsTable, $this->getQualifiedKeyName(), '=', $participantsTable . '.conversation_id')
            ->where($participantsTable . '.user_id', $userId)
            ->where($participantsTable . '.deleted_at', null)
            ->select($conversationsTable . '.*');
    }

    /**
     * Returns conversations with new messages that the user is associated with.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUserWithNewMessages(Builder $query, $userId)
    {
        $participantTable = Models::table('conversation_participants');
        $conversationsTable = Models::table('conversations');

        return $query->join($participantTable, $this->getQualifiedKeyName(), '=', $participantTable . '.conversation_id')
            ->where($participantTable . '.user_id', $userId)
            ->whereNull($participantTable . '.deleted_at')
            ->where(function (Builder $query) use ($participantTable, $conversationsTable) {
                $query->where($conversationsTable . '.updated_at', '>', $this->getConnection()->raw($this->getConnection()->getTablePrefix() . $participantTable . '.last_read'))
                    ->orWhereNull($participantTable . '.last_read');
            })
            ->select($conversationsTable . '.*');
    }

    /**
     * Returns conversations between given user ids.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $participants
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetween(Builder $query, array $participants)
    {
        return $query->whereHas('participants', function (Builder $q) use ($participants) {
            $q->whereIn('user_id', $participants)
                ->select($this->getConnection()->raw('DISTINCT(conversation_id)'))
                ->groupBy('conversation_id')
                ->havingRaw('COUNT(conversation_id)=' . count($participants));
        });
    }

    /**
     * Add users to conversation as participants.
     *
     * @param array|mixed $userId
     *
     * @return void
     */
    public function addParticipant($userId)
    {
        $userIds = is_array($userId) ? $userId : (array) func_get_args();

        collect($userIds)->each(function ($userId) {
            Models::participant()->firstOrCreate([
                'user_id' => $userId,
                'conversation_id' => $this->id,
            ]);
        });
    }

    /**
     * Remove participants from conversation.
     *
     * @param array|mixed $userId
     *
     * @return void
     */
    public function removeParticipant($userId)
    {
        $userIds = is_array($userId) ? $userId : (array) func_get_args();

        Models::participant()->where('conversation_id', $this->id)->whereIn('user_id', $userIds)->delete();
    }

    /**
     * Mark a conversation as read for a user.
     *
     * @param int $userId
     *
     * @return void
     */
    public function markAsRead($userId)
    {
        try {
            $participant = $this->getParticipantFromUser($userId);
            $participant->last_read = new Carbon();
            $participant->save();
        } catch (ModelNotFoundException $e) { // @codeCoverageIgnore
            // do nothing
        }
    }

    /**
     * See if the current conversation is unread by the user.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function isUnread($userId)
    {
        try {
            $participant = $this->getParticipantFromUser($userId);

            if ($participant->last_read === null || $this->updated_at->gt($participant->last_read)) {
                return true;
            }
        } catch (ModelNotFoundException $e) { // @codeCoverageIgnore
            // do nothing
        }

        return false;
    }

    /**
     * Finds the participant record from a user id.
     *
     * @param $userId
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getParticipantFromUser($userId)
    {
        return $this->participants()->where('user_id', $userId)->firstOrFail();
    }

    /**
     * Restores all participants within a conversation that has a new message.
     *
     * @return void
     */
    public function activateAllParticipants()
    {
        $participants = $this->participants()->withTrashed()->get();
        foreach ($participants as $participant) {
            $participant->restore();
        }
    }

    /**
     * Generates a string of participant information.
     *
     * @param null|int $userId
     * @param array $columns
     *
     * @return string
     */
    public function participantsString($userId = null, $columns = ['name'])
    {
        $participantsTable = Models::table('conversation_participants');
        $usersTable = Models::table('users');
        $userPrimaryKey = Models::user()->getKeyName();

        $selectString = $this->createSelectString($columns);

        $participantNames = $this->getConnection()->table($usersTable)
            ->join($participantsTable, $usersTable . '.' . $userPrimaryKey, '=', $participantsTable . '.user_id')
            ->where($participantsTable . '.conversation_id', $this->id)
            ->select($this->getConnection()->raw($selectString));

        if ($userId !== null) {
            $participantNames->where($usersTable . '.' . $userPrimaryKey, '!=', $userId);
        }

        return $participantNames->implode('name', ', ');
    }

    /**
     * Checks to see if a user is a current participant of the conversation.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function hasParticipant($userId)
    {
        $participants = $this->participants()->where('user_id', '=', $userId);
        if ($participants->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Generates a select string used in participantsString().
     *
     * @param array $columns
     *
     * @return string
     */
    protected function createSelectString($columns)
    {
        $dbDriver = $this->getConnection()->getDriverName();
        $tablePrefix = $this->getConnection()->getTablePrefix();
        $usersTable = Models::table('users');

        switch ($dbDriver) {
        case 'pgsql':
        case 'sqlite':
            $columnString = implode(" || ' ' || " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = '(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
            break;
        case 'sqlsrv':
            $columnString = implode(" + ' ' + " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = '(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
            break;
        default:
            $columnString = implode(", ' ', " . $tablePrefix . $usersTable . '.', $columns);
            $selectString = 'concat(' . $tablePrefix . $usersTable . '.' . $columnString . ') as name';
        }

        return $selectString;
    }

    /**
     * Returns array of unread messages in conversation for given user.
     *
     * @param int $userId
     *
     * @return \Illuminate\Support\Collection
     */
    public function userUnreadMessages($userId)
    {
        $messages = $this->messages()->get();

        try {
            $participant = $this->getParticipantFromUser($userId);
        } catch (ModelNotFoundException $e) {
            return collect();
        }

        if (!$participant->last_read) {
            return $messages;
        }

        return $messages->filter(function ($message) use ($participant) {
            return $message->updated_at->gt($participant->last_read);
        });
    }

    /**
     * Returns count of unread messages in conversation for given user.
     *
     * @param int $userId
     *
     * @return int
     */
    public function userUnreadMessagesCount($userId)
    {
        return $this->userUnreadMessages($userId)->count();
    }
}
