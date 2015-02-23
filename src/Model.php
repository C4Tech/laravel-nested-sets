<?php namespace C4tech\NestedSet;

use Baum\Node as BaseModel;
use C4tech\Support\Contracts\ModelInterface;
use C4tech\Support\Traits\DateFilter;
use C4tech\Support\Traits\Presentable;
use Robbo\Presenter\PresentableInterface;

/**
 * A foundation Model with useful features.
 */
class Model extends BaseModel implements PresentableInterface, ModelInterface
{
    /**
     * Consume the Presentable and DateFilter traits.
     */
    use DateFilter, Presentable;

    /**
     * @inheritdoc
     */
    protected $guarded = ['id', 'parent_id', 'lft', 'rgt', 'depth', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Get Dates
     *
     * Overloads the defined database fields which ought to be converted to
     * Carbon objects in order to add deleted_at as a default.
     * @return array Column names to transform.
     */
    public function getDates()
    {
        return array_merge(parent::getDates(), ['deleted_at']);
    }
}
