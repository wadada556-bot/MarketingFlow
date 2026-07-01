<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:today'],
            'period'    => ['nullable', 'string', 'in:today,kemarin,7d,30d,custom'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'date_to.after_or_equal'    => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
            'date_to.before_or_equal'   => 'Tanggal akhir tidak boleh lebih dari hari ini.',
            'period.in'                 => 'Periode tidak valid.',
        ];
    }
}
