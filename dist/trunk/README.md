# YAML Importer #
**Tags:** import yaml bulk  
**Donate link:** https://github.com/croox/donate  
**Contributors:** [croox](https://profiles.wordpress.org/croox)  
**Tested up to:** 5.2.0  
**Requires at least:** 5.0.0  
**Requires PHP:** 7.0.0  
**Stable tag:** trunk  
**License:** GNU General Public License v2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Import Posts from YAML


## Description ##


## Installation ##
Upload and install this Theme the same way you'd install any other Theme.


## Screenshots ##


## Upgrade Notice ##



# 

## Changelog ##

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
