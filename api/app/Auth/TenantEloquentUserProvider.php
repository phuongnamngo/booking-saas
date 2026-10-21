<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Auth runs before the tenant middleware, so the session user is loaded without that scope.
     *
     * @template TModel of Model
     *
     * @param  TModel|null  $model
     * @return Builder<TModel>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope('tenant');
    }
}
