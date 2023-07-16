<?php

/**
 * Simple SEO class for WordPress to create page metatags:
 * title, description, robots, keywords, Open Graph.
 *
 * IMPORTANT! Since version 1.7.0 robots code logic was chenged. Changed your code after update!
 * IMPORTANT! Since version 1.8.0 title code logic was chenged. Changed your code after update!
 *
 * @see https://github.com/doiftrue/Kama_SEO_Tags
 * @requires PHP 7.1+
 * @requires WP 4.4+
 *
 * @author Kama
 *
 * @version 1.9.15
 */
class Kama_SEO_Tags {

	use Kama_SEO_Tags__og_meta;

	public static function init(): void {

		// force WP document_title function to run
		add_theme_support( 'title-tag' );
		add_filter( 'pre_get_document_title', [ __CLASS__, 'meta_title' ], 1 );

		add_action( 'wp_head', [ __CLASS__, 'meta_description' ], 1 );
		add_action( 'wp_head', [ __CLASS__, 'meta_keywords' ], 1 );
		add_action( 'wp_head', [ __CLASS__, 'og_meta' ], 1 );

		// WP 5.7+
		add_filter( 'wp_robots', [ __CLASS__, 'wp_robots_callback' ], 11 );
	}

	private static function get_title_l10n(): array {

		$locale = get_locale();

		if( 'ru_RU' === $locale ){
			$l10n = [
				'404'     => 'Ошибка 404: такой страницы не существует',
				'search'  => 'Результаты поиска по запросу: %s',
				'compage' => 'Комментарии %s',
				'author'  => 'Статьи автора: %s',
				'paged'   => 'Страница %d',
				'year'    => 'Архив за {year} год',                // Архив за 2023 год
				'month'   => 'Архив за {month} {year} года',       // Архив за январь 2023 года
				'day'     => "Архив за {day} {month} {year} года", // Архив за 23 января 2023 года
			];
		}
		else{
			$l10n = [
				'404'     => 'Error 404: there is no such page',
				'search'  => 'Search results by query: %s',
				'compage' => 'Comments %s',
				'author'  => 'Articles of the author: %s',
				'paged'   => 'Page %d',
				'year'    => 'Archive for {year} year',           // Архив за 2023 год
				'month'   => 'Archive for {month} {year}',        // Архив за январь 2023 года
				'day'     => 'Archive for {month} {day}, {year}', // Archive for January 23, 2023
			];
		}

		/**
		 * Allowes to change translations strings.
		 *
		 * @param array $l10n
		 */
		return apply_filters( 'kama_meta_title_l10n', $l10n ) + $l10n;
	}

	/**
	 * @return array{ 0:WP_Post, 1:WP_Term }
	 */
	public static function get_queried_objects(): array {
		$qo = get_queried_object();

		$post = isset( $qo->post_type ) ? $qo : null;
		$term = isset( $qo->term_id ) ? $qo : null;

		return [ $post, $term ];
	}

