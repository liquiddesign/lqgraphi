# 💫 LqGrAphi - CHANGELOG

## [1.0.0-beta.1] - 2023-04-01

### Added
- New caching system
    - All requests are cached and next time are resolved only using cache which can bring up to 50% lower response times

### Changed
- **BREAKING:** Resolvers signature now must accept $resolveInfo as ResolveInfo|array

### Removed

### Deprecated

### Fixed
- Relations processing
