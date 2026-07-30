@extends('layouts.app')

@section('title', 'Edit Kitchen')

@section('content')
    <div class="card">
        <h1 style="margin:0 0 12px;font-size:1.4rem;">Edit Kitchen</h1>
        <form method="POST" action="{{ route('kitchens.update', $kitchen) }}">
            @csrf
            @method('PUT')
            @include('kitchens._form', ['kitchen' => $kitchen, 'mapConfig' => $mapConfig])
        </form>
    </div>
@endsection
