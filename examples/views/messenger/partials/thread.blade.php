<?php $class = $conversation->isUnread(Auth::id()) ? 'alert-info' : ''; ?>

<div class="media alert {{ $class }}">
    <h4 class="media-heading">
        <a href="{{ route('messages.show', $conversation->id) }}">{{ $conversation->subject }}</a>
        ({{ $conversation->userUnreadMessagesCount(Auth::id()) }} unread)</h4>
    <p>
        {{ $conversation->latestMessage->body }}
    </p>
    <p>
        <small><strong>Creator:</strong> {{ $conversation->creator()->name }}</small>
    </p>
    <p>
        <small><strong>Participants:</strong> {{ $conversation->participantsString(Auth::id()) }}</small>
    </p>
</div>