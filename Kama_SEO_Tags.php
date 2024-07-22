<?php

/**
 * Simple SEO class for WordPress to create page metatags:
 * title, description, robots, keywords, Open Graph.
 *
 * IMPORTANT! Since version 1.7.0 robots code logic was chenged. Changed your code after update!
 * IMPORTANT! Since version 1.8.0 title code logic was chenged. Changed your code after update!
 *
 * @see https://github.com/doiftrue/Kama_SEO_Tags
 * @requires PHP 7.1
 * @requires WP 5.7
 *
 * @author Kama
 *
 * @version 2.1.0
 */
class Kama_SEO_Tags {

	public $title = '';
	public $description = '';
	public $keywords = '';

	public static function init(): self {
		static $class;
		if( $class ){
			return $class;
		}

		$class = new self();

		// force WP document_title function to run
		add_theme_support( 'title-tag' );
		add_filter( 'pre_get_document_title', [ $class, 'get_meta_title' ], 1 );

		add_action( 'wp_head', [ $class, 'echo_meta_description' ], 1 );
		add_action( 'wp_head', [ $class, 'echo_meta_keywords' ], 1 );

		// WP 5.7+
		add_filter( 'wp_robots', [ $class, 'wp_robots_callback' ], 11 );

		$og_meta = new Kama_SEO_Tags__og_meta( $class );
		add_action( 'wp_head', [ $og_meta, 'echo_og_meta' ], 1 ); // !IMPORTANT at the end

		return $class;
	}

	/**
	 * Use ::init() result to get access to this object.
	 */
	private function __construct() {
	}

	private function get_title_l10n(): array {

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
	 * @return array{ 0:null|WP_Post, 1:null|WP_Term }
	 */
	public function get_queried_objects(): array {
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
	public function get_meta_title( $title = '' ) {

		// support for `pre_get_document_title` hook.
		if( $title ){
			return $title;
		}

		[ $post, $term ] = $this->get_queried_objects();

		$l10n = $this->get_title_l10n();

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

		$this->title = $title;

		return $this->title;
	}

	/**
	 * For `wp_head` hook.
	 */
	public function echo_meta_description(): void {
		$this->set_meta_description();

		if( $this->description ){
			echo sprintf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $this->description ) );
		}
	}

