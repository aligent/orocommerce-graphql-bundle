<?php
/**
 * @category  Aligent
 * @package
 * @author    Chris Rossi <chris.rossi@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @license
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Resolver;

use Oro\Bundle\AddressBundle\Entity\Region;
use Overblog\GraphQLBundle\Definition\Resolver\QueryInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class RegionResolver implements
    QueryInterface,
    ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function resolveRegion(?String $combinedCode)
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(Region::class);

        if ($combinedCode == null) {
            $regions = $repo->findAll();
        } else {
            $region = $repo->find($combinedCode);

            if (!$region instanceof Region) {
                throw new UserError(sprintf("Region %s not found", $combinedCode));
            }
            $regions = [$region];
        }

        return $regions;
    }
}
