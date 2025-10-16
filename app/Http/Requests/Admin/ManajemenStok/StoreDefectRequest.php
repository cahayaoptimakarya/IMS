<?php

namespace App\Http\Requests\Admin\ManajemenStok;

use Illuminate\Foundation\Http\FormRequest;

class StoreDefectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Assuming any authenticated user with access can create a defect record
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0.01',
            'koli' => 'nullable|numeric|min:0',
            'notes' => 'required|string|max:1000',
        ];
    }
}