	/**
	 * Generate string to show as document title.
	 *
	 * For posts and taxonomies specific title can be specified as metadata with name `title`.	 *
	 *
	 * @param string $title `pre_get_document_title` passed value.
	 *
	 * @return string
	 */
	public static function meta_title( $title = '' ){

		// support for `pre_get_document_title` hook.
		if( $title ){
			return $title;
		}

		static $cache;
		if( $cache ){
			return $cache;
		}

		[ $post, $term ] = self::get_queried_objects();

		$l10n = self::get_title_l10n();

		$parts = [
			'prev'  => '', // before title (post comment page)
			'title' => '',
			'page'  => '', // parination
			'after' => '', // handles wp_get_document_title() `tagline` and `site` elements.
		];

		// 404
		if( is_404() ){
			$parts['title'] = $l10n['404'];
		}
		// search
		elseif( is_search() ){
			$parts['title'] = sprintf( $l10n['search'], get_query_var( 's' ) );
		}
		// front_page
		elseif( is_front_page() ){

			if( is_page() ){
				$parts['title'] = get_post_meta( $post->ID, 'title', true );
			}

			if( ! $parts['title'] ) {
				$parts['title'] = get_bloginfo( 'name', 'display' );
				$parts['after'] = '{{description}}';
			}
		}
		// singular
		elseif(
			$post
	        || ( is_home() && ! is_front_page() )
	        || ( is_page() && ! is_front_page() )
		){
			$parts['title'] = get_post_meta( $post->ID, 'title', true );

			/**
			 * Allow to set meta title for singular type page, before the default title will be taken.
			 *
			 * @param string  $title
			 * @param WP_Post $post
			 */
			$parts['title'] = apply_filters( 'kama_meta_title_singular', $parts['title'], $post );

			if( ! $parts['title'] ){
				$parts['title'] = single_post_title( '', 0 );
			}

			if( $cpage = get_query_var( 'cpage' ) ){
				$parts['prev'] = sprintf( $l10n['compage'], $cpage );
			}
		}
		// post_type_archive
		elseif( is_post_type_archive() ){
			$parts['title'] = post_type_archive_title('', 0 );
			$parts['after'] = '{{blog_name}}';
		}
		// any taxonomy
		elseif( $term ){
			$parts['title'] = get_term_meta( $term->term_id, 'title', true );

			if( ! $parts['title'] ){
				$parts['title'] = single_term_title( '', 0 );

				if( is_tax() ){
					$parts['prev'] = get_taxonomy( $term->taxonomy )->labels->name;
				}
			}

			$parts['after'] = '{{blog_name}}';
		}
		// author posts archive
		elseif( is_author() ){
			$parts['title'] = sprintf( $l10n['author'], get_queried_object()->display_name );
			$parts['after'] = '{{blog_name}}';
		}
		// date archive
		elseif( is_day() || is_month() || is_year() ){
			/** @var WP_Locale $wp_locale */
			global $wp_locale;

			$y_num = get_query_var( 'year' );
			$m_num = get_query_var( 'monthnum' );
			$d_num = get_query_var( 'day' );

			if( is_year() ){
				// 2023 год
				$parts['title'] = strtr( $l10n['year'], [ '{year}' => $y_num ] );
			}
			elseif( is_month() ){
				// январь 2023 года
				$parts['title'] = strtr( $l10n['month'], [
					'{year}'  => $y_num,
					'{month}' => $wp_locale->get_month( $m_num ),
				] );
			}
			elseif( is_day() ){
				// 23 января 2023 года
				$parts['title'] = strtr( $l10n['month'], [
					'{year}'  => $y_num,
					'{month}' => $wp_locale->month_genitive[ zeroise( $m_num, 2 ) ],
					'{day}'   => $d_num,
				] );
			}

			$parts['after'] = '{{blog_name}}';
		}
		// other archives
		else {
			$parts['title'] = get_the_archive_title();
			$parts['after'] = '{{blog_name}}';
		}

		// pagination
		$pagenum = get_query_var( 'paged' ) ?: get_query_var( 'page' );
		if( $pagenum && ! is_404() ){
			$parts['page'] = sprintf( $l10n['paged'], $pagenum );
		}

		/**
		 * Allows to change parts of the document title.
		 *
		 * @param array $parts Title parts. It then will be joined.
		 * @param array $l10n  Localisation strings.
		 */
		$parts = apply_filters( 'kama_meta_title_parts', $parts, $l10n );

		/** This filter is documented in wp-includes/general-template.php */
		$parts = apply_filters( 'document_title_parts', $parts );

		// extra support for `document_title_parts` hook. Do the same as WP does.
		$as_wp_after = $parts['tagline'] ?? $parts['site'] ?? '';
		if( $as_wp_after ){
			unset( $parts['tagline'], $parts['site'] );
			$parts['after'] = $as_wp_after;
		}

		// handle placeholders
		if( '{{blog_name}}' === $parts['after'] ){
			$parts['after'] = get_bloginfo( 'name', 'display' );
		}
		elseif( '{{description}}' === $parts['after'] ){
			$parts['after'] = get_bloginfo( 'description', 'display' );
		}

		/** This filter is documented in wp-includes/general-template.php */
		$sep = apply_filters( 'document_title_separator', ' – ' );

		$title = implode( ' ' . trim( $sep ) . ' ', array_filter( $parts ) );

		//$title = wptexturize( $title );
		//$title = convert_chars( $title );
		$title = capital_P_dangit( $title );
		$title = esc_html( $title );

		return $cache = $title;
	}

