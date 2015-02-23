<?php namespace C4tech\Test\NestedSet;

use Codeception\Verify;
use Mockery;
use C4tech\Support\Test\Model as TestCase;

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
}
