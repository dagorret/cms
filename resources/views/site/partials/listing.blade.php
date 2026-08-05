<section data-listing data-listing-kind="{{ $listingKind }}" class="mt-0 border-t-[3px] border-[#171717] pt-[18px] dark:border-[#e8e1d5]">
    <header class="mb-8">
        <p class="font-sans text-[.76rem] font-bold uppercase tracking-[.14em] text-[#0f4c5c] dark:text-[#8fc3cf]">
            {{ $listingKind === 'category' ? 'Categoría' : 'Últimos artículos' }}
        </p>
        @if($listingKind === 'category')
            <h1 class="my-4 font-serif text-[clamp(2rem,4vw,3.2rem)] font-bold text-[#171717] dark:text-[#f5f0e7]">{{ $listingTitle }}</h1>
            @if(filled($listingDescription ?? null))
                <p class="text-[#333333] dark:text-[#cbc4b8]">{{ $listingDescription }}</p>
            @endif
        @endif
    </header>

    <div class="posts-container">
        @include('site.partials.listing-posts', ['posts' => $posts])
    </div>

    @include('site.partials.pagination')
</section>
