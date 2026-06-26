<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectLeavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'rejection_comment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
