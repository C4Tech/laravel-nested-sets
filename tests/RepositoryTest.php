<?php namespace C4tech\Test\NestedSet;

use C4tech\Support\Test\Repository as TestCase;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;

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

    public function testParent()
    {
        $tag = ['test'];

        $this->mocked_model->shouldReceive('parent->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with('parent')
            ->once()
            ->andReturn($tag);

        expect($this->repo->parent())->true();
    }

    public function testGetParent()
    {
        $this->repo->shouldReceive('parent->get')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getParent())->true();
    }

    public function testChildren()
    {
        $tag = ['test'];

        $this->mocked_model->shouldReceive('children->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with('children')
            ->once()
            ->andReturn($tag);

        expect($this->repo->children())->true();
    }

    public function testGetChildren()
    {
        $this->repo->shouldReceive('children->get')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getChildren())->true();
    }

    public function testDescendantsDefault()
    {
        $tag = ['test'];
        $method = 'descendantsAndSelf';

        $this->mocked_model->shouldReceive($method . '->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with($method)
            ->once()
            ->andReturn($tag);

        expect($this->repo->descendants())->true();
    }

    public function testDescendantsNoSelf()
    {
        $tag = ['test'];
        $method = 'descendants';

        $this->mocked_model->shouldReceive($method . '->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with($method)
            ->once()
            ->andReturn($tag);

        expect($this->repo->descendants(false))->true();
    }

    public function testGetDescendantsDefault()
    {
        $this->repo->shouldReceive('descendants->get')
            ->with(true)
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getDescendants())->true();
    }

    public function testGetDescendantsNoSelf()
    {
        $this->repo->shouldReceive('descendants->get')
            ->with(false)
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getDescendants(false))->true();
    }

    public function testAncestorsDefault()
    {
        $tag = ['test'];
        $method = 'ancestorsAndSelf';

        $this->mocked_model->shouldReceive($method . '->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with($method)
            ->once()
            ->andReturn($tag);

        expect($this->repo->ancestors())->true();
    }

    public function testAncestorsNoSelf()
    {
        $tag = ['test'];
        $method = 'ancestors';

        $this->mocked_model->shouldReceive($method . '->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('getTags')
            ->with($method)
            ->once()
            ->andReturn($tag);

        expect($this->repo->ancestors(false))->true();
    }

    public function testGetAncestorsDefault()
    {
        $this->repo->shouldReceive('ancestors->get')
            ->with(true)
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getAncestors())->true();
    }

    public function testGetAncestorsNoSelf()
    {
        $this->repo->shouldReceive('ancestors->get')
            ->with(false)
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getAncestors(false))->true();
    }

    public function testRoots()
    {
        $tag = ['test'];

        $this->mocked_model->shouldReceive('roots->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with(null, null)
            ->once()
            ->andReturn($this->mocked_model);

        $this->repo->shouldReceive('formatTag')
            ->with('roots')
            ->once()
            ->andReturn($tag);

        expect($this->repo->roots())->true();
    }

    public function testGetRoots()
    {
        $this->repo->shouldReceive('roots->get')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getRoots())->true();
    }

    public function testTrunks()
    {
        $tag = ['test'];

        $this->mocked_model->shouldReceive('trunks->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('formatTag')
            ->with('trunks')
            ->once()
            ->andReturn($tag);

        expect($this->repo->trunks())->true();
    }

    public function testGetTrunks()
    {
        $this->repo->shouldReceive('trunks->get')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getTrunks())->true();
    }

    public function testLeaves()
    {
        $tag = ['test'];

        $this->mocked_model->shouldReceive('leaves->cacheTags->remember')
            ->withNoArgs()
            ->with($tag)
            ->with(Mockery::type('integer'))
            ->once()
            ->andReturn(true);

        $this->repo->shouldReceive('formatTag')
            ->with('leaves')
            ->once()
            ->andReturn($tag);

        expect($this->repo->leaves())->true();
    }

    public function testGetLeaves()
    {
        $this->repo->shouldReceive('leaves->get')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        expect($this->repo->getLeaves())->true();
    }
}