	/**
	 * For `wp_head` hook.
	 */
	public static function meta_description(): void {
		echo self::get_meta_description();
	}

	/**
	 * Gets `description` meta-tag: `<meta name="description" content="..." />`.
	 *
	 * Use post-meta with `description` key to set description for any posts.
	 * It also work for page setted as front page.
	 *
	 * Use term-meta with `meta_description` key to set description for any terms.
	 * Or use default `description` field of a term.
	 *
	 * @return string Metatag or empty string if not description found.
	 */
	public static function get_meta_description(): string {

		static $cache = null;
		if( null !== $cache ){
			return $cache;
		}

		[ $post, $term ] = self::get_queried_objects();

		$desc = '';
		$need_cut = true;

		// front
		if( is_front_page() ){

			// когда для главной установлена страница
			if( is_page() ){
				$desc = get_post_meta( $post->ID, 'description', true );
				$need_cut = false;
			}

			if( ! $desc ){

				/**
				 * Allow to change front_page meta description.
				 *
				 * @param string $home_description
				 */
				$desc = apply_filters( 'home_meta_description', get_bloginfo( 'description', 'display' ) );
			}
		}
		// any post
		elseif( $post ){

			if( $desc = get_post_meta( $post->ID, 'description', true ) ){
				$need_cut = false;
			}

			/**
			 * Allow to set meta description for single post, before the default description will be taken.
			 *
			 * @param string  $title
			 * @param WP_Post $post
			 */
			$desc = apply_filters( 'kama_meta_description_singular', $desc, $post );

			if( ! $desc ){
				$desc = $post->post_excerpt ?: $post->post_content;
			}

			$desc = trim( strip_tags( $desc ) );
		}
		// any term (taxonomy element)
		elseif( $term ){

			$desc = get_term_meta( $term->term_id, 'meta_description', true );

			if( ! $desc ){
				$desc = get_term_meta( $term->term_id, 'description', true );
			}

			$need_cut = false;
			if( ! $desc && $term->description ){
				$desc = strip_tags( $term->description );
				$need_cut = true;
			}
		}

		$desc = str_replace( [ "\n", "\r" ], ' ', $desc );

		// remove shortcodes, but leave markdown [foo](URL)
		$desc = preg_replace( '~\[[^\]]+\](?!\()~', '', $desc );

		/**
		 * Allow to specify is the meta description need to be cutted.
		 *
		 * @param bool $need_cut
		 */
		$need_cut = apply_filters( 'kama_meta_description__need_cut', $need_cut );

		/**
		 * Allow set max length of the meta description.
		 *
		 * @param int $maxchar
		 */
		$maxchar = apply_filters( 'kama_meta_description__maxchar', 260 );

		$origin_desc = $desc;

		if( $need_cut ){
			$char = mb_strlen( $desc );

			if( $char > $maxchar ){
				$desc = mb_substr( $desc, 0, $maxchar );
				$words = explode( ' ', $desc );
				$maxwords = count( $words ) - 1; // remove last word, it incomplete in 90% cases
				$desc = implode( ' ', array_slice( $words, 0, $maxwords ) ) . ' ...';
			}
		}

		/**
		 * Allow change or set the meta description.
		 *
		 * @param string $desc        Current description.
		 * @param string $origin_desc Description before cut.
		 * @param bool   $need_cut    Is need to cut?
		 * @param int    $maxchar     How many characters leave after cut.
		 */
		$desc = apply_filters( 'kama_meta_description', $desc, $origin_desc, $need_cut, $maxchar );

		// remove multi-space
		$desc = preg_replace( '/\s+/s', ' ', $desc );

		if( $desc ){
			$desc = sprintf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( trim( $desc ) ) );
		}

