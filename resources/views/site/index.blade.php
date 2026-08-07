@extends('site.layouts.app')

@if(($currentPage ?? 1) === 1)
@section('jsonld')
@php
    $websiteLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $site->long_name ?? $site->short_name,
        'description' => trim((string) ($site->meta_description ?? $site->slogan ?? '')),
        'url' => $site->publicUrl(),
    ], fn ($value) => $value !== null && $value !== '');
    $personLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => 'Carlos Dagorret',
        'url' => $site->publicUrl(),
        'sameAs' => [
            'https://github.com/dagorret',
            'https://www.linkedin.com/in/carlos-dagorret-59b4a49',
            'https://x.com/Dagorret_',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($websiteLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script type="application/ld+json">{!! json_encode($personLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endsection
@endif

@section('content')
    @include('site.partials.listing', [
        'listingTitle' => 'Últimos artículos',
        'listingKind' => 'home',
    ])
@endsection
