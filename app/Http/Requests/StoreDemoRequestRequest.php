<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDemoRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'inn' => ['required', 'string', 'regex:/^(\d{10}|\d{12})$/'],
            'kpp' => ['nullable', 'string', 'regex:/^\d{9}$/'],
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'items_list' => 'required|string|min:10',
            'terms_accepted' => 'required|accepted',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Поле ФИО обязательно для заполнения',
            'organization.required' => 'Поле Организация обязательно для заполнения',
            'inn.required' => 'Поле ИНН обязательно для заполнения',
            'inn.regex' => 'ИНН должен содержать 10 или 12 цифр',
            'kpp.regex' => 'КПП должен содержать 9 цифр',
            'email.required' => 'Поле Email обязательно для заполнения',
            'email.email' => 'Введите корректный email адрес',
            'phone.required' => 'Поле Телефон обязательно для заполнения',
            'items_list.required' => 'Необходимо указать список товаров',
            'items_list.min' => 'Список товаров должен содержать минимум 10 символов',
            'terms_accepted.required' => 'Необходимо согласиться с условиями использования',
            'terms_accepted.accepted' => 'Необходимо принять условия и правила сервиса',
        ];
    }
}
