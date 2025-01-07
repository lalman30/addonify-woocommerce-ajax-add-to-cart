<?php
/*
 * Plugin Name:       Addonify WooCommerce Ajax Add to Cart
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:        Ajax add to cart for WooCommerce.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Addonify
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       addonify-woocommerce-ajax-add-to-cart
 * Domain Path:       /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'ADDONIFY_AJAX_ADDTOCART_VERSION' ) ) {
	define( 'ADDONIFY_AJAX_ADDTOCART_VERSION', '1.0.0' );
}

if ( ! defined( 'ADDONIFY_AJAX_ADDTOCART_PATH' ) ) {
	define( 'ADDONIFY_AJAX_ADDTOCART_PATH', plugin_dir_path( __FILE__ ) );
}

require_once ADDONIFY_AJAX_ADDTOCART_PATH . 'inc/class-addonify-woocommerce-ajax-add-to-cart-main.php';

if ( class_exists( 'Addonify_Woocommerce_Ajax_Add_To_Cart_Main' ) ) {
	new Addonify_Woocommerce_Ajax_Add_To_Cart_Main();
}
