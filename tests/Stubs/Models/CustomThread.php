<?php

namespace Cmgmyr\Messenger\Test\Stubs\Models;

use Cmgmyr\Messenger\Models\Conversation;

class CustomThread extends Conversation
{
    protected $table = 'custom_threads';
}
