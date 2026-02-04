# Changelog

All notable changes to `fiberflow` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Initial alpha release
- Core Fiber-based worker implementation
- Container sandboxing mechanism
- AsyncHttp facade for non-blocking HTTP requests
- Basic TUI dashboard
- Support for Redis queue driver
- Documentation and examples

## [0.1.0] - TBD

### Added

- Initial project structure
- Core architecture design
- Development environment setup
- Testing framework configuration
- CI/CD pipeline with GitHub Actions
- Comprehensive documentation

### Changed

- N/A

### Deprecated

- N/A

### Removed

- N/A

### Fixed

- N/A

### Security

- N/A

---

## Release Guidelines

### Version Numbers

We follow Semantic Versioning (SemVer):

- **MAJOR** version when you make incompatible API changes
- **MINOR** version when you add functionality in a backwards compatible manner
- **PATCH** version when you make backwards compatible bug fixes

### Pre-release Versions

- **Alpha** (0.x.x): Early development, API may change significantly
- **Beta** (0.x.x-beta): Feature complete, API stabilizing, testing phase
- **RC** (x.x.x-rc.x): Release candidate, final testing before stable release

### Release Process

1. Update CHANGELOG.md with all changes
2. Update version in composer.json
3. Create a git tag: `git tag -a v1.0.0 -m "Release v1.0.0"`
4. Push tag: `git push origin v1.0.0`
5. GitHub Actions will automatically publish to Packagist
6. Create GitHub Release with changelog notes

---

## Changelog Categories

### Added
For new features.

### Changed
For changes in existing functionality.

### Deprecated
For soon-to-be removed features.

### Removed
For now removed features.

### Fixed
For any bug fixes.

### Security
In case of vulnerabilities.

---

[Unreleased]: https://github.com/fiberflow/fiberflow/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/fiberflow/fiberflow/releases/tag/v0.1.0

