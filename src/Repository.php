<?php namespace C4tech\NestedSet;

use C4tech\Support\Repository as BaseRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Node Repository
 *
 * Common business logic wrapper to Node Models.
 */
abstract class Repository extends BaseRepository
{
    public function boot()
    {
        if (!($model = Config::get(static::$model, static::$model))) {
            return;
        }

        parent::boot();

        // Flush nested set caches related to the object
        if (Config::get('app.debug')) {
            Log::info('Binding heirarchical caches', ['model' => $model]);
        }

        // Trigger save events after move events
        // Flushing parent caches directly causes an infinite recursion
        $touch = function ($node) {
            if (Config::get('app.debug')) {
                Log::debug('Touching parents to trigger cache flushing.', ['parent' => $node->parent]);
            }

            // Force parent caches to flush
            if ($node->parent) {
                $node->parent->touch();
            }
        };
        $model::moved($touch);

        // Flush caches related to the ancestors
        $flush = function ($node) {
            $tags = $this->make($node)->getParentTags();

            if (Config::get('app.debug')) {
                Log::debug('Flushing parent caches', ['tags' => $tags]);
            }
            Cache::tags($tags)->flush();
        };
        $model::saved($flush);
        $model::deleted($flush);
    }

    /**
     * Get Parent Tags
     *
     * Retrieves the parent tags which should be flushed after a write query.
     * @return array Cache tags
     */
    protected function getParentTags()
    {
        return $this->getAncestors(false)
            ->map(function ($node) {
                return $this->formatTag($node->id);
            })->toArray();
    }

    /**
     * Get Child Tags
     *
     * Retrieves the child tags which should be flushed after a write query.
     * @return array Cache tags
     */
    protected function getChildTags()
    {
        return $this->getDescendants(false)
            ->map(function ($node) {
                return $this->formatTag($node->id);
            })->toArray();
    }

    public function create($data = [])
    {
        $column = $this->object->getParentColumnName();
        $parent = false;

        if (isset($data[$column])) {
            $parent = $this->find($data[$column]);
        }
        unset($data[$column]);

        $new_object = parent::create($data);

        if ($new_object->exists && $parent && $parent->exists) {
            $new_object->getModel()->makeChildOf($parent->getModel());
        }

        return $new_object;
    }

    public function update($data = [])
    {
        $column = $this->object->getParentColumnName();
        $parent = null;

        if (isset($data[$column])) {
            $parent = $this->find($data[$column]);
        }
        unset($data[$column]);

        $response = parent::update($data);

        if ($parent && $parent->exists) {
            $this->object->makeChildOf($parent->getModel());
        } elseif ($parent === 0 or $parent === "") {
            $this->object->makeRoot();
        }

        return $response;
    }

    /**
     * Parent
     *
     * Retrieves and caches parent object.
     */
    public function parent()
    {
        return $this->object->parent();
    }

    /**
     * Get Parent
     *
     * Retrieves and caches parent object.
     * @return static
     */
    public function getParent()
    {
        return Cache::tags($this->getTags('parent'))
            ->remember(
                $this->getCacheId('parent'),
                self::CACHE_DAY,
                function () {
                    return $this->parent()->get();
                }
            );
    }

    /**
     * (Immediate) Children
     *
     * Retrieves and caches child objects.
     */
    public function children()
    {
        return $this->object->children();
    }

    /**
     * Get Children
     *
     * Retrieves and caches child objects.
     * @return \Illuminate\Support\Collection
     */
    public function getChildren()
    {
        return Cache::tags($this->getTags('children'))
            ->remember(
                $this->getCacheId('children'),
                self::CACHE_DAY,
                function () {
                    return $this->children()->get();
                }
            );
    }

    protected function getDescendantScope($include_self)
    {
        return ($include_self) ? 'descendantsAndSelf' : 'descendants';
    }

    /**
     * Descendants
     *
     * Retrieves and caches child objects.
     */
    public function descendants($include_self = true)
    {
        $scope = $this->getDescendantScope($include_self);
        return $this->object->$scope()
            ->cacheTags($this->getTags($scope))
            ->remember(static::CACHE_DAY);
    }

    /**
     * Get Descendants
     *
     * Retrieves and caches child objects.
     * @return \Illuminate\Support\Collection
     */
    public function getDescendants($include_self = true)
    {
        $scope = $this->getDescendantScope($include_self);
        return Cache::tags($this->getTags($scope))
            ->remember(
                $this->getCacheId($scope),
                self::CACHE_DAY,
                function () use ($include_self) {
                    $this->descendants($include_self)->get();
                }
            );
        return ;
    }

    protected function getAncendantScope($include_self)
    {
        return ($include_self) ? 'ancestorsAndSelf' : 'ancestors';
    }

    /**
     * Ancestors
     *
     * Retrieves and caches parent objects.
     */
    public function ancestors($include_self = true)
    {
        $scope = $this->getAncendantScope($include_self);
        return $this->object->$scope();
    }

    /**
     * Get Ancestors
     *
     * Retrieves and caches parent objects.
     * @return \Illuminate\Support\Collection
     */
    public function getAncestors($include_self = true)
    {
        $scope = $this->getAncendantScope($include_self);
        return Cache::tags($this->getTags($scope))
            ->remember(
                $this->getCacheId($scope),
                self::CACHE_DAY,
                function () use ($include_self) {
                    return $this->ancestors($include_self)->get();
                }
            );
    }

    public function roots()
    {
        $model = $this->getModelClass();
        return $model::roots();
    }

    public function getRoots()
    {
        return Cache::tags($this->formatTag('roots'))
            ->remember(
                $this->getCacheId('roots', null),
                self::CACHE_LONG,
                function () {
                    return $this->roots()->get();
                }
            );
    }

    public function trunks()
    {
        return $this->object->trunks();
    }

    public function getTrunks()
    {
        return Cache::tags($this->formatTag('trunks'))
            ->remember(
                $this->getCacheId('trunks', null),
                self::CACHE_LONG,
                function () {
                    return $this->trunks()->get();
                }
            );
    }

    public function leaves()
    {
        return $this->object->leaves();
    }

    public function getLeaves()
    {
        return Cache::tags($this->formatTag('leaves'))
            ->remember(
                $this->getCacheId('leaves', null),
                self::CACHE_LONG,
                function () {
                    return $this->leaves()->get();
                }
            );
    }
}
