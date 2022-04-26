<?php
/**
 * AddToShoppingListMutation.php
 *
 * @category  Aligent
 * @author    Adam Hall <adam.hall@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Mutation;

use Aligent\GraphQLBundle\GraphQL\Input\AddToShoppingListInput;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JetBrains\PhpStorm\ArrayShape;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Oro\Bundle\ProductBundle\Form\Type\FrontendLineItemType;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Form\Handler\LineItemHandler;
use Oro\Bundle\ShoppingListBundle\Manager\CurrentShoppingListManager;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListManager;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Overblog\GraphQLBundle\Definition\Resolver\MutationInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Overblog\GraphQLBundle\Error\UserErrors;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AddToShoppingListMutation implements MutationInterface, AliasedInterface
{
    protected ShoppingListManager $shoppingListManager;
    protected CurrentShoppingListManager $currentShoppingListManager;
    protected AuthorizationCheckerInterface $authorizationChecker;
    protected FormFactoryInterface $formFactory;
    protected ProductRepository $productRepository;
    protected ManagerRegistry $registry;
    protected ValidatorInterface $validator;

    /**
     * @param ShoppingListManager $shoppingListManager
     * @param CurrentShoppingListManager $currentShoppingListManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param FormFactoryInterface $formFactory
     * @param ManagerRegistry $registry
     * @param ValidatorInterface $validator
     */
    public function __construct(
        ShoppingListManager $shoppingListManager,
        CurrentShoppingListManager $currentShoppingListManager,
        AuthorizationCheckerInterface $authorizationChecker,
        FormFactoryInterface $formFactory,
        ManagerRegistry $registry,
        ValidatorInterface $validator
    ) {
        $this->shoppingListManager = $shoppingListManager;
        $this->currentShoppingListManager = $currentShoppingListManager;
        $this->authorizationChecker = $authorizationChecker;
        $this->formFactory = $formFactory;
        $this->registry = $registry;
        $this->validator = $validator;
        $this->productRepository = $this->registry->getRepository(Product::class);
    }

    #[ArrayShape(['success' => "bool"])]
    public function addToShoppingList(array $args): array
    {
        try {
             $input = new AddToShoppingListInput($args);
            $product = $this->productRepository->findOneBySku($input->getSku());

            if (!$product) {
                throw new \InvalidArgumentException($input->getSku() . " does not exist.");
            }

            // if no shopping list id is passed then we should create a new one
            $create = (bool) !$input->getShoppingListId();
            $shoppingList = $this->currentShoppingListManager->getForCurrentUser($input->getShoppingListId(), $create);

            if ($shoppingList->getId() != $input->getShoppingListId()) {
                throw new UserError("Shopping List does not exist, or you do not have access to it.");
            }

            if (!$this->authorizationChecker->isGranted('EDIT', $shoppingList)) {
                throw new UserError('You do not have the EDIT permission on this shopping list.');
            }

            $lineItem = (new LineItem())
                ->setProduct($product)
                ->setShoppingList($shoppingList)
                ->setCustomerUser($shoppingList->getCustomerUser())
                ->setOrganization($shoppingList->getOrganization());

            $form = $this->formFactory->create(FrontendLineItemType::class, $lineItem, ['csrf_protection' => null]);

            /** @var EntityManagerInterface $em */
            $em = $this->registry->getManagerForClass(LineItem::class);
            $em->beginTransaction();
            $form->submit([
                'quantity' => $input->getQuantity(),
                'unit' => $input->getUnit()
            ]);

            if (!$form->isValid()) {
                $formErrors = $form->getErrors(true);
                $errors = [];
                foreach ($formErrors as $formError) {
                    $errors[] = new UserError($formError->getMessage());
                }

                throw new UserErrors($errors);
            }

            $this->shoppingListManager->addLineItem($lineItem, $lineItem->getShoppingList(), false, true);
            $shoppingListErrors = $this->validateShoppingList($lineItem->getShoppingList());

            if (!empty($shoppingListErrors)) {
                $errors = [];
                foreach ($shoppingListErrors as $shoppingListError) {
                    $errors[] = new UserError($shoppingListError);
                }

                throw new UserErrors($errors);
            }

            $em->flush();
            $em->commit();
        } catch (UnknownProperties $e) {
            die();
        }
        return [
            'success' => true
        ];
    }

    /**
     * @param ShoppingList $shoppingList
     * @return array<string>
     */
    protected function validateShoppingList(ShoppingList $shoppingList): array
    {
        $errors = [];
        $constraintViolationList = $this->validator->validate($shoppingList);

        foreach ($constraintViolationList as $constraintViolation) {
            $errors[] = $constraintViolation->getMessage();
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    public static function getAliases(): array
    {
        return [
            'addToShoppingList' => 'add_to_shopping_list'
        ];
    }
}