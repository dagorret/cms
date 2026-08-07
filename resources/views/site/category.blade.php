@extends('site.layouts.app')

@section('title', $category->name)
@section('description', trim((string) $category->description) !== '' ? $category->description : "Publicaciones de la categoría {$categoryPath} en " . ($site->long_name ?? 'el sitio') . '.')

@section('content')
    @include('site.partials.listing', [
        'listingTitle' => $categoryPath,
        'listingKind' => 'category',
        'listingDescription' => $category->description,
    ])
@endsection
