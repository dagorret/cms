<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePostPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable'],
            'type' => ['nullable', Rule::in([Post::TYPE_POST, Post::TYPE_PAGE])],
            'site_id' => ['nullable'],
            'category_id' => ['nullable', 'integer'],
            'published_at' => ['nullable'],
            'keywords' => ['nullable', 'string'],
            'has_math' => ['nullable', 'boolean'],
        ];
    }
}
