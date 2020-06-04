<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AnswersRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class AnswersCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class AnswersCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel('App\Models\Answers');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/answers');
        $this->crud->setEntityNameStrings('answers', 'answers');
    }

    protected function setupListOperation()
    {
        $this->crud->addColumn(
            [
                'label' => "Question", // Table column heading
                'type' => "select",
                'name' => 'questionId', // the column that contains the ID of that connected entity;
                'entity' => 'question', // the method that defines the relationship in your Model
                'attribute' => "question", // foreign key attribute that is shown to user
                'model' => 'App\Models\Question' // foreign key model
            ]
        );
        $this->crud->addColumn(
            [
                'name' => 'answer', // The db column name
                'type' => 'Text',
            ]
        );
        if (!$this->request->has('order')) {
            $this->crud->orderBy('questionId');
        }
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(AnswersRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        $this->setupAddUpdateOprations();
    }

    protected function setupUpdateOperation()
    {
        $this->setupAddUpdateOprations();
    }

    protected function setupAddUpdateOprations()
    {
        $this->crud->setValidation(AnswersRequest::class);

        $this->crud->addField(
            [
                'name' => 'questionId',
                'type' => 'select',
                'entity' => 'question',
                'attribute' => 'question'
            ]
        );
        $this->crud->addField(
            [
                'name' => 'answer',
                'type' => 'textarea',
            ]
        );
        $this->crud->addField(
            [
                'name' => 'correct',
                'type' => 'checkbox',
            ]
        );
    }
}