	/**
	 * For `wp_head` hook.
	 */
	public function echo_meta_keywords(): void {
		$this->set_meta_keywords();

		if( $this->keywords ){
			echo sprintf( "<meta name=\"keywords\" content=\"%s\" />\n", esc_attr( $this->keywords ) );
		}
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
	public function set_meta_description(): void {
		[ $post, $term ] = $this->get_queried_objects();

		$desc = '';
		$need_cut = true;

		// home page
		if( is_front_page() ){

			// when static page is set for home_page
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

		$this->description = trim( $desc );
	}

	/**
	 * Wrapper for WP Robots API introduced in WP 5.7.
	 *
	 * Set post/term mete with `robots` key and values separeted with comma. Eg: `noindex,nofollow`.
	 *
	 * Must be used on `wp_robots` hook.
	 *
	 * @param array $robots
	 */
	public function wp_robots_callback( $robots ) {

		[ $post, $term ] = $this->get_queried_objects();

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
	public function set_meta_keywords( $home_keywords = '', ?string $def_keywords = '' ): void {
		[ $post, $term ] = $this->get_queried_objects();

		$keywords = [];

		if( is_front_page() ){
			$keywords[] = $home_keywords;
		}
		elseif( is_singular() ){

			$meta_keywords = get_post_meta( $post->ID, 'keywords', true );

			if( $meta_keywords ){
				$keywords[] = $meta_keywords;
			}
			elseif( $post->post_type === 'post' ){

				$res = wp_get_object_terms( $post->ID, [ 'post_tag', 'category' ], [ 'orderby' => 'none' ] );

				if( $res && ! is_wp_error( $res ) ){
					foreach( $res as $tag ){
						$keywords[] = $tag->name;
					}
				}
			}

		}
		elseif( $term ){
			$keywords[] = get_term_meta( $term->term_id, 'keywords', true );
		}

		if( $def_keywords ){
			$keywords[] = $def_keywords;
		}

		$keywords = implode( ', ', $keywords );

		/**
		 * Allow to change resulting string of meta_keywords() method.
		 *
		 * @param string $keywords
		 */
		$this->keywords = apply_filters( 'kama_meta_keywords', $keywords );
	}

}

/**
 * @see http://ogp.me/ (Open Graph protocol documentation)
 */
class Kama_SEO_Tags__og_meta {

	/** @var  Kama_SEO_Tags*/
	private $kst;

	public function __construct( Kama_SEO_Tags $kst ){
		$this->kst = $kst;
	}

	/**
	 * Displays Open Graph, twitter data in `<head>`.
	 * Designed to use in `wp_head` hook.
	 */
	public function echo_og_meta(): void {
		$metas = $this->get_og_meta_data();

		$meta_tags = [];
		foreach( $metas as $key => $val ){
			// og:image[1] > og:image  ||  og:image[1]:width > og:image:width
			$fixed_key = preg_replace( '/\[\d\]/', '', $key );

			if( 0 === strpos( $key, 'twitter:' ) ){
				$meta_tags[ $key ] = sprintf( '<meta name="%s" content="%s" />', $fixed_key, esc_attr( $val ) );
			}
			else{
				$meta_tags[ $key ] = sprintf( '<meta property="%s" content="%s" />', $fixed_key, esc_attr( $val ) );
			}
		}

		/**
		 * Filter resulting properties. Allows to add or remove any og/twitter properties.
		 *
		 * @param array $metas og meta elements array.
		 */
		$meta_tags = apply_filters( 'kama_og_meta_elements', $meta_tags, $metas );

		echo "\n\n". implode( "\n", $meta_tags ) ."\n\n";
	}

	public function get_og_meta_data(): array {
		[ $post, $term ] = $this->kst->get_queried_objects();

		$els = [];
		$this->fill_basic( $post, $term, $els );
		$this->fill_type( $post, $term, $els );
		$this->fill_og_url( $post, $term, $els );
		$this->fill_og_image( $post, $term, $els );
		$this->fill_twitter( $post, $term, $els ); // !IMPORTANT after set_og_image()

		/**
		 * Allows change values of og / twitter meta properties.
		 *
		 * @param array $els
		 */
		$els = apply_filters( 'kama_og_meta_elements_values', $els );

		return array_filter( $els );
	}

	protected function fill_basic( ?WP_Post $post, ?WP_Term $term, array & $els ): void {
		$els['og:locale']      = get_locale();
		$els['og:site_name']   = get_bloginfo('name');
		$els['og:title']       = $this->kst->title;
		$els['og:description'] = $this->kst->description;
	}

	protected function fill_type( ?WP_Post $post, ?WP_Term $term, array & $els ): void {

		if( ! $post || ! is_singular() ){
			$els['og:type'] = 'website';
			return;
		}

		$els['og:type'] = 'article';
		$els['article:published_time'] = mysql2date( 'c', $post->post_date, false );
		$els['article:modified_time'] = mysql2date( 'c', $post->post_modified, false );
		$els['article:author'] = get_user_by( 'id', (int) $post->post_author )->display_name ?? '';

		$this->_fill_og_article_section( $post, $term, $els );
	}

	protected function _fill_og_article_section( ?WP_Post $post, ?WP_Term $term, array & $els ): void {
		/**
		 * Allow to disable `article:section` property.
		 *
		 * @param bool         $is_enabled  True if queried object is any WP post type.
		 * @param null|WP_Post $post        Since v1.9.17.
		 * @param null|WP_Term $term        Since v1.9.17.
		 */
		if( ! apply_filters( 'kama_og_meta_show_article_section', (bool) $post, $post, $term ) || ! is_singular() ){
			return;
		}

		$tax_objects = get_object_taxonomies( $post->post_type, 'objects' );
		$tax_types = [];
		foreach( $tax_objects as $obj ){
			if( ! $obj->public || ! $obj->publicly_queryable ){
				continue;
			}

			if( isset( $tax_types['cat'] ) && isset( $tax_types['tag'] ) ){
				break;
			}

			$obj->hierarchical
				? ( $tax_types['cat'] = $obj->name )
				: ( $tax_types['tag'] = $obj->name );
		}

		foreach( $tax_types as $key => $taxname ){
			$post_terms = get_the_terms( $post, $taxname ) ?: [];
			if( ! $post_terms || is_wp_error( $post_terms ) ){
				continue;
			}

			if( 'cat' === $key ){
				$els['article:section'] = reset( $post_terms )->name;
			}
			else {
				foreach( $post_terms as $index => $_term ){
					$els["article:tag[$index]"] = $_term->name;
				}
			}
		}

	}

	protected function fill_og_url( ?WP_Post $post, ?WP_Term $term, array & $els ): void {
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

	protected function fill_og_image( ?WP_Post $post, ?WP_Term $term, array & $els ): void {
		/**
		 * Allow to change `og:image` `og:image:width` `og:image:height` values.
		 *
		 * @param int|string|array|WP_Post $image_data  WP attachment ID or Image URL or Array [ image_url, width, height ].
		 * @param null|WP_Post             $post        Since v1.9.17.
		 * @param null|WP_Term             $term        Since v1.9.17.
		 */
		$image = apply_filters( 'pre_kama_og_meta_image', null, $post, $term );

		if( ! $image ){
			/**
			 * Allows to turn off the image search in post content.
			 *
			 * @param bool $is_enabled
			 */
			$is_find_in_content = apply_filters( 'kama_og_meta_thumb_id_find_in_content', true );

			if( $post ){
				$image = (int) get_post_thumbnail_id( $post );

				if( ! $image && $is_find_in_content ){

					$image = $this->get_attach_image_id_from_text( $post->post_content );

					// The first post attachment
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
				$image = (int) get_term_meta( $term->term_id, '_thumbnail_id', true );

				if( ! $image && $term->description ){
					$image = $this->get_attach_image_id_from_text( $term->description );
				}
			}

			/**
			 * Allow to set `og:image` `og:image:width` `og:image:height` values if it's not.
			 *
			 * @param int|string|array|WP_Post  $image  WP attachment ID or Image URL or [ image_url, width, height ] array.
			 * @param null|WP_Post             $post        Since v1.9.17.
			 * @param null|WP_Term             $term        Since v1.9.17.
			 */
			$image = apply_filters( 'kama_og_meta_image', $image, $post, $term );
			$image = apply_filters( 'kama_og_meta_thumb_id', $image ); // for backcompat
		}

		if( ! $image ){
			return;
		}

		// WP_Post | int
		if(
			$image instanceof WP_Post
			|| ( is_numeric( $image ) && $image = get_post( $image ) )
		){
			// full size
			[ $url, $width, $height ] = image_downsize( $image->ID, 'full' );
			$image_alt = $image->_wp_attachment_image_alt ?: $image->post_excerpt;
			$mime_type = $image->post_mime_type;

			$els['og:image[1]']        = $url;
			$els['og:image[1]:width']  = $width;
			$els['og:image[1]:height'] = $height;
			$els['og:image[1]:alt']    = $image_alt;
			$els['og:image[1]:type']   = $mime_type;

			// thumbnail size
			[ $url, $width, $height ] = image_downsize( $image->ID, 'thumbnail' );

			$els['og:image[2]']        = $url;
			$els['og:image[2]:width']  = $width;
			$els['og:image[2]:height'] = $height;
		}
		// array
		elseif( is_array( $image ) ){
			$els['og:image[1]']        = $image['og:image']        ?? $image[0];
			$els['og:image[1]:width']  = $image['og:image:width']  ?? $image[1];
			$els['og:image[1]:height'] = $image['og:image:height'] ?? $image[2];
			$els['og:image[1]:alt']    = $image['og:image:alt']    ?? $image[3];
			$els['og:image[1]:type']   = $image['og:image:type']   ?? $image[4];
		}
		// string
		else{
			$els['og:image[1]'] = $image;
		}
	}

	protected function fill_twitter( ?WP_Post $post, ?WP_Term $term, array & $els ): void {
		/**
		 * Allow to disable `twitter:*` elements.
		 *
		 * @param bool $is_enabled Default: true.
		 */
		if( ! apply_filters( 'kama_seo_tags__show_twitter', true ) ){
			return;
		}

		$els['twitter:card'] = 'summary';
		$els['twitter:title'] = $els['og:title'];
		$els['twitter:description'] = $els['og:description'];

		if( ! empty( $els['og:image[1]'] ) ){
			$els['twitter:image'] = $els['og:image[1]'];
		}
	}

	/**
	 * Tryes to find WP attachment image ID from passed content.
	 * Passed content checks with regex and first image name is retrieved,
	 * then the ID is searched in DB by found image name.
	 */
	private function get_attach_image_id_from_text( string $text ): int {

		preg_match( '/<img[^>]+(?:src|srcset) *= *[\'"](?P<src>[^\'", ]+)/', $text, $mm );

		$src = $mm['src'] ?? '';

		if( $src && ( '/' === $src[0] || strpos( $src, $_SERVER['HTTP_HOST'] ?? '' ) )
		){
			$name = basename( $src );
			$name = preg_replace( '~-\d+x\d+(?=\..{2,6})~', '', $name ); // remove the size part (-80x80)
			$name = preg_replace( '~\.[^.]+$~', '', $name );             // remove the extension
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
