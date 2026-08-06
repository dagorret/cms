<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostPreviewRequest;
use App\Services\PostPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PostPreviewController extends Controller
{
    public function store(StorePostPreviewRequest $request, PostPreviewService $previews): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $previews->generate($userId, $request->validated());

        return response()->json([
            'url' => route('filament.dash.post-preview.show', [
                't' => now()->getTimestampMs(),
            ]),
        ]);
    }

    public function show(Request $request, PostPreviewService $previews): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $html = $previews->read($userId);

        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
