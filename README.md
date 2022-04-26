# OroCommerce GraphQL Bundle - Work In Progress
This bundle was created during a hackathon event at Aligent as a proof of concept for adding a GraphQL API to OroCommerce. This bundle is in VERY early stages of development and should only be used for testing and learning purposes.

## Getting Started
1. Add this repository to your `composer.json` repository section e.g 
    ```
      "repositories": {
        "aligent-graphql": {
           "type": "vcs",
           "url": "https://github.com/aligent/orocommerce-graphql-bundle",
           "no-api": true
        }
      },
   ```
2. Require this bundle
   ```shell
      composer require aligent/orocommerce-graphql-bundle
   ```
3. Clear your cache
   ```shell
      bin/console cache:clear --env=dev
   ```
4. In development mode head to `/graphiql` to access an interactive graphql UI to make GrapqhQL Queries

## Example Queries
```
query {
  product (id: 1) {
    sku,
    id,
    type,
    inventoryStatus,
    organization {
      id
    },
    messages {
      type
      text
    },
    relatedProduct {
      id
    },
    prices {
      price {
      	value
      },
      unit,
      quantity
    }
  },
  
  isUpcoming: productSearch(isUpcoming:true) {
    totalRecords,
    results {
      name,
      cplPrice
    }
  },
  
  sets: productSearch(filters: {name: "text.primary_unit", value: "set"}) {
    totalRecords,
    results {
      name,
      cplPrice
    }
  },
  
  landingPage(id: 1) {
    content,
    titles {
      string
      text
    }
  }
}
```

## Example Mutations

Login Mutation:
Generates a WSSE authentication token using like Oro's existing `/login` API. See documentation [here](https://doc.oroinc.com/api/authentication/wsse/#header-generation) on how to generate an auth header.
```
mutation ($generateTokenInput: GenerateCustomerTokenInput) {
  generateCustomerToken (input: $generateTokenInput) {
    token
  }
}
```

Query Variables:
```
{
  "generateTokenInput": {
    "email": "<EMAIL>",
  	"password": "<PASSWORD>"
  }
}
```

Add to shopping list Mutation:
Adds a product to the shopping list (Shopping list must already exist, and authenticated user must have access to it).
```
mutation ($addToShoppingListInput: AddToShoppingListInput) {
  addToShoppingList(input: $addToShoppingListInput) {
    success
  }
}
```

Query Variables:
```
{
  "addToShoppingListInput": {
    "sku": "2JD29",
    "shoppingListId": 5,
    "quantity": 2.0,
    "unit": "item"
  }
}
```

## Roadmap / ToDo List
- [ ] Investigate alternative forms of authentication e.g. OroCommerce's OAuth Bundle
- [ ] Investigate using Oro's existing `frontend_api.yml` to generate GraphQL types instead of creating them manually
- [ ] Investigate disabling Oro's cookies for graphql requests
- [ ] Determine how to handle twig functions in Landing Pages / CMS Blocks
- [ ] Split out Product and Product Search resolvers
- [ ] General Code clean-up
- [ ] Tests, Tests, Tests
- [ ] Add more queries and mutations for more storefront functionality 
