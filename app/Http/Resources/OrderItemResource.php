<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single order line for the API response.
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'menu_item_id' => $this->menu_item_id,
            // include the item name only when the menuItem relation has been loaded
            'name'         => $this->whenLoaded('menuItem', fn () => $this->menuItem?->name),
            'quantity'     => $this->quantity,
            'unit_price'   => $this->unit_price,
            'subtotal'     => $this->subtotal,
        ];
    }
}