		return $cache = $desc;
	}

	/**
	 * Wrpper for WP Robots API introduced in WP 5.7+.
	 *
	 * Set post/term mete with `robots` key and values separeted with comma. Eg: `noindex,nofollow`.
	 *
	 * Must be used on `wp_robots` hook.
	 *
	 * @param array $robots
	 */
	public static function wp_robots_callback( $robots ){

		[ $post, $term ] = self::get_queried_objects();

		if( $post ){
			$robots_str = get_post_meta( $post->ID, 'robots', true );
		}
		elseif( $term ){
			$robots_str = get_term_meta( $term->term_id, 'robots', true );
		}

		if( empty( $robots_str ) ){
			return $robots;
		}

		// split by spece or comma
		$robots_parts = preg_split( '/(?<!:)[\s,]+/', $robots_str, -1, PREG_SPLIT_NO_EMPTY );

		foreach( $robots_parts as $directive ){

			// for derectives like `max-snippet:2`
			if( strpos( $directive, ':' ) ){
				[ $key, $value ] = explode( ':', $directive );
				$robots[ $key ] = $value;
			}
			else {
				$robots[ $directive ] = true;
			}
		}

		if( ! empty( $robots['none'] ) || ! empty( $robots['noindex'] ) ){
			unset( $robots['max-image-preview'] );
		}

		return $robots;
	}

	/**
	 * Generate `<meta name="keywords">` meta-tag fore <head> part of the page.
	 *
	 * To set Your own keywords for post, create meta-field with key `keywords`
	 * and set the keyword to the value.
	 *
	 * Default keyword for a post generates from post tags nemes and categories names.
	 * If the `keywords` meta-field is not specified.
	 *
	 * You can specify the keywords for the any taxonomy element (term) using shortcode
	 * `[keywords=word1, word2, word3]` in the description field.
	 *
	 * @param string $home_keywords Keywords for home page. Ex: 'word1, word2, word3'
	 * @param string $def_keywords  сквозные ключевые слова - укажем и они будут прибавляться
	 *                              к остальным на всех страницах.
	 */
	public static function meta_keywords( $home_keywords = '', $def_keywords = '' ){
		$out = [];

		[ $post, $term ] = self::get_queried_objects();

		if( is_front_page() ){
			$out[] = $home_keywords;
		}
		elseif( is_singular() ){

			$meta_keywords = get_post_meta( $post->ID, 'keywords', true );

			if( $meta_keywords ){
				$out[] = $meta_keywords;
			}
			elseif( $post->post_type === 'post' ){

				$res = wp_get_object_terms( $post->ID, [ 'post_tag', 'category' ], [ 'orderby' => 'none' ] );

				if( $res && ! is_wp_error( $res ) ){
					foreach( $res as $tag ){
						$out[] = $tag->name;
					}
				}
			}

		}
		elseif( $term ){
			$out[] = get_term_meta( $term->term_id, 'keywords', true );
		}

		if( $def_keywords ){
			$out[] = $def_keywords;
		}

		$out = implode( ', ', $out );

		/**
		 * Allow to change resulting string of meta_keywords() method.
		 *
		 * @param string $out
		 */
		$out = apply_filters( 'kama_meta_keywords', $out );

		echo $out
			? '<meta name="keywords" content="'. esc_attr( $out ) .'" />' . "\n"
			: '';
	}

}

