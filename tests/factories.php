<?php

$faktory->define(['conversation', 'Cmgmyr\Messenger\Models\Conversation'], function ($f) {
    $f->subject = 'Sample conversation';
});

$faktory->define(['message', 'Cmgmyr\Messenger\Models\Message'], function ($f) {
    $f->user_id = 1;
    $f->thread_id = 1;
    $f->body = 'A message';
});

$faktory->define(['participant', 'Cmgmyr\Messenger\Models\ConversationParticipant'], function ($f) {
    $f->user_id = 1;
    $f->thread_id = 1;
});
