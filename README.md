# 💫 LqGrAphi

GraphQL API library for Liquid Design ecosystem.

## Functions
- Auto types creation from PHP classes to TypeRegister (Storm entities)
- Autoload of Queries and Mutations
- Caching of schema
- Universal CRUD query, mutations and resolvers for generic generation and resolving
- Recursive data fetcher of Storm entities from database based on requested query (highly optimized - makes only one query per entity class - simulating dataloader)

## Recommendations

This package works great with extended packages with types for LQD packages:

- https://github.com/liquiddesign/eshop-api

## Installation

`composer require liquiddesign/lqgraphi`

## Configuration

```neon
extensions:
    typeRegister: LqGrAphi\LqGrAphiDI

typeRegister:
    resolversNamespace: EshopApi\Resolvers
    queryAndMutationsNamespace: EshopApi\Schema\Types
    types:
        output:
            customerGroup: EshopApi\Schema\Outputs\CustomerGroupOutput
            pricelist: EshopApi\Schema\Outputs\PricelistOutput
            address: EshopApi\Schema\Outputs\AddressOutput
            productGetProducts: EshopApi\Schema\Outputs\ProductGetProductsOutput
        crud:
            customer: [EshopApi\Schema\Outputs\CustomerOutput, EshopApi\Schema\Inputs\CustomerCreateInput, EshopApi\Schema\Inputs\CustomerUpdateInput]
            product: [EshopApi\Schema\Outputs\ProductOutput, EshopApi\Schema\Inputs\ProductCreateInput, EshopApi\Schema\Inputs\ProductUpdateInput]
```

## Entry point

In you entry point (probably `index.php`) you need to call handler. You just need to create container and pass it to `\LqGrAphi\Handlers\IndexHandler::handle`.

Minimal example of `index.php`:

```php
require __DIR__ . '/vendor/autoload.php';

\LqGrAphi\Handlers\IndexHandler::handle(\EshopApi\Bootstrap::boot()->createContainer());
```

### Sandbox

Apollo Sandbox is enabled by default for debug connections based on environment file.

You can permanently disable it:
```php
require __DIR__ . '/vendor/autoload.php';

\LqGrAphi\Handlers\IndexHandler::handle(\EshopApi\Bootstrap::boot()->createContainer(), false);
```

## Queries and Mutations

Location of queries and mutations is set via config `queryAndMutationsNamespace`.
Query needs to extend `\LqGrAphi\Schema\BaseQuery` and mutation `\LqGrAphi\Schema\BaseMutation`.<br><br>
These types are automatically loaded only first time when schema is created and cached.
All other requests uses cached schema, due to that script don't need to create schema for every request and performance is not decreased.
This approach has some limitations: Queries and mutations are not registered in container, so you cant use DI. All these classes will receive container as first argument.

## Types

Location of types is set via config `types`. You need to specify all used inputs and outputs here.

```neon
typeRegister:
    types:
        input:
            product: EshopApi\Schema\Inputs\ProductInput
        output:
            product: EshopApi\Schema\Outputs\ProductOutput
```

