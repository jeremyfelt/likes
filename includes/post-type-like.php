<?php
/**
 * Manage the like post type.
 *
 * @package likes
 */

namespace Likes\PostType\Like;

use WP_Post;

add_action( 'init', __NAMESPACE__ . '\register', 10 );
add_action( 'save_post', __NAMESPACE__ . '\save_post', 10, 2 );
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\set_default_post_data', 10 );
add_filter( 'webmention_links', __NAMESPACE__ . '\filter_webmention_links', 10, 2 );
add_filter( 'the_content', __NAMESPACE__ . '\filter_content' );

/**
 * Provide the post type's slug.
 *
 * @return string The post type slug.
 */
function get_slug(): string {
	return 'like';
}

/**
 * Register the post type used to track likes.
 */
function register(): void {
	register_post_type(
		get_slug(),
		array(
			'labels'               => [
				'name'               => __( 'Likes', 'likes' ),
				'singular_name'      => __( 'Like', 'likes' ),
				'add_new_item'       => __( 'Add New Like', 'likes' ),
				'view_item'          => __( 'View Like', 'likes' ),
				'view_items'         => __( 'View Likes', 'likes' ),
				'all_items'          => __( 'All Likes', 'likes' ),
				'search_items'       => __( 'Search Likes', 'likes' ),
				'not_found'          => __( 'No likes found.', 'likes' ),
				'not_found_in_trash' => __( 'No likes found in Trash.', 'likes' ),

			],
			'public'               => true,
			'menu_position'        => 6,
			'menu_icon'            => 'dashicons-star-filled',
			'show_in_rest'         => true,
			'supports'             => false,
			'register_meta_box_cb' => __NAMESPACE__ . '\register_meta_boxes',
			'has_archive'          => true,
			'rewrite'              => [
				'slug' => 'liked',
			],
		)
	);
}

/**
 * Register the meta boxes used to store data for likes.
 *
 * @param WP_Post $post The current like being edited.
 */
function register_meta_boxes( WP_Post $post ): void {
	add_meta_box( 'like-data-primary', __( 'Like data', 'likes' ), __NAMESPACE__ . '\display_meta_box', $post->post_type, 'normal', 'high' );
}

/**
 * Retrieve the URL associated with a like.
 *
 * @param int $post_id The ID of the current like.
 * @return string The URL associated with the like.
 */
function get_like_url( int $post_id ): string {
	$url = get_post_meta( $post_id, 'like_of_url', true );

	// Account for likes published via the Micropub plugin.
	if ( ! $url ) {
		$url = get_post_meta( $post_id, 'mf2_like-of', true );
	}

	$url = is_array( $url ) ? array_pop( $url ) : $url;

	return $url;
}

/**
 * Display the meta box used to capture like data.
 *
 * @param WP_Post $post The current like being edited.
 */
function display_meta_box( WP_Post $post ): void {
	$url = get_like_url( $post->ID );

	wp_nonce_field( 'save-like-data', 'like_data_nonce' );
	?>
	<h3><?php esc_html_e( 'URL', 'likes' ); ?></h3>
	<input class="widefat" type="text" id="like-url" name="like_url" value="<?php echo esc_url( $url ); ?>" />

	<h3><?php esc_html_e( 'Title', 'likes' ); ?></h3>
	<input class="widefat" type="text" id="like-title" name="like_title" value="<?php echo esc_attr( $post->post_title ); ?>">
	<?php
}

/**
 * Save meta data attached to a like.
 *
 * @param int     $post_id The ID of the current like.
 * @param WP_Post $post    The post object representing the current like.
 */
function save_post( int $post_id, WP_Post $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'auto-draft' === $post->post_status ) {
		return;
	}

	if ( ! isset( $_POST['like_data_nonce'] ) || ! wp_verify_nonce( $_POST['like_data_nonce'], 'save-like-data' ) ) {
		return;
	}

	if ( isset( $_POST['like_url'] ) && '' !== $_POST['like_url'] ) {
		update_post_meta( $post_id, 'like_of_url', esc_url_raw( $_POST['like_url'] ) );
	} elseif ( isset( $_POST['like_url'] ) && '' === $_POST['like_url'] ) {
		delete_post_meta( $post_id, 'like_of_url' );
	}
}

/**
 * Modify the defaults stored with a new like.
 *
 * @param array $post Current post data to store for the like.
 * @return array $post Modified post data to store.
 */
function set_default_post_data( array $post ): array {
	if ( 'like' === $post['post_type'] && '' === $post['post_name'] && 'Auto Draft' === $post['post_title'] ) {
		$post['post_title'] = __( 'Like', 'likes' );
		$post['post_name']  = gmdate( 'YmdHis' );
	}

	if ( ! isset( $_POST['like_data_nonce'] ) || ! wp_verify_nonce( $_POST['like_data_nonce'], 'save-like-data' ) ) {
		return $post;
	}

	if ( 'like' === $post['post_type'] && isset( $_POST['like_title'] ) ) {
		$post['post_title'] = sanitize_text_field( $_POST['like_title'] );
		$post['post_name']  = sanitize_key( $_POST['like_title'] );
	}

	return $post;
}

/**
 * Add the URL associated with this like to the list of URLs to be
 * pinged with a webmention.
 *
 * @param string[] $urls    List of URLs to ping.
 * @param int      $post_id The current post ID.
 * @return string[] $urls Modified list of URLs to ping.
 */
function filter_webmention_links( array $urls, int $post_id ): array {
	$post = get_post( $post_id );

	if ( $post && get_slug() === $post->post_type ) {
		$url = get_like_url( $post_id );

		if ( '' !== $url ) {
			$urls[] = $url;
		}
	}

	return $urls;
}

/**
 * Filter like content with a specific format.
 *
 * @param string $content The like content.
 * @return string The modified content.
 */
function filter_content( string $content ): string {
	if ( get_slug() !== get_post_type() || false === get_the_ID() ) {
		return $content;
	}

	$url   = get_like_url( get_the_ID() );
	$title = get_the_title();
	$title = $title ? $title : $url; // Fall back to the URL if a title is not available.
	$host  = wp_parse_url( $url, PHP_URL_HOST );

	ob_start();
	?>
	<p>
		<span class="screen-reader-text">Liked </span>
		<a class="u-like-of" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
		<span class="like-of-domain"><?php echo esc_html( $host ); ?></span>
	</p>
	<?php
	return (string) ob_get_clean();
}
