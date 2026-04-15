<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('section_id') && ! is_numeric($this->section_id) && ! empty($this->section_id)) {
            $section = Section::resolveReference($this->section_id);

            if ($section) {
                $this->merge(['section_id' => $section->id]);
            } else {
                $this->merge(['section_id' => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'section_id' => 'nullable|exists:sections,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:upload,link',
            'file' => 'nullable|file',
            'url' => 'nullable|url',
            'thumbnail' => 'nullable|image',
            'keywords' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:5',
        ];
    }
}
