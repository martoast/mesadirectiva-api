<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tables' => 'nullable|array',
            'tables.*' => 'integer|exists:tables,id',
            'seats' => 'nullable|array',
            'seats.*' => 'integer|exists:seats,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tables = $this->input('tables', []);
            $seats = $this->input('seats', []);

            if (empty($tables) && empty($seats)) {
                $validator->errors()->add('tables', 'You must select at least one table or seat.');
            }
        });
    }
}
