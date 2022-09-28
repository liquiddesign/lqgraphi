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

## TODO
- Create/Update process mutations
- Fix custom scalars validator -JSON, etc
- Check HTTP headers for mutation selection
- Persisted queries with Redis/KeyDB
- Security - guards, login
- Write tests
- Query batching with dataloader