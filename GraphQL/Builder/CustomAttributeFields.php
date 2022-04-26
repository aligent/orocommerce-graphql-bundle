<?php
/**
 * @category  Aligent
 * @package
 * @author    Chris Rossi <chris.rossi@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @license
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Builder;

use Overblog\GraphQLBundle\Definition\Builder\MappingInterface;

class CustomAttributeFields implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        $propertyCreatedAt = $config['propertyCreatedAt'] ?? 'createdAt';
        $propertyUpdatedAt = $config['propertyUpdatedAt'] ?? 'updatedAt';

        return [
            'createdAt' => [
                'description' => 'The creation date of the object',
                'type' => 'Int!',
                'resolve' => "@=value.$propertyCreatedAt",
            ],
            'updatedAt' => [
                'description' => 'The update date of the object',
                'type' => 'Int!',
                'resolve' => "@=value.$propertyUpdatedAt",
            ],
        ];
    }
}