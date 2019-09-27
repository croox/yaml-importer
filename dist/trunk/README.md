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

Bulk import posts (of any post-type) or taxonomy-terms from YAML files.

> **Not production ready**
>
> Currently the importer does not sanitize anything before creating posts/terms.
> ... but all other features should work.

Supports:
- Multilingual import for [wpml](https://wpml.org/).
- Can create post to post connections for [wp-posts-to-posts](https://github.com/scribu/wp-posts-to-posts). Post to Users or Users to Users currently not supported.

### Usage

WordPress by default doesn't allow you to upload YAML files. The purpose of this plugin is not to overcome this limitation.
You need to have access to the server and put the YAML files into your `wp-content/yaml-importer/` directory.

Go to wp-admin and and select `Tools` -> `YAML importer` from the menu. Select a file and start the Import.
A log will be written to `wp-content/yaml-importer/import.log`.

#### YAML file syntax

??? TODO: example YAML files ??? wp_insert_post args ??? wp_insert_term args ???

### Acknowledgments

- The background processing is based on [WP Background Processing](https://github.com/deliciousbrains/wp-background-processing).
- YAML files are loaded into php using [mustangostang/spyc](https://packagist.org/packages/mustangostang/spyc)

## Installation ##
Upload and install this Theme the same way you'd install any other Theme.


## Screenshots ##


## Upgrade Notice ##




# 

## Changelog ##

## 0.1.2 - 2019-09-27
Print parsed yaml file to import.log

### Added
- Print parsed yaml file to import.log

### Changed
- Update to wp-dev-env-frame#0.7.3

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
