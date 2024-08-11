<?php

namespace Webkul\RestApi\Http\Controllers\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Requests\InventoryRequest;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Requests\ProductForm;
use Webkul\Core\Rules\Slug;
use Webkul\Product\Helpers\ProductType;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\RestApi\Http\Resources\V1\Admin\Catalog\ProductResource;

class ProductController extends CatalogController
{
    /**
     * Repository class name.
     */
    public function repository(): string
    {
        return ProductRepository::class;
    }

    /**
     * Resource class name.
     */
    public function resource(): string
    {
        return ProductResource::class;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (
            ProductType::hasVariants($request->input('type'))
            && (! $request->has('super_attributes')
                || ! count($request->get('super_attributes')))
        ) {
            return response([
                'message' => trans('rest-api::app.admin.catalog.products.error.configurable-error'),
            ], 400);
        }

        $request->validate([
            'type'                => 'required',
            'attribute_family_id' => 'required',
            'partner'             => 'required',
            'sku'                 => ['required', 'unique:products,sku', new Slug],
        ]);

        Event::dispatch('catalog.product.create.before');

        $product = $this->getRepositoryInstance()->create($request->all());

        $product = $this->getRepositoryInstance()->update([
            //'channel' => null,
            //'locale'  => null,
            'partner'  => $request->partner,
        ], $product->id, ['partner']);

        Event::dispatch('catalog.product.create.after', $product);

        return response([
            'data'    => new ProductResource($product),
            'message' => trans('rest-api::app.admin.catalog.products.create-success'),
        ]);
    }


    public function createUploadedFileFromUrl(&$images) {

        //also replace the
        // if (request()->images)
        // with
        // if (request()->images && !empty(request()->images['files']))
        //in packages/Webkul/Admin/src/Http/Requests/ProductForm.php rules()

        foreach ($images as $imgKey => &$image) {
            if(is_string($image)){
                $image = urlencode($image);
                $image = str_replace("%2F", "/", $image);
                $image = str_replace("%3A//", "://", $image);     
                $image = str_replace("%3F", "?", $image);     
                $image = str_replace("%3D", "=", $image);     
       
                if(filter_var($image, FILTER_VALIDATE_URL)){
                    $stream = fopen($image, 'r');
                    $tempFile = tempnam(sys_get_temp_dir(), 'url-file-');
                    file_put_contents($tempFile, $stream);
                    $images['files'][$imgKey] = new \Illuminate\Http\UploadedFile($tempFile, '');
                }
                else
                    unset($images[$imgKey]);
            }
            else{
                // is image object from repository
            }
        }
        $images = array_filter($images);
    }
    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(ProductForm $request, int $id)
    {
        Event::dispatch('catalog.product.update.before', $id);

        // trick to upload images via urls
        $allreqs = $request->all();

        // product
        if(!empty($allreqs['images']))
            $this->createUploadedFileFromUrl($allreqs['images']);

        // variants
        if(!empty($allreqs['variants'])){
            foreach ($allreqs['variants'] as $key1 => &$variant) {
                if(!empty($variant['images'])){
                    $this->createUploadedFileFromUrl($variant['images']);
                }        
            }
        }

        $product = $this->getRepositoryInstance()->update($allreqs, $id);

        Event::dispatch('catalog.product.update.after', $product);

        return response([
            'data'    => new ProductResource($product),
            'message' => trans('rest-api::app.admin.catalog.products.update-success'),
        ]);
    }

    /**
     * Update inventories.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateInventories(InventoryRequest $inventoryRequest, ProductInventoryRepository $productInventoryRepository, int $id)
    {
        $product = $this->getRepositoryInstance()->findOrFail($id);

        Event::dispatch('catalog.product.update.before', $id);

        $productInventoryRepository->saveInventories($inventoryRequest->all(), $product);

        Event::dispatch('catalog.product.update.after', $product);

        return response()->json([
            'data'    => [
                'total' => $productInventoryRepository->where('product_id', $product->id)->sum('qty'),
            ],
            'message' => trans('rest-api::app.admin.catalog.products.inventories.update-success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $this->getRepositoryInstance()->findOrFail($id);

        Event::dispatch('catalog.product.delete.before', $id);

        $this->getRepositoryInstance()->delete($id);

        Event::dispatch('catalog.product.delete.after', $id);

        return response([
            'message' => trans('rest-api::app.admin.catalog.products.delete-success'),
        ]);
    }

    /**
     * Remove the specified resources from database.
     *
     * @return \Illuminate\Http\Response
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest)
    {
        $productIds = $massDestroyRequest->input('indices');

        foreach ($productIds as $productId) {
            Event::dispatch('catalog.product.delete.before', $productId);

            $this->getRepositoryInstance()->delete($productId);

            Event::dispatch('catalog.product.delete.after', $productId);
        }

        return response([
            'message' => trans('rest-api::app.admin.catalog.products.mass-operations.delete-success'),
        ]);
    }

    /**
     * Mass update the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest)
    {
        $results = $this->getRepositoryInstance()->findWhereIn('id', $massUpdateRequest->indices);

        foreach ($results as $result){
            Event::dispatch('catalog.product.update.before', $result->id);            

            $product = $this->getRepositoryInstance()->update([
                //'channel' => null,
                //'locale'  => null,
                'status'  => $massUpdateRequest->value,
            ], $result->id, ['status']);

            Event::dispatch('catalog.product.update.after', $product);
        }

        return response([
            'message' => trans('rest-api::app.admin.catalog.products.mass-operations.update-success'),
        ]);
    }
}
