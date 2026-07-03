<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_sku'        => ['required', 'string', 'exists:jubelio_inventory,parent_sku'],
            'category_id'       => ['nullable', 'integer', 'exists:categories,id'],
            'status'            => ['required', 'string', 'in:active,stopped,completed'],
            'stores'            => ['required', 'array', 'min:1', 'max:7'],
            'stores.*'          => ['required', 'integer', 'exists:stores,id'],
            'testing_status'    => ['nullable', 'string', 'in:testing,success,fail'],
            'testing_dates'     => ['nullable', 'string'],
        ];
    }
}