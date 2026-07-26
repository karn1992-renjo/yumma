<?php

namespace App\Http\Requests;

use App\Rules\UniqueUserContactForRole;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', UniqueUserContactForRole::email($this->user()->roles()->value('name'), $this->user()->id)],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone($this->user()->roles()->value('name'), $this->user()->id)],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.unique' => 'This email is already taken',
            'phone.required' => 'Phone number is required',
            'phone.unique' => 'This phone number is already registered',
        ];
    }
}
