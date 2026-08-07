<x-filament-widgets::widget class="fi-cms-info-widget">
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="fi-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    CMS Faro
                </h2>

                <p class="fi-header-subheading text-sm text-gray-500 dark:text-gray-400">
                    Laravel {{ \Composer\InstalledVersions::getPrettyVersion('laravel/framework') }} &middot;
                    Filament {{ \Composer\InstalledVersions::getPrettyVersion('filament/filament') }}
                </p>
            </div>

            <x-filament::link
                color="gray"
                href="https://github.com/dagorret/cms"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedCodeBracket"
                rel="noopener noreferrer"
                target="_blank"
            >
                GitHub
            </x-filament::link>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
