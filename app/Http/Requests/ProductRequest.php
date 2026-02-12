<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products')->ignore($this->product),
            ],
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'category' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive',
            'image' => 'sometimes|image|max:2048',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['sku'] = [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('products')->ignore($this->route('id')),
            ];
            $rules['name'] = 'sometimes|required|string|min:3|max:255';
            $rules['price'] = 'sometimes|required|numeric|min:0.01';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'O SKU é obrigatório',
            'sku.unique' => 'Este SKU já está em uso',
            'name.required' => 'O nome é obrigatório',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres',
            'price.min' => 'O preço deve ser maior que zero',
        ];
    }
}