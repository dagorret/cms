@extends('site.layouts.app')

@section('title', $site->long_name ?? 'Notas')

@section('content')
    @include('site.partials.listing', [
        'listingTitle' => 'Últimos artículos',
        'listingKind' => 'home',
    ])
@endsection
