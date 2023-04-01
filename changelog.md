# 💫 LqGrAphi - CHANGELOG

## [1.0.0] - 2023-04-01

### Added
- **BREAKING:** New type loading system
  - More info in [README.md](README.md)
- New caching system
    - All requests are cached and next time are resolved only using cache which can bring up to 50% lower response times

### Changed
- **BREAKING:** Resolvers signature now must accept $resolveInfo as ResolveInfo|array
- **BREAKING:** Config *types* now accepts list of namespaces instead of list of types for *outputs* and *inputs*
- Config namespaces changes
  - **BREAKING:** Config *resolversNamespace* is now *resolvers* and accepts list of namespaces instead of single namespace
  - **BREAKING:** Config *queryAndMutationsNamespace* is now *queriesAndMutations* and accepts list of namespaces instead of single namespace
  - This change enables you to use multiple namespaces for resolvers, queries and mutations, and you can directly use namespaces from packages, so you don't have to generate empty classes.

### Removed
- **BREAKING:** Config *types* now does not accept list of crud types

### Deprecated

### Fixed
- Relations processing
