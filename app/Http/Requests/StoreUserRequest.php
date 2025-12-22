<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['super_admin', 'admin', 'viewer'])],
            'is_active' => 'boolean',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $userId = $this->route('id');
            $rules['email'] = "required|email|unique:users,email,{$userId}";
            $rules['password'] = 'nullable|string|min:8';
        }

        return $rules;
    }
}
