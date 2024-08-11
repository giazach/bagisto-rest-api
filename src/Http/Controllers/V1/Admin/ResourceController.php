<?php

namespace Webkul\RestApi\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use Webkul\RestApi\Contracts\ResourceContract;
use Webkul\RestApi\Http\Controllers\V1\V1Controller;
use Webkul\RestApi\Traits\ProvideResource;
use Webkul\RestApi\Traits\ProvideUser;

class ResourceController extends V1Controller implements ResourceContract
{
    use ProvideResource, ProvideUser;

    /**
     * Resource name.
     *
     * Can be customizable in individual controller to change the resource name.
     *
     * @var string
     */
    protected $resourceName = 'Resource(s)';

    /**
     * These are ignored during request.
     *
     * @var array
     */
    protected $requestException = ['page', 'limit', 'pagination', 'sort', 'order', 'token'];

    /**
     * Returns a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allResources(Request $request)
    {
        $query = $this->getRepositoryInstance()->with('categories')->scopeQuery(function ($query) use ($request) {

            // make a copy of findByAttributeCode to a new findAllByAttributeCode (packages/Webkul/Product/src/Repositories/ProductRepository)
            // and replace the return with: return $this->findWhereIn('id', $filteredAttributeValues->pluck('product_id')->toArray())->all();
            if($byAttributes = $request->input('attributes')){ // eg ['attributes' => ['partner' => 4]]
                foreach ($byAttributes as $loop_code => $loop_value) {
                    $results = $this->getRepositoryInstance()->findAllByAttributeCode($loop_code, $loop_value);
                    $ids = array_column($results, 'id');
                    $query = $query->whereIn('id', $ids);
                }
            }

            foreach ($request->except($this->requestException) as $input => $value) {
                if(is_string($value))
                    $query = $query->whereIn($input, array_map('trim', explode(',', $value)));
            }

            if ($sort = $request->input('sort')) {
                $query = $query->orderBy($sort, $request->input('order') ?? 'desc');
            } else {
                $query = $query->orderBy('id', 'desc');
            }

            return $query;
        });

        if (is_null($request->input('pagination')) || $request->input('pagination')) {
            $results = $query->paginate($request->input('limit') ?? 10);
        } else {
            $results = $query->get();
        }

        return $this->getResourceCollection($results);
    }

    /**
     * Returns an individual resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getResource(int $id)
    {
        $resourceClassName = $this->resource();

        $resource = $this->getRepositoryInstance()->findOrFail($id);

        return new $resourceClassName($resource);
    }
}
