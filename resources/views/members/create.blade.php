@extends('layouts.app')
@section('content')<h1 class="mb-6 text-3xl font-bold">Add New Member</h1>@include('members.form', ['member' => null, 'button' => 'Add Member'])@endsection
