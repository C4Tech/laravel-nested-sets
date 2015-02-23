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

    /**
     * Parent
     *
     * Retrieves and caches parent object.
     */
    public function parent()
    {
        return $this->object->parent()
            ->cacheTags($this->getTags('parent'))
            ->remember(static::CACHE_DAY);
    }

    /**
     * Get Parent
     *
     * Retrieves and caches parent object.
     * @return static
     */
    public function getParent()
    {
        return $this->parent()->get();
    }

    /**
     * (Immediate) Children
     *
     * Retrieves and caches child objects.
     */
    public function children()
    {
        return $this->object->children()
            ->cacheTags($this->getTags('children'))
            ->remember(static::CACHE_DAY);
    }

    /**
     * Get Children
     *
     * Retrieves and caches child objects.
     * @return \Illuminate\Support\Collection
     */
    public function getChildren()
    {
        return $this->children()->get();
    }

    /**
     * Descendants
     *
     * Retrieves and caches child objects.
     */
    public function descendants($include_self = true)
    {
        $scope = ($include_self) ? 'descendantsAndSelf' : 'descendants';
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
        return $this->descendants($include_self)->get();
    }

    /**
     * Ancestors
     *
     * Retrieves and caches parent objects.
     */
    public function ancestors($include_self = true)
    {
        $scope = ($include_self) ? 'ancestorsAndSelf' : 'ancestors';
        return $this->object->$scope()
            ->cacheTags($this->getTags($scope))
            ->remember(static::CACHE_DAY);
    }

    /**
     * Get Ancestors
     *
     * Retrieves and caches parent objects.
     * @return \Illuminate\Support\Collection
     */
    public function getAncestors($include_self = true)
    {
        return $this->ancestors($include_self)->get();
    }

    public function roots()
    {
        $model = Config::get(static::$model, static::$model);
        return $model::roots()
            ->cacheTags($this->formatTag('roots'))
            ->remember(static::CACHE_LONG);
    }

    public function getRoots()
    {
        return $this->roots()->get();
    }

    public function trunks()
    {
        return $this->object->trunks()
            ->cacheTags($this->formatTag('trunks'))
            ->remember(static::CACHE_LONG);
    }

    public function getTrunks()
    {
        return $this->trunks()->get();
    }

    public function leaves()
    {
        return $this->object->leaves()
            ->cacheTags($this->formatTag('leaves'))
            ->remember(static::CACHE_LONG);
    }

    public function getLeaves()
    {
        return $this->leaves()->get();
    }
}
