@extends('layouts.app')

@section('title', 'New Kitchen')

@section('content')
    <div class="card">
        <h1 style="margin:0 0 12px;font-size:1.4rem;">Add Kitchen</h1>
        <form method="POST" action="{{ route('kitchens.store') }}">
            @csrf
            @include('kitchens._form', ['kitchen' => $kitchen, 'mapConfig' => $mapConfig])
        </form>
    </div>
@endsection
