<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    protected $errorBag = 'createStore';
    
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower($this->name)
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100', 'unique:stores,name']
        ];
    }
}
