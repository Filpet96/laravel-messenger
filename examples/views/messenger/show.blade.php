@extends('layouts.master')

@section('content')
    <div class="col-md-6">
        <h1>{{ $conversation->subject }}</h1>
        @each('messenger.partials.messages', $conversation->messages, 'message')

        @include('messenger.partials.form-message')
    </div>
@stop
