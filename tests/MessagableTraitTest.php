<?php

namespace Cmgmyr\Messenger\Test;

use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Conversation;
use Cmgmyr\Messenger\Traits\Messagable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class MessagableTraitTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        Eloquent::unguard();
    }

    /** @test */
    public function it_should_get_all_conversations_with_new_messages()
    {
        $user = User::create(
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'notify' => 'y',
            ]
        );

        $conversation = $this->faktory->create('conversation');
        $user_1 = $this->faktory->build('participant', ['user_id' => $user->id, 'last_read' => Carbon::yesterday()]);
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation->participants()->saveMany([$user_1, $user_2]);

        $message_1 = $this->faktory->build('message', ['user_id' => 2]);
        $conversation->messages()->saveMany([$message_1]);

        $conversation2 = $this->faktory->create('conversation');
        $user_1b = $this->faktory->build('participant', ['user_id' => 3, 'last_read' => Carbon::yesterday()]);
        $user_2b = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation2->participants()->saveMany([$user_1b, $user_2b]);

        $message_1b = $this->faktory->build('message', ['user_id' => 2]);
        $conversation2->messages()->saveMany([$message_1b]);

        $conversations = $user->conversationsWithNewMessages();
        $this->assertEquals(1, $conversations->first()->id);

        $this->assertEquals(1, $user->newThreadsCount());
    }

    /** @test */
    public function it_get_all_incoming_messages_count_for_user()
    {
        $user = User::create(
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'notify' => 'y',
            ]
        );

        $conversation_1 = $this->faktory->create('conversation');
        $participant_11 = $this->faktory->build('participant', ['user_id' => $user->id, 'last_read' => Carbon::now()->subDays(5)]);
        $participant_12 = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation_1->participants()->saveMany([$participant_11, $participant_12]);

        $conversation_2 = $this->faktory->create('conversation');
        $participant_21 = $this->faktory->build('participant', ['user_id' => 3, 'last_read' => Carbon::now()->subDays(5)]);
        $participant_22 = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation_2->participants()->saveMany([$participant_21, $participant_22]);

        for ($i = 0; $i < 10; $i++) {
            $conversation_1->messages()->saveMany([$this->faktory->build('message', ['user_id' => 2, 'created_at' => Carbon::now()->subDays(1)])]);
        }

        for ($i = 0; $i < 5; $i++) {
            $conversation_1->messages()->saveMany([$this->faktory->build('message', ['user_id' => 2, 'created_at' => Carbon::now()->subDays(10)])]);
        }

        $conversation_2->messages()->saveMany([$this->faktory->build('message', ['user_id' => 2])]);

        $this->assertEquals(10, $user->unreadMessagesCount());
    }

    /** @test */
    public function it_should_get_participant_conversations()
    {
        $user = User::create(
            [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
            ]
        );
        $conversation = $this->faktory->create('conversation');
        $user_1 = $this->faktory->build('participant', ['user_id' => $user->id]);
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation->participants()->saveMany([$user_1, $user_2]);

        $firstThread = $user->conversations->first();
        $this->assertInstanceOf(Conversation::class, $firstThread);
    }
}

class User extends Eloquent
{
    use Messagable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'notify'];
}
