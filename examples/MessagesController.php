<?php

namespace App\Http\Controllers;

use App\User;
use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\ConversationParticipant;
use Cmgmyr\Messenger\Models\Conversation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;

class MessagesController extends Controller
{
    /**
     * Show all of the message conversations to the user.
     *
     * @return mixed
     */
    public function index()
    {
        // All conversations, ignore deleted/archived participants
        $conversations = Conversation::getAllLatest()->get();

        // All conversations that user is participating in
        // $conversations = Conversation::forUser(Auth::id())->latest('updated_at')->get();

        // All conversations that user is participating in, with new messages
        // $conversations = Conversation::forUserWithNewMessages(Auth::id())->latest('updated_at')->get();

        return view('messenger.index', compact('conversations'));
    }

    /**
     * Shows a message conversation.
     *
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        try {
            $conversation = Conversation::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The conversation with ID: ' . $id . ' was not found.');

            return redirect()->route('messages');
        }

        // show current user in list if not a current participant
        // $users = User::whereNotIn('id', $conversation->participantsUserIds())->get();

        // don't show the current user in list
        $userId = Auth::id();
        $users = User::whereNotIn('id', $conversation->participantsUserIds($userId))->get();

        $conversation->markAsRead($userId);

        return view('messenger.show', compact('conversation', 'users'));
    }

    /**
     * Creates a new message conversation.
     *
     * @return mixed
     */
    public function create()
    {
        $users = User::where('id', '!=', Auth::id())->get();

        return view('messenger.create', compact('users'));
    }

    /**
     * Stores a new message conversation.
     *
     * @return mixed
     */
    public function store()
    {
        $input = Input::all();

        $conversation = Conversation::create([
            'subject' => $input['subject'],
        ]);

        // Message
        Message::create([
            'thread_id' => $conversation->id,
            'user_id' => Auth::id(),
            'body' => $input['message'],
        ]);

        // Sender
        ConversationParticipant::create([
            'thread_id' => $conversation->id,
            'user_id' => Auth::id(),
            'last_read' => new Carbon,
        ]);

        // Recipients
        if (Input::has('recipients')) {
            $conversation->addParticipant($input['recipients']);
        }

        return redirect()->route('messages');
    }

    /**
     * Adds a new message to a current conversation.
     *
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        try {
            $conversation = Conversation::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The conversation with ID: ' . $id . ' was not found.');

            return redirect()->route('messages');
        }

        $conversation->activateAllParticipants();

        // Message
        Message::create([
            'thread_id' => $conversation->id,
            'user_id' => Auth::id(),
            'body' => Input::get('message'),
        ]);

        // Add replier as a participant
        $participant = ConversationParticipant::firstOrCreate([
            'thread_id' => $conversation->id,
            'user_id' => Auth::id(),
        ]);
        $participant->last_read = new Carbon;
        $participant->save();

        // Recipients
        if (Input::has('recipients')) {
            $conversation->addParticipant(Input::get('recipients'));
        }

        return redirect()->route('messages.show', $id);
    }
}
