== Description ==

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

== Installation ==
Upload and install this Theme the same way you'd install any other Theme.


== Screenshots ==


== Upgrade Notice ==


