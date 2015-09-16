<?php namespace C4tech\NestedSet;

use C4tech\Support\Repository as BaseRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Node Repository
 *
 * Common business logic wrapper to Node Models.
 * @property bool $exists Does the Model exist in the DB?
 */
abstract class Repository extends BaseRepository
{
    public function boot()
    {
        if (!($model = $this->getModelClass())) {
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
                Log::debug(
                    'Touching parents to trigger cache flushing.',
                    ['parent' => $node->parent]
                );
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

    /**
     * @inheritDoc
     */
    public function create(array $data = [])
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

    /**
     * @inheritDoc
     */
    public function update(array $data = [])
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
        } elseif ($parent === 0 || $parent === "") {
            $this->object->makeRoot();
        }

        return $response;
    }

    /**
     * Parent
     *
     * Begin a query for retrieving the node's parent.
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->object->parent();
    }

    /**
     * Get Parent
     *
     * Retrieves and caches the node's parent.
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
     * Begin a query for retrieving the node's children.
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->object->children();
    }

    /**
     * Get Children
     *
     * Retrieves and caches the node's children.
     * @return Illuminate\Support\Collection
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

    /**
     * Get Descendant Scope
     *
     * Calculate which descendant method to call.
     * @param  boolean $include_self Include the current node in query results?
     * @return string
     */
    protected function getDescendantScope($include_self = true)
    {
        return ($include_self) ? 'descendantsAndSelf' : 'descendants';
    }

    /**
     * Descendants
     *
     * Begin query for all nodes descendant from the current one.
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function descendants($include_self = true)
    {
        $scope = $this->getDescendantScope($include_self);
        return $this->object->$scope();
    }

    /**
     * Get Descendants
     *
     * Retrieves and caches the node's descendants.
     * @return Illuminate\Support\Collection
     */
    public function getDescendants($include_self = true)
    {
        $scope = $this->getDescendantScope($include_self);
        return Cache::tags($this->getTags($scope))
            ->remember(
                $this->getCacheId($scope),
                self::CACHE_DAY,
                function () use ($include_self) {
                    return $this->descendants($include_self)->get();
                }
            );
    }

    /**
     * Get Ancestor Scope
     *
     * Calculate which ancestor method to call.
     * @param  boolean $include_self Include the current node in query results?
     * @return string
     */
    protected function getAncestorScope($include_self = true)
    {
        return ($include_self) ? 'ancestorsAndSelf' : 'ancestors';
    }

    /**
     * Ancestors
     *
     * Begin query for all nodes ascendant from the current one.
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function ancestors($include_self = true)
    {
        $scope = $this->getAncestorScope($include_self);
        return $this->object->$scope();
    }

    /**
     * Get Ancestors
     *
     * Retrieves and caches the node's ancestors.
     * @return Illuminate\Support\Collection
     */
    public function getAncestors($include_self = true)
    {
        $scope = $this->getAncestorScope($include_self);
        return Cache::tags($this->getTags($scope))
            ->remember(
                $this->getCacheId($scope),
                self::CACHE_DAY,
                function () use ($include_self) {
                    return $this->ancestors($include_self)->get();
                }
            );
    }

    /**
     * Roots
     *
     * Begin query for all root nodes.
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function roots()
    {
        $model = $this->getModelClass();
        return $model::roots();
    }

    /**
     * Get Roots
     *
     * Retrieves and caches all root nodes.
     * @return Illuminate\Support\Collection
     */
    public function getRoots()
    {
        return Cache::tags($this->formatTag('roots'))
            ->remember(
                $this->getCacheId('roots', ''),
                self::CACHE_LONG,
                function () {
                    return $this->roots()->get();
                }
            );
    }

    /**
     * Trunks
     *
     * Begin query for all descendant nodes with children.
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function trunks()
    {
        return $this->object->trunks();
    }

    /**
     * Get Trunks
     *
     * Retrieves and caches all descendant nodes with children.
     * @return Illuminate\Support\Collection
     */
    public function getTrunks()
    {
        return Cache::tags($this->formatTag('trunks'))
            ->remember(
                $this->getCacheId('trunks'),
                self::CACHE_LONG,
                function () {
                    return $this->trunks()->get();
                }
            );
    }

    /**
     * Leaves
     *
     * Begin query for all descendant nodes with no children.
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function leaves()
    {
        return $this->object->leaves();
    }

    /**
     * Get Leaves
     *
     * Retrieves and caches all descendant nodes without children.
     * @return Illuminate\Support\Collection
     */
    public function getLeaves()
    {
        return Cache::tags($this->formatTag('leaves'))
            ->remember(
                $this->getCacheId('leaves'),
                self::CACHE_LONG,
                function () {
                    return $this->leaves()->get();
                }
            );
    }
}
