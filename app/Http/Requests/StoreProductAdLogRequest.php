<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductAdLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ad_id' => ['required', 'integer', 'exists:product_ads,id'],
            'action_date'   => ['required', 'string'],
            'description'   => ['required', 'string', 'max:255'],
        ];
    }
}
