<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('section_id') && ! is_numeric($this->section_id) && ! empty($this->section_id)) {
            $section = \App\Models\Section::where('slug', $this->section_id)
                ->orWhere('name', $this->section_id)
                ->first();

            if ($section) {
                $this->merge(['section_id' => $section->id]);
            } else {
                $this->merge(['section_id' => null]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => 'sometimes|nullable|exists:sections,id',
            'title' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($this->route('article'))],
            'excerpt' => 'nullable|string',
            'content' => 'sometimes|string',
            'author_name' => 'nullable|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1048576', // 1GB
            'gregorian_date' => 'nullable|string',
            'hijri_date' => 'nullable|string',
            'references' => 'nullable|string',
            'keywords' => 'nullable|string',
            'status' => ['sometimes', Rule::in([
                env('ARTICLE_STATUS_DRAFT', 'draft'),
                env('ARTICLE_STATUS_PUBLISHED', 'published'),
                env('ARTICLE_STATUS_ARCHIVED', 'archived'),
            ])],
            'published_at' => 'nullable|date',
        ];
    }
}