trait Kama_SEO_Tags__og_meta {

	/**
	 * Displays Open Graph, twitter data in `<head>`.
	 *
	 * Designed to use in `wp_head` hook.
	 *
	 * @See Documentation: http://ogp.me/
	 */
	public static function og_meta(): void {

		[ $post, $term ] = self::get_queried_objects();

		$title = self::meta_title();
		$desc = preg_replace( '/^.+content="([^"]*)".*$/s', '$1', self::get_meta_description() );

		// base
		$els = [
			'og:locale'      => get_locale(),
			'og:site_name'   => get_bloginfo('name'),
			'og:title'       => $title,
			'og:description' => $desc,
			'og:type'        => is_singular() ? 'article' : 'object',
		];

		self::set_og_url( $post, $term, $els );

		self::set_og_image( $post, $term, $els );

		self::set_og_article_section( $post, $term, $els );


		// set `twitter:___`

		$els['twitter:card'] = 'summary';
		$els['twitter:title'] = $els['og:title'];
		$els['twitter:description'] = $els['og:description'];
		if( ! empty( $els['og:image[1]'] ) ){
			$els['twitter:image'] = $els['og:image[1]'];
		}

		/**
		 * Allows change values of og / twitter meta properties.
		 *
		 * @param array $els
		 */
		$els = apply_filters( 'kama_og_meta_elements_values', $els );
		$els = array_filter( $els );
		ksort( $els );

		// make <meta> tags
		$metas = [];
		foreach( $els as $key => $val ){

			// og:image[1] > og:image  ||  og:image[1]:width > og:image:width
			$fixed_key = preg_replace( '/\[\d\]/', '', $key );

			if( 0 === strpos( $key, 'twitter:' ) ){
				$metas[ $key ] = sprintf( '<meta name="%s" content="%s" />', $fixed_key, esc_attr( $val ) );
			}
			else{
				$metas[ $key ] = sprintf( '<meta property="%s" content="%s" />', $fixed_key, esc_attr( $val ) );
			}
		}

		/**
		 * Filter resulting properties. Allows to add or remove any og/twitter properties.
		 *
		 * @param array  $els
		 */
		$metas = apply_filters( 'kama_og_meta_elements', $metas, $els );

		echo "\n\n". implode( "\n", $metas ) ."\n\n";
	}

	/**
	 * @param WP_Post $post
	 * @param WP_Term $term
	 * @param array   $els
	 */
	private static function set_og_url( $post, $term, & $els ): void {
		if( ! $post && ! $term ){
			return;
		}

		$post && $url = get_permalink( $post );
		$term && $url = get_term_link( $term );

		// without protocol only: //domain.com/path
		if( 0 === strpos( $url, '//' ) ){
			$els['og:url'] = set_url_scheme( $url );
		}
		// without domain
		elseif( '/' === $url[0] ){
			$parts = wp_parse_url( $url );
			$els['og:url'] = home_url( $parts['path'] ) . ( isset( $parts['query'] ) ? "?{$parts['query']}" : '' );
		}
		else{
			$els['og:url'] = $url;
		}
	}

