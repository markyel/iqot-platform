<?php

namespace App\Http\Requests;

use App\Models\RequestItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequestRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            // Основная информация о заявке
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            
            // Контактная информация (обязательна для отправки)
            'company_name' => 'required|string|max:255',
            'company_address' => 'required|string|max:500',
            'inn' => 'required|digits_between:10,12',
            'kpp' => 'nullable|digits:9',
            'contact_person' => 'required|string|max:255',
            'contact_phone' => 'required|string|max:20',
            
            // Позиции заявки
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:500',
            'items.*.equipment_type' => 'required|in:' . implode(',', array_keys(RequestItem::equipmentTypes())),
            'items.*.equipment_brand' => 'required|string|max:255',
            'items.*.manufacturer_article' => 'required|string|max:255',
            'items.*.brand' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.description' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Название организации обязательно для заполнения',
            'company_address.required' => 'Адрес организации обязателен для заполнения',
            'inn.required' => 'ИНН обязателен для заполнения',
            'inn.digits_between' => 'ИНН должен содержать 10 или 12 цифр',
            'kpp.digits' => 'КПП должен содержать 9 цифр',
            'contact_person.required' => 'ФИО ответственного сотрудника обязательно',
            'contact_phone.required' => 'Телефон обязателен для заполнения',
            
            'items.required' => 'Необходимо добавить хотя бы одну позицию',
            'items.min' => 'Необходимо добавить хотя бы одну позицию',
            'items.*.name.required' => 'Название позиции обязательно',
            'items.*.equipment_type.required' => 'Тип оборудования обязателен',
            'items.*.equipment_type.in' => 'Недопустимый тип оборудования',
            'items.*.equipment_brand.required' => 'Марка оборудования обязательна',
            'items.*.manufacturer_article.required' => 'Артикул производителя обязателен',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'название организации',
            'company_address' => 'адрес',
            'inn' => 'ИНН',
            'kpp' => 'КПП',
            'contact_person' => 'ФИО ответственного',
            'contact_phone' => 'телефон',
            'items.*.name' => 'название',
            'items.*.equipment_type' => 'тип оборудования',
            'items.*.equipment_brand' => 'марка оборудования',
            'items.*.manufacturer_article' => 'артикул производителя',
        ];
    }
}
