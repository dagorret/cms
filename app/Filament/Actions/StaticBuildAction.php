<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Post;
use App\Models\Site;
use App\Support\StaticBuildLauncher;
use App\Support\StaticBuildProcess;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class StaticBuildAction
{
    public static function make(string $name = 'launchStaticBuild'): Action
    {
        return Action::make($name)
            ->label('Lanzar Orquestador NASA')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->modalHeading('Lanzar Orquestador NASA')
            ->modalDescription('Selecciona el alcance y el modo. Las cuatro entradas del panel ejecutan este mismo orquestador.')
            ->modalSubmitActionLabel('Iniciar compilación')
            ->fillForm(fn (?Model $record): array => self::defaultsFor($record))
            ->schema([
                Select::make('site_id')
                    ->label('Sitio')
                    ->options(fn (): array => Site::query()
                        ->orderBy('long_name')
                        ->pluck('long_name', 'id')
                        ->mapWithKeys(fn (mixed $name, mixed $id): array => [(string) $id => (string) $name])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('post_id', null))
                    ->disabled(fn (?Model $record): bool => $record instanceof Post || $record instanceof Site)
                    ->dehydrated()
                    ->required(),
                Radio::make('scope')
                    ->label('Alcance')
                    ->options([
                        StaticBuildLauncher::SCOPE_POST => 'Solo este post',
                        StaticBuildLauncher::SCOPE_SITE => 'Todo el sitio',
                    ])
                    ->descriptions([
                        StaticBuildLauncher::SCOPE_POST => 'Renderiza el HTML individual seleccionado y actualiza las estructuras globales.',
                        StaticBuildLauncher::SCOPE_SITE => 'Procesa el sitio completo respetando la incrementalidad del comando.',
                    ])
                    ->live()
                    ->required(),
                Select::make('post_id')
                    ->label('Post')
                    ->options(function (Get $get): array {
                        $siteId = $get->integer('site_id', isNullable: true);

                        if ($siteId === null) {
                            return [];
                        }

                        $site = Site::query()->find($siteId);

                        return $site
                            ? app(StaticBuildLauncher::class)->postOptions($site)
                            : [];
                    })
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get, ?Model $record): bool => $record instanceof Post || blank($get('site_id')))
                    ->visible(fn (Get $get): bool => $get('scope') === StaticBuildLauncher::SCOPE_POST)
                    ->required(fn (Get $get): bool => $get('scope') === StaticBuildLauncher::SCOPE_POST)
                    ->dehydrated(),
                Radio::make('mode')
                    ->label('Modo')
                    ->options([
                        StaticBuildLauncher::MODE_NORMAL => 'Normal',
                        StaticBuildLauncher::MODE_FORCE => 'Forzar',
                    ])
                    ->descriptions([
                        StaticBuildLauncher::MODE_NORMAL => 'Usa la incrementalidad normal.',
                        StaticBuildLauncher::MODE_FORCE => 'Envía --force. Para un solo post, el comando actual ya recompila siempre ese HTML.',
                    ])
                    ->required(),
                Placeholder::make('summary_site')
                    ->label('Sitio')
                    ->content(fn (Get $get): string => self::siteSummary($get)),
                Placeholder::make('summary_post_id')
                    ->label('ID del post')
                    ->content(fn (Get $get): string => self::postSummary($get, 'id')),
                Placeholder::make('summary_post_title')
                    ->label('Título del post')
                    ->content(fn (Get $get): string => self::postSummary($get, 'title')),
                Placeholder::make('summary_scope')
                    ->label('Alcance')
                    ->content(fn (Get $get): string => match ($get('scope')) {
                        StaticBuildLauncher::SCOPE_POST => 'Solo este post',
                        StaticBuildLauncher::SCOPE_SITE => 'Todo el sitio',
                        default => '—',
                    }),
                Placeholder::make('summary_mode')
                    ->label('Modo')
                    ->content(fn (Get $get): string => match ($get('mode')) {
                        StaticBuildLauncher::MODE_NORMAL => 'Normal',
                        StaticBuildLauncher::MODE_FORCE => 'Forzar',
                        default => '—',
                    }),
            ])
            ->action(function (array $data): void {
                try {
                    $site = Site::query()->find($data['site_id'] ?? null);

                    if (! $site) {
                        throw new \RuntimeException('El sitio seleccionado no existe.');
                    }

                    $result = app(StaticBuildLauncher::class)->launch(
                        site: $site,
                        scope: (string) ($data['scope'] ?? ''),
                        mode: (string) ($data['mode'] ?? ''),
                        postId: filled($data['post_id'] ?? null) ? (int) $data['post_id'] : null,
                    );

                    Notification::make()
                        ->title($result->successful() ? 'Compilación finalizada' : 'Falló la compilación')
                        ->body(StaticBuildProcess::summary($result))
                        ->{$result->successful() ? 'success' : 'danger'}()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Falló la compilación')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function defaultsFor(?Model $record): array
    {
        $site = match (true) {
            $record instanceof Site => $record,
            $record instanceof Post => app(StaticBuildLauncher::class)->resolveSiteForPost($record),
            default => null,
        };

        return [
            'site_id' => $site?->getKey(),
            'post_id' => $record instanceof Post ? $record->getKey() : null,
            'scope' => $record instanceof Post
                ? StaticBuildLauncher::SCOPE_POST
                : StaticBuildLauncher::SCOPE_SITE,
            'mode' => StaticBuildLauncher::MODE_NORMAL,
        ];
    }

    private static function siteSummary(Get $get): string
    {
        $site = Site::query()->find($get->integer('site_id', isNullable: true));

        return $site ? "{$site->long_name} ({$site->short_name})" : '—';
    }

    private static function postSummary(Get $get, string $field): string
    {
        if ($get('scope') !== StaticBuildLauncher::SCOPE_POST) {
            return '—';
        }

        $postId = $get->integer('post_id', isNullable: true);

        if ($postId === null) {
            return '—';
        }

        $post = Post::query()->find($postId);

        return $field === 'id'
            ? (string) $postId
            : (string) ($post?->title ?? '—');
    }
}
