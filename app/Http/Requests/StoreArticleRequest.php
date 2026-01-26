<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
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

        $section = $this->route('section');

        if ($section) {
            $this->merge([
                'section_id' => $section instanceof \App\Models\Section ? $section->id : $section,
            ]);
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
            'section_id' => 'nullable|exists:sections,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:articles,slug|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'author_name' => 'nullable|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1048576', // 1GB
            'gregorian_date' => 'nullable|string',
            'hijri_date' => 'nullable|string',
            'references' => 'nullable|string',
            'keywords' => 'nullable|string',
            'status' => ['required', Rule::in([
                env('ARTICLE_STATUS_DRAFT', 'draft'),
                env('ARTICLE_STATUS_PUBLISHED', 'published'),
                env('ARTICLE_STATUS_ARCHIVED', 'archived'),
            ])],
            'published_at' => 'nullable|date',
        ];
    }
}
