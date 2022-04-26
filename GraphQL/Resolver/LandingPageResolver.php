<?php
/**
 * @category  Aligent
 * @package
 * @author    Jan Plank <jan.plank@aligent.com.au>
 * @copyright 2021 Aligent Consulting.
 * @license
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Resolver;

use Oro\Bundle\CMSBundle\Entity\Page;
use Overblog\GraphQLBundle\Definition\Resolver\QueryInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class LandingPageResolver  implements QueryInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function resolveLandingPage(int $id)
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(Page::class);

        $landingPage = $repo->find($id);

        if (!$landingPage instanceof Page) {
            throw new UserError(sprintf("Landing Page %s not found", $id));
        }

        return $landingPage;
    }

}
