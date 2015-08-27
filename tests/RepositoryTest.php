<?php namespace C4tech\Test\NestedSet;

use C4tech\Support\Test\Repository as TestCase;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use stdClass;

class RepositoryTest extends TestCase
{
    public function setUp()
    {
        $this->setRepository('C4tech\NestedSet\Repository', 'C4tech\NestedSet\Model');
    }

    public function tearDown()
    {
        Cache::clearResolvedInstances();
        Config::clearResolvedInstances();
        Log::clearResolvedInstances();
        parent::tearDown();
    }

    public function testBootNull()
    {
        Config::shouldReceive('get')
            ->with(null, null)
            ->once()
            ->andReturn(null);

        Config::shouldReceive('get')
            ->with('app.debug')
            ->never();

        expect_not($this->repo->boot());
    }

    public function testBootNoDebug()
    {
        $model = Mockery::mock('C4tech\Support\Contracts\ModelInterface');
        $model->shouldReceive('saved')
            ->with(Mockery::type('callable'))
            ->once();
        $model->shouldReceive('deleted')
            ->with(Mockery::type('callable'))
            ->once();
        $model->shouldReceive('moved')
            ->with(Mockery::type('callable'))
            ->once();

        Config::shouldReceive('get')
            ->with('app.debug')
            ->once()
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with(null, null)
            ->twice()
            ->andReturn($model, null);

        Log::shouldReceive('info')
            ->never();

        expect_not($this->repo->boot());
    }

    public function testBootClosure()
    {
        $tag = ['test-123'];

        $node = Mockery::mock('C4tech\Support\Contracts\ModelInterface');
        $node->parent = Mockery::mock('C4tech\Support\Contracts\ModelInterface[touch]');
        $node->parent->shouldReceive('touch')
            ->withNoArgs()
            ->once();

        $model = Mockery::mock('stdClass');
        $model->shouldReceive('moved')
            ->with(
                Mockery::on(function ($method) use ($node) {
                    expect_not($method($node));

                    return true;
                })
            )->once();

        $model->shouldReceive('saved')
            ->with(
                Mockery::on(function ($method) use ($node) {
                    expect_not($method($node));

                    return true;
                })
            )->once();

        $model->shouldReceive('deleted')
            ->once();

        Config::shouldReceive('get')
            ->with(null, null)
            ->twice()
            ->andReturn($model, null);
        Config::shouldReceive('get')
            ->with('app.debug')
            ->times(3)
            ->andReturn(true);

        Log::shouldReceive('info')
            ->with(Mockery::type('string'), Mockery::type('array'))
            ->once();
        Log::shouldReceive('debug')
            ->with(Mockery::type('string'), Mockery::type('array'))
            ->twice();

        Cache::shouldReceive('tags->flush')
            ->with($tag)
            ->withNoArgs()
            ->once();

        $this->repo->shouldReceive('make->getParentTags')
            ->with($node)
            ->withNoArgs()
            ->andReturn($tag);

        expect_not($this->repo->boot());
    }

    public function testGetParentTags()
    {
        $result = true;
        $array = Mockery::mock('stdClas');
        $array->shouldReceive('toArray')
            ->withNoArgs()
            ->once()
            ->andReturn($result);

        $map = Mockery::mock('stdClass');
        $map->shouldReceive('map')
            ->with(
                Mockery::on(function ($method) {
                    $tag = 'test';
                    $node = Mockery::mock('stdCalss');
                    $node->id = 14;

                    $this->repo->shouldReceive('formatTag')
                        ->with($node->id)
                        ->once()
                        ->andReturn($tag);

                    expect($method($node))->equals($tag);

                    return true;
                })
            )->once()
            ->andReturn($array);

        $this->repo->shouldReceive('getAncestors')
            ->with(false)
            ->once()
            ->andReturn($map);

        $method = $this->getMethod($this->repo, 'getParentTags');
        expect($method->invoke($this->repo))->equals($result);
    }

