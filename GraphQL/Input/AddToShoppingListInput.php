<?php
/**
 * AddToShoppingListInput.php
 *
 * @category  Aligent
 * @author    Adam Hall <adam.hall@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Input;

use Spatie\DataTransferObject\DataTransferObject;

class AddToShoppingListInput extends DataTransferObject
{
    public int $shoppingListId;
    public string $sku;
    public string $unit;
    public float $quantity;

    /**
     * @return int
     */
    public function getShoppingListId(): int
    {
        return $this->shoppingListId;
    }

    /**
     * @param int $shoppingListId
     * @return AddToShoppingListInput
     */
    public function setShoppingListId(int $shoppingListId): AddToShoppingListInput
    {
        $this->shoppingListId = $shoppingListId;
        return $this;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     * @return AddToShoppingListInput
     */
    public function setSku(string $sku): AddToShoppingListInput
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @param string $unit
     * @return AddToShoppingListInput
     */
    public function setUnit(string $unit): AddToShoppingListInput
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @param float $quantity
     * @return AddToShoppingListInput
     */
    public function setQuantity(float $quantity): AddToShoppingListInput
    {
        $this->quantity = $quantity;
        return $this;
    }
}