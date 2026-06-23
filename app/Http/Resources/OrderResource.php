<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a full order (with items + status history) for the API response.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status->value,   // e.g. "pending"
            'customer_id'  => $this->customer_id,
            'rider_id'     => $this->rider_id,

            // money
            'subtotal'     => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total'        => $this->total,

            // delivery details
            'address'      => $this->address,
            'lat'          => $this->lat,
            'lng'          => $this->lng,
            'notes'        => $this->notes,

            // distance from the rider to the restaurant — only present in the
            // rider's "available orders" list (see RiderController@available)
            'distance_km'  => $this->when(isset($this->resource->distance_km), fn () => $this->resource->distance_km),

            // restaurant info, including pickup location (only when the relation is loaded)
            'restaurant'   => $this->whenLoaded('restaurant', fn () => [
                'id'      => $this->restaurant->id,
                'name'    => $this->restaurant->name,
                'address' => $this->restaurant->address,
                'lat'     => $this->restaurant->lat,
                'lng'     => $this->restaurant->lng,
            ]),

            // the ordered items (only when loaded)
            'items'        => OrderItemResource::collection($this->whenLoaded('items')),

            // the status timeline (only when loaded)
            'status_history' => $this->whenLoaded('statusHistory', fn () =>
                $this->statusHistory->map(fn ($entry) => [
                    'status'     => $entry->status->value,
                    'note'       => $entry->note,
                    'created_at' => $entry->created_at,
                ])
            ),

            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