For more info visit documentation of [webonyx/graphql-php](https://webonyx.github.io/graphql-php/) library.

### ClassOutput

There is interface `\LqGrAphi\Schema\ClassOutput` with method `getClass`.
If you use it, TypeRegister will save this mapping ang when you call `getOutputType` you can simply pass class-string instead of name from config.

### ClassInput

### Relations

In outputs, relations are object or list of objects with up to 10 levels of depth.
Inputs, on the other end, have always two fields for relation.

One with suffix `ID` for single relations which takes string (or null if possible).
For many relations there is field with suffix `IDs` which is object with `add`, `remove` and `replace` fields. These fields takes list of strings.

Second, there is always fields with suffix `OBJ`, which takes directly input object and also updates it. It can also have 10 levels of depth.
For many relations there is field with suffix `OBJs` which takes list of input objects.

Due to limitations of GraphQL where you can´t have union input type, these inputs are always in UpdateInput variant which has all fields optional.
Based on ID field, object is created or only updated. This approach loses type safety for required fields when creating object, in this case error will only be caused in runtime. 

```graphql
input Object {
    fullName: String
    accountsIDs: SubObjectIDs
    accountsOBJs: [SubObjectUpdateInput]
}
```

There is interface `\LqGrAphi\Schema\ClassInput` with method `getClass`.
If you use it, TypeRegister will save this mapping ang when you call `getInputType` you can simply pass class-string instead of name from config. Also, TypeRegister will map this input in input objects to relation fields.

## Resolvers

Due to caching of whole schema creation, resolvers are isolated from schema and have to resolve request on their own.<br>
There is simple routing mechanism. GraphQL's queries and mutations names uses lower-camelCase.<br>
Name is parsed as first word in resolver class name and rest is function name.<br>
Example: `productGetMany` is parsed as `ProductResolver` and function `getMany`.

Router that parses name to resolver and function uses on-demand cache.

Signature of every resolver function must be:
```php
/**
 * @param array<mixed> $rootValue
 * @param array<mixed> $args
 * @param \LqGrAphi\GraphQLContext $context
 * @param \GraphQL\Type\Definition\ResolveInfo $resolveInfo
 * @return array<mixed>|null
 * @throws \LqGrAphi\Resolvers\Exceptions\BadRequestException
 * @throws \ReflectionException
 * @throws \StORM\Exception\GeneralException
 */
public function getMany(array $rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?array
{
    ...
}
```

Recommended way of configuration:

```neon
services:
	graphql_resolvers:
		in: %appDir%
		files: [Resolvers/*Resolver.php, Resolvers/*/*Resolver.php]
		implements:
		    - LqGrAphi\Resolvers\BaseResolver
```

### CRUD

You can write your types and queries by yourself, but most of the time you just want to take existing entity and make crud operations for it.

At that point comes `\LqGrAphi\Schema\CrudQuery` and `\LqGrAphi\Schema\CrudMutation`.

First, create query class

```php
class CustomerQuery extends CrudQuery
{
	public function getClass(): string
	{
		return Customer::class;
	}
}
```

then create output, create and update types with use of helpers:

```php
class CustomerOutput extends BaseOutput
{
	public function __construct(TypeRegister $typeRegister)
	{
		$config = [
			'fields' => $typeRegister->createOutputFieldsFromClass(Customer::class, exclude: ['account']),
		];

		parent::__construct($config);
	}
}
```

```php
class CustomerCreateInput extends BaseInput
{
	public function __construct(TypeRegister $typeRegister)
	{
		$config = [
			'fields' => $typeRegister->createInputFieldsFromClass(Customer::class, includeId: false),
		];

		parent::__construct($config);
	}
}

```

```php
class CustomerUpdateInput extends BaseInput
{
	public function __construct(TypeRegister $typeRegister)
	{
		$config = [
			'fields' => $typeRegister->createInputFieldsFromClass(Customer::class, forceAllOptional: true),
		];

		parent::__construct($config);
	}
}
```

 register types in config as crud types
```neon
typeRegister:
    types:
        crud:
            customer: [EshopApi\Schema\Outputs\CustomerOutput, EshopApi\Schema\Inputs\CustomerCreateInput, EshopApi\Schema\Inputs\CustomerUpdateInput]
```

and lastly create resolver

```php
class CustomerResolver extends CrudResolver
{
	public function getClass(): string
	{
		return Customer::class;
	}
}
```
and that's all, you can query one, many or collection and mutate create, update and delete operations.

#### Helpers for CRUD

To help working with API there is some automatic improvements

- All array outputs are encapsulated in object with key *data* and *onPageCount*
- There is universal input objects for sorting, paging and filtering

#### Filtering

Filters are input fields of type JSON, which are parsed to repository allowed *filter* functions.

#### Fetch result

There is universal fetch function. You just need to pass collection with ResolveInfo. It will take care of retrieving only data you asked in most efficient way.

### Language mutations
If you need to use different language mutation, you can use HTTP header "Accept-Language".
System will set detected language (if supported) as primary language for all SQL queries.
If no language in "Accept-Language" header is supported, then primary language from settings is used.

## Roadmap
- Persisted queries with Redis/KeyDB
- Security - guards, login
- Automatic testing
- Query batching with dataloader