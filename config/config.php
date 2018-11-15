<?php

return [

    // 'user_model' => App\Models\User::class,

    'message_model' => Cmgmyr\Messenger\Models\Message::class,

    'conversation_participant_model' => Cmgmyr\Messenger\Models\ConversationParticipant::class,

    'conversation_model' => Cmgmyr\Messenger\Models\Conversation::class,

    /**
     * Define custom database table names - without prefixes.
     */
    'messages_table' => null,

    'conversation_participants_table' => null,

    'conversations_table' => null,
];