    public function testGetChildTags()
    {
        $result = true;
        $array = Mockery::mock('stdClas');
        $array->shouldReceive('toArray')
            ->withNoArgs()
            ->once()
            ->andReturn($result);

        $map = Mockery::mock('stdClass');
        $map->shouldReceive('map')
            ->with(
                Mockery::on(function ($method) {
                    $tag = 'test';
                    $node = Mockery::mock('stdCalss');
                    $node->id = 14;

                    $this->repo->shouldReceive('formatTag')
                        ->with($node->id)
                        ->once()
                        ->andReturn($tag);

                    expect($method($node))->equals($tag);

                    return true;
                })
            )->once()
            ->andReturn($array);

        $this->repo->shouldReceive('getDescendants')
            ->with(false)
            ->once()
            ->andReturn($map);

        $method = $this->getMethod($this->repo, 'getChildTags');
        expect($method->invoke($this->repo))->equals($result);
    }

    public function testCreateEmpty()
    {
        $object = new stdClass;
        $object->exists = true;

        $this->stubCreate([], $object);
        expect($this->repo->create())->equals($object);
    }

    public function testCreateParent()
    {
        $data = ['test' => true];
        $parent_id = 13;
        $parent_model = 'model!';

        $this->mocked_model->shouldReceive('getParentColumnName')
            ->withNoArgs()
            ->once()
            ->andReturn('parent_id');

        $created_model = new stdClass;
        $created_model->exists = true;

        $created = Mockery::mock('C4tech\NestedSet\Repository')
            ->makePartial();
        $this->setPropertyValue($created, 'object', $created_model);
        $created->shouldReceive('getModel->makeChildOf')
            ->with($parent_model)
            ->once();

        $parent = Mockery::mock('stdClass');
        $parent->shouldReceive('getModel')
            ->withNoArgs()
            ->once()
            ->andReturn($parent_model);
        $parent->exists = true;

        $this->stubCreate($data, $created);

        $this->repo->shouldReceive('find')
            ->with($parent_id)
            ->once()
            ->andReturn($parent);

        $data['parent_id'] = $parent_id;

        expect($this->repo->create($data))->equals($created);
    }

    public function testUpdate()
    {
        $updates = [
            'magic' => true
        ];

        $this->mocked_model->shouldReceive('getParentColumnName')
            ->withNoArgs()
            ->once()
            ->andReturn('parent_id');

        $this->stubUpdate($updates, false);

        expect($this->repo->update($updates))->equals(false);
    }

    public function testUpdateParent()
    {
        $parent_id = 14;
        $parent_model = 'alphabet';
        $parent = Mockery::mock('stdClass');
        $parent->shouldReceive('getModel')
            ->withNoArgs()
            ->once()
            ->andReturn($parent_model);
        $parent->exists = true;
        $updates = [
            'magic' => true
        ];

        $this->mocked_model->shouldReceive('getParentColumnName')
            ->withNoArgs()
            ->once()
            ->andReturn('parent_id');

        $this->repo->shouldReceive('find')
            ->with($parent_id)
            ->once()
            ->andReturn($parent);

        $this->stubUpdate($updates, false);

        $this->mocked_model->shouldReceive('makeChildOf')
            ->with($parent_model)
            ->once();

        $updates['parent_id'] = $parent_id;

        expect($this->repo->update($updates))->false();
    }

    public function testUpdateRemoveParent()
    {
        $parent = 0;
        $parent_id = 14;
        $updates = [
            'magic' => true
        ];

        $this->mocked_model->shouldReceive('getParentColumnName')
            ->withNoArgs()
            ->once()
            ->andReturn('parent_id');

        $this->repo->shouldReceive('find')
            ->with($parent_id)
            ->once()
            ->andReturn($parent);

        $this->stubUpdate($updates, false);

        $this->mocked_model->shouldReceive('makeRoot')
            ->withNoArgs()
            ->once();

        $updates['parent_id'] = $parent_id;

        expect($this->repo->update($updates))->false();
    }

    public function testParent()
    {
        $this->mocked_model->shouldReceive('parent')
            ->once()
            ->andReturn(true);

        expect($this->repo->parent())->true();
    }

    public function testGetParent()
    {
        $this->repo->shouldReceive('getTags')
            ->with('parent')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('parent')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('parent->get')
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);


