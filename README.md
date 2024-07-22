Kama_SEO_Tags
=============

Simple SEO class for WordPress to create page metatags:  `title`, `description`, `robots`, `keywords`, `og:____` (Open Graph).

Instalation
-----------
```php
composer require doiftrue/wp-kama-seo-tags
```

Usage
-----
Just init the class.

```php
$Kama_SEO_Tags = Kama_SEO_Tags::init();
```

### Meta-fields in the admin panel

> [!IMPORTANT]
> IMPORTANT: The code does not add meta-fields in the admin panel!

The code above only processes (outputs) the necessary SEO meta-tags - it does not add meta-fields to the post or taxonomy term editing page. Such meta-fields need to be created separately.

On the post or term editing page, you need to create a meta box with the following meta-fields:

- ``title`` - an alternative SEO title that will be used in the `<title>` meta-tag instead of the post title.
- ``description`` - description for the page. If not available, the text from the excerpt will be used, and if that is also not available, a snippet from the beginning of the post content will be used.
	- ``meta_description`` - this is how the description meta-field for terms should be named, because the `description` key is already used for taxonomies...

- ``keywords`` - keywords meta-tag. I'm not sure if it's needed in modern realities.
- ``robots`` - the specified value is output as is, for example, `noindex,nofollow`.



Issues
------
If it doesn't work, you need to make sure that the `wp_head()` function is being used in the `header.php` theme file and that it does not output the hard-coded `<title>` tag and other SEO tags.

For more details see: https://wp-kama.ru/9537


Changelog
---------

1.9.15 - 16-07-2023
- IMP: PHP Warning fix. 

1.9.14 - 28-06-2023
- NEW: `composer.json` added. And module added to Packagist.
- NEW: Default EN translations added.
- CHG: `year`, `month`, `day` keys added to localisation strings. `archive` key was removed.
- IMP: Better support for `document_title_parts` WP hook.
- IMP: Minor refactoring and improvements.

1.9.13 - 1-06-2022
- IMP: Minor improvements.

1.9.8 - 25-10-2021
- CHG: Disable function wptexturize() convert_chars() for title.
- NEW: hook `kama_meta_keywords`.

1.6 - 13-12-2020
- IMP: Paged becomes real part of implode.
- NEW: `$paged` parameter added to `kama_meta_title` hook.

1.5 - 01-12-2020
- IMP: `get_term_meta()` for meta_robots(). Simplify the code of meta_robots().
- CHG: Hook `kama_meta_robots_close` renamed to `kama_meta_robots`

1.4 - 27.11.2020
- NEW: Изменил логику `og_meta()`:
- NEW: Теперь все собирается в массив.
- NEW: Появился хук `pre_kama_og_meta_image`.
- NEW: ВАЖНО! Переименован хук `kama_og_meta_thumb_id` на `kama_og_meta_image`.
- NEW: Появился хук `kama_og_meta_elements_values`.
