<?php

namespace Webkul\RestApi\Http\Resources\V1\Admin\Catalog;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /**
         * Not able to use individual key in the resource because
         * attributes are system defined and custom defined.
         *
         * @var array
         */
        $mainAttributes = $this->resource->toArray();

        if(!empty($mainAttributes['categories']))
            $mainAttributes['categories'] = array_column($mainAttributes['categories'], 'id');

        return [
            /**
             * Main attributes.
             */
            ...$mainAttributes,

            'sku' => $this->resource->sku,

            /**
             * Additional attributes.
             */
            'images'     => ProductImageResource::collection($this->images),
            'videos'     => ProductVideoResource::collection($this->videos),
            'additional' => $this->additional,
        ];
    }
}
