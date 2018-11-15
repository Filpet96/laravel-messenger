@extends('layouts.master')

@section('content')
    @include('messenger.partials.flash')

    @each('messenger.partials.conversation', $conversations, 'conversation', 'messenger.partials.no-conversations')
@stop
