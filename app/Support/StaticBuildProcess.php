<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

final class StaticBuildProcess
{
    public static function runTarget(string $target): ProcessResult
    {
        $target = self::normalizeTarget($target);

        return self::run(['artisan', 'site:build', $target]);
    }

    public static function runPost(Post $post): ProcessResult
    {
        $siteCode = self::resolveSiteCode($post);

        if ($siteCode === null) {
            throw new RuntimeException('No se pudo resolver el sitio asociado al post.');
        }

        return self::run(['artisan', 'site:build', $siteCode, '--post=' . $post->getKey()]);
    }

    public static function summary(ProcessResult $result, int $limit = 900): string
    {
        $output = trim($result->output() . PHP_EOL . $result->errorOutput());
        $output = preg_replace('/\s+/', ' ', $output) ?: 'Sin salida del proceso.';

        return Str::limit($output, $limit);
    }

    protected static function run(array $arguments): ProcessResult
    {
        return Process::path(base_path())
            ->timeout(600)
            ->run([
                self::phpBinary(),
                ...$arguments,
            ]);
    }

    protected static function phpBinary(): string
    {
        $configured = config('static_cms.build.php_binary');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (is_file('/.dockerenv')) {
            return PHP_BINARY;
        }

        return is_executable(base_path('php')) ? './php' : PHP_BINARY;
    }

    protected static function normalizeTarget(string $target): string
    {
        $target = trim($target);

        return in_array($target, ['all', 'posts', 'logo'], true) ? $target : 'all';
    }

    protected static function resolveSiteCode(Post $post): ?string
    {
        if ($post->relationLoaded('site') && $post->site) {
            return $post->site->short_name;
        }

        if ($post->site_id) {
            $siteCode = Site::query()
                ->where('short_name', $post->site_id)
                ->orWhere('id', $post->site_id)
                ->value('short_name');

            return $siteCode ?: (string) $post->site_id;
        }

        return Site::query()->orderBy('id')->value('short_name');
    }
}
