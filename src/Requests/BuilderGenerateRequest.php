<?php

namespace InfyOm\GeneratorBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Response;

class BuilderGenerateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'modelName'                => 'required',
            'commandType'              => 'required',
            'tableName'                => 'nullable',
            'fields'                   => 'required',
            'migrate'                  => 'required|boolean',
            'options'                  => 'required|array',
            'options.softDelete'       => 'required|boolean',
            'options.save'             => 'required|boolean',
            'options.prefix'           => 'nullable',
            'options.paginate'         => 'required|integer',
            'options.forceMigrate'     => 'required|boolean',
            'addOns'                   => 'required|array',
            'addOns.swagger'           => 'required|boolean',
            'addOns.tests'             => 'required|boolean',
            'addOns.datatables'        => 'required|boolean',
            'fields'                   => 'required|array',
            'fields.*.name'            => 'required',
            'fields.*.foreignTable'    => 'required_if:fields.*.isForeign,true',
            'fields.*.dbType'          => 'required',
            'fields.*.validations'     => 'nullable',
            'fields.*.htmlType'        => 'required',
            'fields.*.primary'         => 'required|boolean',
            'fields.*.isForeign'       => 'required|boolean',
            'fields.*.searchable'      => 'required|boolean',
            'fields.*.fillable'        => 'required|boolean',
            'fields.*.inForm'          => 'required|boolean',
            'fields.*.inIndex'         => 'required|boolean',
            'relations'                => 'nullable|array',
            'relations.*.relationType' => 'required',
            'relations.*.foreignTable' => 'required_if:relations.*.relationType,mtm',
            'relations.*.foreignModel' => 'required',
            'relations.*.foreignKey'   => 'nullable',
            'relations.*.localKey'     => 'nullable'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'fields.*.foreignTable.required_if' => 'Foreign table required',
            'relations.*.foreignTable.required_if' => 'Custom table required'
        ];
    }

    /**
     * Get the proper failed validation response for the request.
     *
     * @param array $errors
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        $messages = implode(' ', array_flatten($errors));

        return Response::json($messages, 400);
    }
}
