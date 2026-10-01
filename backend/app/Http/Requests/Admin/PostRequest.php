<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('posts', 'name')->ignore($this->route('post'))],
            'location' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'between:1,'.Post::MAX_CAPACITY],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.between' => 'Satu pos dijaga 1 sampai '.Post::MAX_CAPACITY.' orang.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama pos',
            'location' => 'lokasi',
            'capacity' => 'jumlah penjaga',
            'is_active' => 'status aktif',
        ];
    }
}
