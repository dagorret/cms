@extends('site.layouts.app')

@section('title', $category->name)

@section('content')
    @include('site.partials.listing', [
        'listingTitle' => $categoryPath,
        'listingKind' => 'category',
        'listingDescription' => $category->description,
    ])
@endsection
