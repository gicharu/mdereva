<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\QuestionsRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class QuestionsCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class QuestionsCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel('App\Models\Questions');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/questions');
        $this->crud->setEntityNameStrings('questions', 'questions');
    }

    protected function setupListOperation()
    {
        $this->crud->addColumn([
            'name' => 'question', // The db column name
            'type' => 'Text'
        ]);
        $this->crud->addColumn([
            'name' => 'media', // The db column name
            'type' => 'image'
        ]);
         $this->crud->addColumn([
            'name' => 'score', // The db column name
            'type' => 'Text'
        ]);
         $this->crud->addColumn([
            'name' => 'duration', // The db column name
            'type' => 'Text'
        ]);

    }

    protected function setupShowOperation()
    {
        $this->crud->addColumn([
            'name' => 'questions',
            'label' => '',
            'type' => 'table',
            'columns' => [
                'name'  => 'Name',
                'media'  => 'Description',
                'score' => 'Price',
                'duration' => 'Duration',
            ]
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->setupAddUpdateOprations();
    }

    protected function setupUpdateOperation()
    {
        $this->setupAddUpdateOprations();


    }

    protected function setupAddUpdateOprations()
    {
        $this->crud->setValidation(QuestionsRequest::class);

        $this->crud->addField([
            'name' => 'question',
            'type' => 'textarea'
        ]);
        $this->crud->addField([
            'name' => 'media',
            'type' => 'upload',
            'upload' => 'true'
        ]);
        $this->crud->addField([
            'name' => 'media_type',
            'type' => 'select_from_array',
            'options' => ['Image', 'Video'],
            'default' => 0
        ]);
        $this->crud->addField([
            'name' => 'score',
            'type' => 'number',
        ]);
        $this->crud->addField([
            'name' => 'duration',
            'type' => 'number',
            'label' => 'Duration (seconds)'
        ]);
    }
}
