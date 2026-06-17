<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteProductAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:product_ads,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih setidaknya satu data yang ingin dihapus.',
        ];
    }
}