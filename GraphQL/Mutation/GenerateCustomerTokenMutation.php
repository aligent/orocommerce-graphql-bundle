<?php
/**
 * GenerateCustomerTokenMutation.php
 *
 * @category  Aligent
 * @author    Adam Hall <adam.hall@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Mutation;

use JetBrains\PhpStorm\ArrayShape;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserApi;
use Overblog\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Overblog\GraphQLBundle\Definition\Resolver\MutationInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class GenerateCustomerTokenMutation implements MutationInterface, AliasedInterface
{
    protected string $authenticationProviderKey;
    protected AuthenticationProviderInterface $authenticationProvider;
    protected ConfigManager $configManager;
    protected DoctrineHelper $doctrineHelper;
    protected TranslatorInterface $translator;

    /**
     * @param string $authenticationProviderKey
     * @param AuthenticationProviderInterface $authenticationProvider
     * @param ConfigManager $configManager
     * @param DoctrineHelper $doctrineHelper
     * @param TranslatorInterface $translator
     */
    public function __construct(
        string $authenticationProviderKey,
        AuthenticationProviderInterface $authenticationProvider,
        ConfigManager $configManager,
        DoctrineHelper $doctrineHelper,
        TranslatorInterface $translator
    ) {
        $this->authenticationProviderKey = $authenticationProviderKey;
        $this->authenticationProvider = $authenticationProvider;
        $this->configManager = $configManager;
        $this->doctrineHelper = $doctrineHelper;
        $this->translator = $translator;
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     */
    #[ArrayShape(['token' => "string"])]
    public function generateCustomerToken(string $email, string $password): array
    {
        $token = $this->authenticate($email, $password);

        if (empty($token)) {
            throw new \InvalidArgumentException('Failed to login');
        }

        return [
            'token' => $this->generateApiKey($token->getUser())
        ];
    }


    protected function authenticate(string $email, string $password): TokenInterface
    {
        $token = new UsernamePasswordToken(
            $email,
            $password,
            $this->authenticationProviderKey
        );

        if (!$this->authenticationProvider->supports($token)) {
            throw new \LogicException(sprintf(
                'Invalid authentication provider. The provider key is "%s".',
                $this->authenticationProviderKey
            ));
        }

        try {
            return $this->authenticationProvider->authenticate($token);
        } catch (AuthenticationException $e) {
            throw new AccessDeniedException(sprintf(
                'The user authentication fails. Reason: %s',
                $this->translator->trans($e->getMessageKey(), $e->getMessageData(), 'security')
            ));
        }
    }

    /**
     * @param CustomerUser $user
     *
     * @return string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function generateApiKey(CustomerUser $user): string
    {
        $apiKey = new CustomerUserApi();
        $apiKey->setApiKey($apiKey->generateKey());

        $user->addApiKey($apiKey);

        $em = $this->doctrineHelper->getEntityManager($user);
        $em->persist($apiKey);
        $em->flush();

        return $apiKey->getApiKey();
    }

    /**
     * @return string[]
     */
    public static function getAliases(): array
    {
        return [
           'generateCustomerToken' => 'generate_customer_token'
        ];
    }
}