	/**
	 * @param WP_Post $post
	 * @param WP_Term $term
	 * @param array   $els
	 */
	private static function set_og_image( $post, $term, & $els ): void {
		/**
		 * Allow to change `og:image` `og:image:width` `og:image:height` values.
		 *
		 * @param int|string|array|WP_Post  $image_data  WP attachment ID or Image URL or Array [ image_url, width, height ].
		 */
		$image = apply_filters( 'pre_kama_og_meta_image', null );

		if( ! $image ){
			/**
			 * Allows to turn off the image search in post content.
			 *
			 * @param bool $is_on
			 */
			$is_find_in_content = apply_filters( 'kama_og_meta_thumb_id_find_in_content', true );

			if( $post ){
				$image = get_post_thumbnail_id( $post );

				if( ! $image && $is_find_in_content ){

					$image = self::get_attach_image_id_from_text( $post->post_content );

					// первое вложение поста
					if( ! $image ) {

						$attach = get_children( [
							'numberposts'    => 1,
							'post_mime_type' => 'image',
							'post_type'      => 'attachment',
							'post_parent'    => $post->ID,
						] );

						if( $attach && $attach = array_shift( $attach ) ){
							$image = $attach->ID;
						}
					}
				}
			}

			if( $term && $is_find_in_content ){

				$image = get_term_meta( $term->term_id, '_thumbnail_id', true );

				if( ! $image ){
					$image = self::get_attach_image_id_from_text( $term->description );
				}
			}

			/**
			 * Allow to set `og:image` `og:image:width` `og:image:height` values if it's not.
			 *
			 * @param int|string|array|WP_Post  $image  WP attachment ID or Image URL or [ image_url, width, height ] array.
			 */
			$image = apply_filters( 'kama_og_meta_image', $image );
			$image = apply_filters( 'kama_og_meta_thumb_id', $image ); // for backcompat
		}

		if( ! $image ){
			return;
		}

		if(
			$image instanceof WP_Post
			||
			( is_numeric( $image ) && $image = get_post( $image ) )
		){

			[
				$els['og:image[1]'],
				$els['og:image[1]:width'],
				$els['og:image[1]:height'],
				$els['og:image[1]:alt'],
				$els['og:image[1]:type']
			] = array_merge(
				array_slice( image_downsize( $image->ID, 'full' ), 0, 3 ),
				[ $image->post_excerpt, $image->post_mime_type ]
			);

			if( ! $els['og:image[1]:alt'] ){
				unset( $els['og:image[1]:alt'] );
			}

			// thumbnail size
			[
				$els['og:image[2]'],
				$els['og:image[2]:width'],
				$els['og:image[2]:height']
			] = array_slice( image_downsize( $image->ID, 'thumbnail' ), 0, 3 );
		}
		elseif( is_array( $image ) ){
			[
				$els['og:image[1]'],
				$els['og:image[1]:width'],
				$els['og:image[1]:height']
			] = $image;
		}
		else{
			$els['og:image[1]'] = $image;
		}
	}

	/**
	 * @param WP_Post $post
	 * @param WP_Term $term
	 * @param array   $els
	 */
	private static function set_og_article_section( $post, $term, & $els ): void {
		/**
		 * Allow to disable `article:section` property.
		 *
		 * @param bool $is_on
		 */
		if( ! apply_filters( 'kama_og_meta_show_article_section', true ) || ! is_singular() ){
			return;
		}

		$post_taxname = get_object_taxonomies( $post->post_type );
		if( $post_taxname ){
			$post_terms = get_the_terms( $post, reset( $post_taxname ) );

			if( $post_terms && $post_term = array_shift( $post_terms ) ){
				$els['article:section'] = $post_term->name;
			}
		}
	}

	/**
	 * Tryes to find WP attachment image ID from passed content.
	 * Passed content checks with regex and first image name is retrieved,
	 * then the ID is searched in DB by found image name.
	 */
	private static function get_attach_image_id_from_text( string $text ): int {

		if(
			preg_match( '/<img +src *= *[\'"]([^\'"]+)[\'"]/', $text, $mm )
			&&
			( '/' === $mm[1][0] || strpos($mm[1], $_SERVER['HTTP_HOST']) )
		){
			$name = basename( $mm[1] );
			$name = preg_replace( '~-[0-9]+x[0-9]+(?=\..{2,6})~', '', $name ); // удалим размер (-80x80)
			$name = preg_replace( '~\.[^.]+$~', '', $name );                   // удалим расширение
			$name = sanitize_title( sanitize_file_name( $name ) );

			global $wpdb;
			$attach_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'attachment'", $name
			) );

			return (int) $attach_id;
		}

		return 0;
	}

}
