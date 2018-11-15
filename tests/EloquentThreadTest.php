<?php

namespace Cmgmyr\Messenger\Test;

use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Models;
use Cmgmyr\Messenger\Models\ConversationParticipant;
use Cmgmyr\Messenger\Models\Conversation;
use Illuminate\Database\Eloquent\Model as Eloquent;
use ReflectionClass;

class EloquentThreadTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        Eloquent::unguard();
    }

    /**
     * Activate private/protected methods for testing.
     *
     * @param $name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name)
    {
        $class = new ReflectionClass(Conversation::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /** @test */
    public function search_specific_conversation_by_subject()
    {
        $this->faktory->create('conversation', ['id' => 1, 'subject' => 'first subject']);
        $this->faktory->create('conversation', ['id' => 2, 'subject' => 'second subject']);

        $conversations = Conversation::getBySubject('first subject');

        $this->assertEquals(1, $conversations->count());
        $this->assertEquals(1, $conversations->first()->id);
        $this->assertEquals('first subject', $conversations->first()->subject);
    }

    /** @test */
    public function search_conversations_by_subject()
    {
        $this->faktory->create('conversation', ['id' => 1, 'subject' => 'first subject']);
        $this->faktory->create('conversation', ['id' => 2, 'subject' => 'second subject']);

        $conversations = Conversation::getBySubject('%subject');

        $this->assertEquals(2, $conversations->count());

        $this->assertEquals(1, $conversations->first()->id);
        $this->assertEquals('first subject', $conversations->first()->subject);

        $this->assertEquals(2, $conversations->last()->id);
        $this->assertEquals('second subject', $conversations->last()->subject);
    }

    /** @test */
    public function it_should_create_a_new_conversation()
    {
        $conversation = $this->faktory->build('conversation');
        $this->assertEquals('Sample conversation', $conversation->subject);

        $conversation = $this->faktory->build('conversation', ['subject' => 'Second sample conversation']);
        $this->assertEquals('Second sample conversation', $conversation->subject);
    }

    /** @test */
    public function it_should_return_the_latest_message()
    {
        $oldMessage = $this->faktory->build('message', [
            'created_at' => Carbon::yesterday(),
        ]);

        $newMessage = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'This is the most recent message',
        ]);

        $conversation = $this->faktory->create('conversation');
        $conversation->messages()->saveMany([$oldMessage, $newMessage]);
        $this->assertEquals($newMessage->body, $conversation->latestMessage->body);
    }

    /** @test */
    public function it_should_return_all_conversations()
    {
        $conversationCount = rand(5, 20);

        foreach (range(1, $conversationCount) as $index) {
            $this->faktory->create('conversation', ['id' => ($index + 1)]);
        }

        $conversations = Conversation::getAllLatest()->get();

        $this->assertCount($conversationCount, $conversations);
    }

    /** @test */
    public function it_should_get_all_conversation_participants()
    {
        $conversation = $this->faktory->create('conversation');
        $participantIds = $conversation->participantsUserIds();
        $this->assertCount(0, $participantIds);

        $user_1 = $this->faktory->build('participant');
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $user_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $conversation->participants()->saveMany([$user_1, $user_2, $user_3]);

        $participantIds = $conversation->participantsUserIds();
        $this->assertCount(3, $participantIds);
        $this->assertEquals(2, $participantIds[1]);

        $participantIds = $conversation->participantsUserIds(999);
        $this->assertCount(4, $participantIds);
        $this->assertEquals(999, end($participantIds));

        $this->assertInternalType('array', $participantIds);
    }

    /** @test */
    public function it_should_get_all_conversations_for_a_user()
    {
        $userId = 1;

        $participant_1 = $this->faktory->create('participant', ['user_id' => $userId]);
        $conversation = $this->faktory->create('conversation');
        $conversation->participants()->saveMany([$participant_1]);

        $conversation2 = $this->faktory->create('conversation', ['subject' => 'Second Conversation']);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $userId, 'conversation_id' => $conversation2->id]);
        $conversation2->participants()->saveMany([$participant_2]);

        $conversations = Conversation::forUser($userId)->get();
        $this->assertCount(2, $conversations);
    }

    /** @test */
    public function it_should_get_all_user_entities_for_a_conversation()
    {
        $conversation = $this->faktory->create('conversation');
        $user_1 = $this->faktory->build('participant');
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $conversation->participants()->saveMany([$user_1, $user_2]);

        $conversationUserIds = $conversation->users()->get()->pluck('id')->toArray();
        $this->assertArraySubset([1, 2], $conversationUserIds);
    }

    /** @test */
    public function it_should_get_all_conversations_for_a_user_with_new_messages()
    {
        $userId = 1;

        $participant_1 = $this->faktory->create('participant', ['user_id' => $userId, 'last_read' => Carbon::now()]);
        $conversation = $this->faktory->create('conversation', ['updated_at' => Carbon::yesterday()]);
        $conversation->participants()->saveMany([$participant_1]);

        $conversation2 = $this->faktory->create('conversation', ['subject' => 'Second Conversation', 'updated_at' => Carbon::now()]);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $userId, 'conversation_id' => $conversation2->id, 'last_read' => Carbon::yesterday()]);
        $conversation2->participants()->saveMany([$participant_2]);

        $conversations = Conversation::forUserWithNewMessages($userId)->get();
        $this->assertCount(1, $conversations);
    }

    /** @test */
    public function it_should_get_all_conversations_shared_by_specified_users()
    {
        $userId = 1;
        $userId2 = 2;

        $conversation = $this->faktory->create('conversation');
        $conversation2 = $this->faktory->create('conversation');

        $this->faktory->create('participant', ['user_id' => $userId, 'conversation_id' => $conversation->id]);
        $this->faktory->create('participant', ['user_id' => $userId2, 'conversation_id' => $conversation->id]);
        $this->faktory->create('participant', ['user_id' => $userId, 'conversation_id' => $conversation2->id]);

        $conversations = Conversation::between([$userId, $userId2])->get();
        $this->assertCount(1, $conversations);
    }

    /** @test */
    public function it_should_add_a_participant_to_a_conversation()
    {
        $participant = 1;

        $conversation = $this->faktory->create('conversation');

        $conversation->addParticipant($participant);

        $this->assertEquals(1, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_add_participants_to_a_conversation_with_array()
    {
        $participants = [1, 2, 3];

        $conversation = $this->faktory->create('conversation');

        $conversation->addParticipant($participants);

        $this->assertEquals(3, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_add_participants_to_a_conversation_with_arguments()
    {
        $conversation = $this->faktory->create('conversation');

        $conversation->addParticipant(1, 2);

        $this->assertEquals(2, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_mark_the_participant_as_read()
    {
        $userId = 1;
        $last_read = Carbon::yesterday();

        $participant = $this->faktory->create('participant', ['user_id' => $userId, 'last_read' => $last_read]);
        $conversation = $this->faktory->create('conversation');
        $conversation->participants()->saveMany([$participant]);

        $conversation->markAsRead($userId);

        $this->assertNotEquals($conversation->getParticipantFromUser($userId)->last_read, $last_read);
    }

    /** @test */
    public function it_should_see_if_conversation_is_unread_by_user()
    {
        $userId = 1;

        $participant_1 = $this->faktory->create('participant', ['user_id' => $userId, 'last_read' => Carbon::now()]);
        $conversation = $this->faktory->create('conversation', ['updated_at' => Carbon::yesterday()]);
        $conversation->participants()->saveMany([$participant_1]);

        $this->assertFalse($conversation->isUnread($userId));

        $conversation2 = $this->faktory->create('conversation', ['subject' => 'Second Conversation', 'updated_at' => Carbon::now()]);
        $participant_2 = $this->faktory->create('participant', ['user_id' => $userId, 'conversation_id' => $conversation2->id, 'last_read' => Carbon::yesterday()]);
        $conversation2->participants()->saveMany([$participant_2]);

        $this->assertTrue($conversation2->isUnread($userId));
    }

    /** @test */
    public function it_should_get_a_participant_from_userid()
    {
        $userId = 1;

        $participant = $this->faktory->create('participant', ['user_id' => $userId]);
        $conversation = $this->faktory->create('conversation');
        $conversation->participants()->saveMany([$participant]);

        $newParticipant = $conversation->getParticipantFromUser($userId);

        $this->assertInstanceOf(ConversationParticipant::class, $newParticipant);
    }

    /**
     * @test
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function it_should_throw_an_exception_when_participant_is_not_found()
    {
        $conversation = $this->faktory->create('conversation');

        $conversation->getParticipantFromUser(99);
    }

    /** @test */
    public function it_should_activate_all_deleted_participants()
    {
        $deleted_at = Carbon::yesterday();
        $conversation = $this->faktory->create('conversation');

        $user_1 = $this->faktory->build('participant', ['deleted_at' => $deleted_at]);
        $user_2 = $this->faktory->build('participant', ['user_id' => 2, 'deleted_at' => $deleted_at]);
        $user_3 = $this->faktory->build('participant', ['user_id' => 3, 'deleted_at' => $deleted_at]);

        $conversation->participants()->saveMany([$user_1, $user_2, $user_3]);

        $participants = $conversation->participants();
        $this->assertEquals(0, $participants->count());

        $conversation->activateAllParticipants();

        $participants = $conversation->participants();
        $this->assertEquals(3, $participants->count());
    }

    /** @test */
    public function it_should_generate_participant_select_string()
    {
        $method = self::getMethod('createSelectString');
        $conversation = new Conversation();
        $tableName = Models::table('users');

        $columns = ['name'];
        $select = $method->invokeArgs($conversation, [$columns]);
        $this->assertEquals('(' . Eloquent::getConnectionResolver()->getTablePrefix() . $tableName . '.name) as name', $select);

        $columns = ['name', 'email'];
        $select = $method->invokeArgs($conversation, [$columns]);
        $this->assertEquals('(' . Eloquent::getConnectionResolver()->getTablePrefix() . $tableName . ".name || ' ' || " . Eloquent::getConnectionResolver()->getTablePrefix() . $tableName . '.email) as name', $select);
    }

    /** @test */
    public function it_should_get_participants_string()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $participant_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $conversation->participants()->saveMany([$participant_1, $participant_2, $participant_3]);

        $string = $conversation->participantsString();
        $this->assertEquals('Chris Gmyr, Adam Wathan, Taylor Otwell', $string);

        $string = $conversation->participantsString(1);
        $this->assertEquals('Adam Wathan, Taylor Otwell', $string);

        $string = $conversation->participantsString(1, ['email']);
        $this->assertEquals('adam@test.com, taylor@test.com', $string);
    }

    /** @test */
    public function it_should_check_users_and_participants()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);

        $this->assertTrue($conversation->hasParticipant(1));
        $this->assertTrue($conversation->hasParticipant(2));
        $this->assertFalse($conversation->hasParticipant(3));
    }

    /** @test */
    public function it_should_remove_a_single_participant()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);

        $conversation->removeParticipant(2);

        $this->assertEquals(1, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_remove_a_group_of_participants_with_array()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);

        $conversation->removeParticipant([1, 2]);

        $this->assertEquals(0, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_remove_a_group_of_participants_with_arguments()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);

        $conversation->removeParticipant(1, 2);

        $this->assertEquals(0, $conversation->participants()->count());
    }

    /** @test */
    public function it_should_get_all_unread_messages_for_user()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $message_1 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 1',
        ]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);
        $conversation->messages()->saveMany([$message_1]);

        $conversation->markAsRead($participant_2->user_id);

        // Simulate delay after last read
        sleep(1);

        $message_2 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 2',
        ]);

        $conversation->messages()->saveMany([$message_2]);

        $this->assertEquals('Message 1', $conversation->userUnreadMessages(1)->first()->body);
        $this->assertCount(2, $conversation->userUnreadMessages(1));

        $this->assertEquals('Message 2', $conversation->userUnreadMessages(2)->first()->body);
        $this->assertCount(1, $conversation->userUnreadMessages(2));
    }

    /** @test */
    public function it_should_get_count_of_all_unread_messages_for_user()
    {
        $conversation = $this->faktory->create('conversation');

        $participant_1 = $this->faktory->build('participant');
        $participant_2 = $this->faktory->build('participant', ['user_id' => 2]);

        $message_1 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 1',
        ]);

        $conversation->participants()->saveMany([$participant_1, $participant_2]);
        $conversation->messages()->saveMany([$message_1]);

        $conversation->markAsRead($participant_2->user_id);

        // Simulate delay after last read
        sleep(1);

        $message_2 = $this->faktory->build('message', [
            'created_at' => Carbon::now(),
            'body' => 'Message 2',
        ]);

        $conversation->messages()->saveMany([$message_2]);

        $this->assertEquals(2, $conversation->userUnreadMessagesCount(1));

        $this->assertEquals(1, $conversation->userUnreadMessagesCount(2));
    }

    /** @test */
    public function it_should_return_empty_collection_when_user_not_participant()
    {
        $conversation = $this->faktory->create('conversation');

        $this->assertEquals(0, $conversation->userUnreadMessagesCount(1));
    }

    /** @test */
    public function it_should_get_the_creator_of_a_conversation()
    {
        $conversation = $this->faktory->create('conversation');

        $user_1 = $this->faktory->build('participant');
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $user_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $conversation->participants()->saveMany([$user_1, $user_2, $user_3]);

        $message_1 = $this->faktory->build('message', ['created_at' => Carbon::yesterday()]);
        $message_2 = $this->faktory->build('message', ['user_id' => 2]);
        $message_3 = $this->faktory->build('message', ['user_id' => 3]);

        $conversation->messages()->saveMany([$message_1, $message_2, $message_3]);

        $this->assertEquals('Chris Gmyr', $conversation->creator()->name);
    }

    /**
     * @test
     *
     * TODO: Need to get real creator of the conversation without messages in future versions.
     */
    public function it_should_get_the_null_creator_of_a_conversation_without_messages()
    {
        $conversation = $this->faktory->create('conversation');

        $user_1 = $this->faktory->build('participant');
        $user_2 = $this->faktory->build('participant', ['user_id' => 2]);
        $user_3 = $this->faktory->build('participant', ['user_id' => 3]);

        $conversation->participants()->saveMany([$user_1, $user_2, $user_3]);

        $this->assertFalse($conversation->creator()->exists);
        $this->assertNull($conversation->creator()->name);
    }
}