        expect($this->repo->getParent())->true();
    }

    public function testChildren()
    {
        $this->mocked_model->shouldReceive('children')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->children())->true();
    }

    public function testGetChildren()
    {
        $this->repo->shouldReceive('getTags')
            ->with('children')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('children')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('children->get')
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);


        expect($this->repo->getChildren())->true();
    }

    public function testGetDescendantScope()
    {
        $method = $this->getMethod($this->repo, 'getDescendantScope');
        expect($method->invoke($this->repo))->equals('descendantsAndSelf');
    }

    public function testGetDescendantScopeNoSelf()
    {
        $method = $this->getMethod($this->repo, 'getDescendantScope');
        expect($method->invoke($this->repo, false))->equals('descendants');
    }

    public function testDescendantsDefault()
    {
        $this->mocked_model->shouldReceive('descendantsAndSelf')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->descendants())->true();
    }

    public function testDescendantsNoSelf()
    {
        $this->mocked_model->shouldReceive('descendants')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->descendants(false))->true();
    }

    public function testGetDescendantsDefault()
    {
        $this->repo->shouldReceive('getTags')
            ->with('descendantsAndSelf')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('descendantsAndSelf')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('descendants->get')
                        ->with(true)
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);


        expect($this->repo->getDescendants())->true();
    }

    public function testGetDescendantsNoSelf()
    {
        $this->repo->shouldReceive('getTags')
            ->with('descendants')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('descendants')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('descendants->get')
                        ->with(false)
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);

        expect($this->repo->getDescendants(false))->true();
    }

    public function testGetAncestorScope()
    {
        $method = $this->getMethod($this->repo, 'getAncestorScope');
        expect($method->invoke($this->repo))->equals('ancestorsAndSelf');
    }

    public function testGetAncestorScopeNoSelf()
    {
        $method = $this->getMethod($this->repo, 'getAncestorScope');
        expect($method->invoke($this->repo, false))->equals('ancestors');
    }

    public function testAncestorsDefault()
    {
        $this->mocked_model->shouldReceive('ancestorsAndSelf')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->ancestors())->true();
    }

    public function testAncestorsNoSelf()
    {
        $this->mocked_model->shouldReceive('ancestors')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->ancestors(false))->true();
    }

    public function testGetAncestorsDefault()
    {
        $this->repo->shouldReceive('getTags')
            ->with('ancestorsAndSelf')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('ancestorsAndSelf')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('ancestors->get')
                        ->with(true)
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);


        expect($this->repo->getAncestors())->true();
    }

    public function testGetAncestorsNoSelf()
    {
        $this->repo->shouldReceive('getTags')
            ->with('ancestors')
            ->once()
            ->andReturn(['tag']);

        $this->repo->shouldReceive('getCacheId')
            ->with('ancestors')
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('array'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('ancestors->get')
                        ->with(false)
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);

        expect($this->repo->getAncestors(false))->true();
    }

    public function testRoots()
    {
        $this->mocked_model->shouldReceive('roots')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getModelClass')
            ->once()
            ->andReturn($this->mocked_model);

        expect($this->repo->roots())->true();
    }

    public function testGetRoots()
    {
        $this->repo->shouldReceive('formatTag')
            ->with('roots')
            ->once()
            ->andReturn('tag');

        $this->repo->shouldReceive('getCacheId')
            ->with('roots', null)
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('string'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('roots->get')
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);

        expect($this->repo->getRoots())->true();
    }

    public function testTrunks()
    {
        $this->mocked_model->shouldReceive('trunks')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->trunks())->true();
    }

    public function testGetTrunks()
    {
        $this->repo->shouldReceive('formatTag')
            ->with('trunks')
            ->once()
            ->andReturn('tag');

        $this->repo->shouldReceive('getCacheId')
            ->with('trunks', null)
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('string'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('trunks->get')
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);

        expect($this->repo->getTrunks())->true();
    }

    public function testLeaves()
    {
        $this->mocked_model->shouldReceive('leaves')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->leaves())->true();
    }

    public function testGetLeaves()
    {
        $this->repo->shouldReceive('formatTag')
            ->with('leaves')
            ->once()
            ->andReturn('tag');

        $this->repo->shouldReceive('getCacheId')
            ->with('leaves', null)
            ->once()
            ->andReturn('hashid');

        Cache::shouldReceive('tags->remember')
            ->with(Mockery::type('string'))
            ->with(
                Mockery::type('string'),
                Mockery::type('integer'),
                Mockery::on(function ($closure) {
                    $this->repo->shouldReceive('leaves->get')
                        ->withNoArgs()
                        ->once()
                        ->andReturn(false);
                    expect($closure())->false();

                    return true;
                })
            )
            ->once()
            ->andReturn(true);

        expect($this->repo->getLeaves())->true();
    }
}
