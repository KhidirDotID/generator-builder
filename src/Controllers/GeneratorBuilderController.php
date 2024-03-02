<?php

namespace InfyOm\GeneratorBuilder\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Artisan;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use InfyOm\GeneratorBuilder\Requests\BuilderGenerateRequest;
use Spatie\Permission\Models\Permission;

class GeneratorBuilderController extends Controller
{
    public function builder(): View
    {
        return view(config('generator_builder.views.builder'));
    }

    public function fieldTemplate(): View
    {
        return view(config('generator_builder.views.field-template'));
    }

    public function relationFieldTemplate(): View
    {
        return view(config('generator_builder.views.relation-field-template'));
    }

    public function generate(BuilderGenerateRequest $request): JsonResponse
    {
        $data = $request->all();

        // Validate fields
        if (isset($data['fields'])) {
            $this->validateFields($data['fields']);

            // prepare foreign key
            $isAnyForeignKey = collect($data['fields'])->filter(function ($field) {
                return $field['isForeign'] == true;
            });
            if (count($isAnyForeignKey)) {
                $data['fields'] = $this->prepareForeignKeyData($data['fields']);
            }
        }

        // prepare relationship
        if (isset($data['fields']) && !empty($data['relations'])) {
            $data = $this->prepareRelationshipData($data);
        }

        if (class_exists(Module::class)) {
            $module = Module::create([
                'name'         => $data['modelName'],
                'display_name' => ltrim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $data['modelName'])),
                'route'        => Str::camel(Str::plural($data['modelName'])),
                'icon'         => 'far fa-circle',
                'order'        => Module::where('type', 'web')->max('order') + 1,
                'type'         => 'web'
            ]);

            if (class_exists(Permission::class)) {
                $permissions = [
                    'manage-' . $module->name,
                    'create-' . $module->name,
                    'show-' . $module->name,
                    'edit-' . $module->name,
                    'delete-' . $module->name
                ];

                foreach ($permissions as $permission) {
                    Permission::create([
                        'module_id'  => $module->id,
                        'name'       => $permission,
                        'guard_name' => 'web'
                    ]);
                }
            }
        }

        $res = Artisan::call($data['commandType'], [
            'model'         => $data['modelName'],
            '--jsonFromGUI' => json_encode($data),
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Files created successfully'
        ]);
    }

    public function rollback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modelName'   => 'required',
            'commandType' => 'required',
            'prefix'      => 'nullable'
        ]);

        $input = [
            'model' => $data['modelName'],
            'type'  => $data['commandType'],
        ];

        if (!empty($data['prefix'])) {
            $input['--prefix'] = $data['prefix'];
        }

        Artisan::call('infyom:rollback', $input);

        if (class_exists(Module::class)) {
            $module = Module::where('name', $data['modelName'])->first();

            if (class_exists(Permission::class)) {
                Permission::where('module_id', $module->id)->delete();
            }

            $module->delete();
        }

        return Response::json([
            'success' => true,
            'message' => 'Files rollback successfully'
        ]);
    }

    public function generateFromFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modelName' => 'required',
            'schemaFile' => 'required|file|mimes:json',
            'commandType' => 'required'
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('schemaFile');
        $filePath = $file->getRealPath();

        Artisan::call($data['commandType'], [
            'model'        => $data['modelName'],
            '--fieldsFile' => $filePath,
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Files created successfully'
        ]);
    }

    private function validateFields($fields)
    {
        $fieldsGroupBy = collect($fields)->groupBy(function ($item) {
            return strtolower($item['name']);
        });

        $duplicateFields = $fieldsGroupBy->filter(function (Collection $groups) {
            return $groups->count() > 1;
        });

        if (count($duplicateFields)) {
            throw new \Exception('Duplicate fields are not allowed');
        }

        return true;
    }

    private function prepareRelationshipData($inputData)
    {
        foreach ($inputData['relations'] as $inputRelation) {
            $relationType = $inputRelation['relationType'];
            $relation = $relationType;

            if (isset($inputRelation['foreignModel'])) {
                $relation .= ',' . $inputRelation['foreignModel'];
            }

            if ($relationType == 'mtm') {
                if (isset($inputRelation['foreignTable'])) {
                    $relation .= ',' . $inputRelation['foreignTable'];
                } else {
                    $relation .= ',';
                }
            }

            if (isset($inputRelation['foreignKey'])) {
                $relation .= ',' . $inputRelation['foreignKey'];
            }

            if (isset($inputRelation['localKey'])) {
                $relation .= ',' . $inputRelation['localKey'];
            }

            $inputData['fields'][] = [
                'type'     => 'relation',
                'relation' => $relation,
            ];
        }

        unset($inputData['relations']);

        return $inputData;
    }

    private function prepareForeignKeyData($fields)
    {
        $updatedFields = [];

        foreach ($fields as $field) {
            if ($field['isForeign'] == true) {
                $inputs = explode(',', $field['foreignTable']);
                $foreignTableName = array_shift($inputs);

                // prepare dbType
                $dbType = $field['dbType'];
                $dbType .= ':unsigned:foreign';
                $dbType .= ',' . $foreignTableName;

                if (!empty($inputs)) {
                    $dbType .= ',' . $inputs['0'];
                } else {
                    $dbType .= ',id';
                }

                $field['dbType'] = $dbType;
            }

            $updatedFields[] = $field;
        }

        return $updatedFields;
    }

    // public function availableSchema()
    // {
    //     $schemaFolder = config('laravel_generator.path.schema_files', base_path('resources/model_schemas/'));

    //     if (!File::exists($schemaFolder)) {
    //         return [];
    //     }

    //     $files = File::allFiles($schemaFolder);

    //     $modelNames = [];

    //     foreach ($files as $file) {
    //         if (File::extension($file) == "json") {
    //             $modelNames[] = File::name($file);
    //         }
    //     }

    //     return Response::json($modelNames);
    // }

    // public function retrieveSchema($schema)
    // {
    //     $schemaFolder = config('laravel_generator.path.schema_files', base_path('resources/model_schemas/'));

    //     $filePath = $schemaFolder . $schema . ".json";

    //     if (!File::exists($filePath)) {
    //         return Response::json('not found', 402);
    //     }

    //     return Response::json(json_decode(File::get($filePath)));
    // }
}
