<?php

namespace Cmgmyr\Messenger\Test\Stubs\Models;

use Cmgmyr\Messenger\Models\ConversationParticipant;

class CustomParticipant extends ConversationParticipant
{
    protected $table = 'custom_participants';
}
