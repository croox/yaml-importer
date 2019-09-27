# Yaml Importer

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## 0.1.1 - 2019-09-27
Update readme

### Changed
- Update readme.txt
- Move logging to function `yaim_log`

## 0.1.0 - 2019-09-25
Background Processing

### Added
- Handle `meta_input` field for Term imports
- Use `wpautop`for certain fields. Fields are filtered by "yaim_{$type}_autop_keys" filter
- Handle p2p connections for post import
- Use a5hleyrich/wp-background-processing and process all imports in background

### Changed
- More stable log

### Fixed
- Multilang values for directly nested attributes. eg `meta_input`. Also works if some directly nested attributes have translations, and others not.

## 0.0.2 - 2019-09-23
Taxonomy Term Import

### Added
- Taxonomy Term Import
- Admin notices

## 0.0.1 - 2019-09-22
Imports array of posts, supports wpml

### Added
- cmb2/cmb2
- mustangostang/spyc
- Imports array of posts.
- Works with wpml
