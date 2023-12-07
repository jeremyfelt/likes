<?php
/**
 * Plugin Name:     Likes
 * Plugin URI:      https://github.com/jeremyfelt/likes
 * Description:     Add a likes post type to WordPress. Like others' URLs.
 * Author:          jeremyfelt
 * Author URI:      https://jeremyfelt.com
 * Text Domain:     likes
 * Domain Path:     /languages
 * Version:         1.0.2
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package likes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/includes/post-type-like.php';
