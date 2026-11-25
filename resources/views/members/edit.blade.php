@extends('layouts.app')
@section('content')<h1 class="mb-6 text-3xl font-bold">Edit Member Details</h1>@include('members.form', ['member' => $member, 'button' => 'Save Changes'])@endsection
