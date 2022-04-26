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

use Overblog\GraphQLBundle\Definition\Resolver\QueryInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Oro\Bundle\AddressBundle\Entity\Country;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class CountryResolver implements
    QueryInterface,
    ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function resolveCountry(?string $id)
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(Country::class);

        if (!$id) {
            $countries = $repo->findAll();
        } else {
            $countries = $repo->findBy(['iso2Code'=>$id]);
        }



//        if (!$country instanceof Country) {
//            throw new UserError(sprintf("Country %s not found", $id));
//        }

        return $countries;
    }

    public function resolveCountries()
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(Country::class);

        $countries = $repo->findAll();

//        if (!$country instanceof Country) {
//            throw new UserError(sprintf("Country %s not found", $id));
//        }

        return $countries;
    }
}
