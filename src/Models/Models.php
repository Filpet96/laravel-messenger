<?php

namespace Cmgmyr\Messenger\Models;

class Models
{
    /**
     * Map for the messenger's models.
     *
     * @var array
     */
    protected static $models = [];

    /**
     * Map for the messenger's tables.
     *
     * @var array
     */
    protected static $tables = [];

    /**
     * Internal pointer name for the app's "user" model.
     *
     * @var string
     */
    private static $userModelLookupKey = 'User';

    /**
     * Set the model to be used for conversations.
     *
     * @param string $model
     */
    public static function setMessageModel($model)
    {
        static::$models[Message::class] = $model;
    }

    /**
     * Set the model to be used for participants.
     *
     * @param  string $model
     */
    public static function setParticipantModel($model)
    {
        static::$models[ConversationParticipant::class] = $model;
    }

    /**
     * Set the model to be used for conversations.
     *
     * @param  string $model
     */
    public static function setThreadModel($model)
    {
        static::$models[Conversation::class] = $model;
    }

    /**
     * Set the model to be used for users.
     *
     * @param  string  $model
     */
    public static function setUserModel($model)
    {
        static::$models[self::$userModelLookupKey] = $model;
    }

    /**
     * Set custom table names.
     *
     * @param  array $map
     */
    public static function setTables(array $map)
    {
        static::$tables = array_merge(static::$tables, $map);
    }

    /**
     * Get a custom table name mapping for the given table.
     *
     * @param  string $table
     * @return string
     */
    public static function table($table)
    {
        if (isset(static::$tables[$table])) {
            return static::$tables[$table];
        }

        return $table;
    }

    /**
     * Get the class name mapping for the given model.
     *
     * @param  string $model
     * @return string
     */
    public static function classname($model)
    {
        if (isset(static::$models[$model])) {
            return static::$models[$model];
        }

        return $model;
    }

    /**
     * Get an instance of the messages model.
     *
     * @param  array $attributes
     * @return \Cmgmyr\Messenger\Models\Message
     */
    public static function message(array $attributes = [])
    {
        return static::make(Message::class, $attributes);
    }

    /**
     * Get an instance of the participants model.
     *
     * @param  array $attributes
     * @return \Cmgmyr\Messenger\Models\ConversationParticipant
     */
    public static function participant(array $attributes = [])
    {
        return static::make(ConversationParticipant::class, $attributes);
    }

    /**
     * Get an instance of the conversations model.
     *
     * @param  array $attributes
     * @return \Cmgmyr\Messenger\Models\Conversation
     */
    public static function conversation(array $attributes = [])
    {
        return static::make(Conversation::class, $attributes);
    }

    /**
     * Get an instance of the user model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function user(array $attributes = [])
    {
        return static::make(self::$userModelLookupKey, $attributes);
    }

    /**
     * Get an instance of the given model.
     *
     * @param  string $model
     * @param  array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected static function make($model, array $attributes = [])
    {
        $model = static::classname($model);

        return new $model($attributes);
    }
}
