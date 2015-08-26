<?php namespace C4tech\Test\NestedSet;

use C4tech\Support\Test\Model as TestCase;
use Codeception\Verify;
use Illuminate\Support\Facades\Config;
use Mockery;

class ModelTest extends TestCase
{
    public function setUp()
    {
        $this->setModel('C4tech\NestedSet\Model');
    }

    public function testGetDates()
    {
        $dates = $this->model->getDates();

        expect($dates)->contains('created_at');
        expect($dates)->contains('updated_at');
        expect($dates)->contains('deleted_at');
    }

    public function testToArray()
    {
        $this->model->test_thing = 123;
        Config::shouldReceive('get')
            ->with('c4tech.jsonify_output', true)
            ->once()
            ->andReturn(false);

        expect($this->model->toArray())->equals(['test_thing' => 123]);
    }

    public function testToArrayJsonify()
    {
        $model = $this->getModelMock();
        $model->shouldAllowMockingProtectedMethods();
        $model->test_thing = 123;
        $model->shouldReceive('convertToCamelCase')
            ->with(['test_thing' => 123])
            ->once()
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with('c4tech.jsonify_output', true)
            ->once()
            ->andReturn(true);

        expect($model->toArray())->false();
    }
}
