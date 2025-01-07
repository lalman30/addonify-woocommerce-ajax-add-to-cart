<?php
/**
 * Plugin main class file.
 *
 * @since 1.0.0
 * @package  addonify-woocommerce-ajax-add-to-cart
 */

if ( ! class_exists( 'Addonify_Woocommerce_Ajax_Add_To_Cart_Main' ) ) {
	/**
	 * Plugin main class.
	 */
	class Addonify_Woocommerce_Ajax_Add_To_Cart_Main {
		/**
		 * Class constructor
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'add_actions' ) );

			/**
			 * Fire all the hooks
			 */
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		}

		/**
		 * Enqueue scripts.
		 */
		public function enqueue_scripts() {
			wp_enqueue_script(
				'ajax-addtocart',
				plugin_dir_url( __DIR__ ) . '/assets/ajax-addtocart.js',
				array( 'jquery' ),
				ADDONIFY_AJAX_ADDTOCART_VERSION,
				true,
			);

			// Initialize localize object.
			$ajax_addtocart_localize_data = array(
				'ajax_url'                  => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'                     => wp_create_nonce( 'ajax_addtocart_nonce' ),
				'ajaxSingleAddToCartAction' => 'addonify_ajax_single_addtocart',
			);

			wp_localize_script( 'ajax-addtocart', 'ajaxSingleAddtocartJSObj', $ajax_addtocart_localize_data );
		}

		/**
		 * Hook functions to actions.
		 *
		 * @since 1.0.0
		 */
		public function add_actions() {

			add_action( 'wp_ajax_addonify_ajax_single_addtocart', array( $this, 'woocommerce_single_ajax_add_to_cart_handler' ) );
			add_action( 'wp_ajax_nopriv_addonify_ajax_single_addtocart', array( $this, 'woocommerce_single_ajax_add_to_cart_handler' ) );
		}

		/**
		 * Function to handle add to cart AJAX request coming from product single.
		 *
		 * @since 1.0.0
		 */
		public function woocommerce_single_ajax_add_to_cart_handler() {
			$product_types = array( 'simple', 'variable', 'grouped' );

			$form_data = isset( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : ''; // phpcs:ignore

			// verify nonce.
			if ( ! isset( $form_data['nonce'] ) || ! wp_verify_nonce( $form_data['nonce'], 'ajax_addtocart_nonce' ) ) {
				wp_send_json(
					array(
						'status'  => false,
						'notices' => array(
							array(
								'notice_type' => 'error',
								'notice'      => esc_html__( 'Error! Nonce verification failed!!.', 'addonify-woocommerce-ajax-add-to-cart' ),
							),
						),
					)
				);
			}

			// Return if no request data has been received.
			if ( empty( $form_data ) ) {
				wp_send_json(
					array(
						'status'  => false,
						'notices' => array(
							array(
								'notice_type' => 'error',
								'notice'      => esc_html__( 'Error! No data received.', ' addonify-woocommerce-ajax-add-to-cart' ),
							),
						),
					)
				);
			}


			// Return if no data associated with product type has been received.
			if (
				! isset( $form_data['type'] ) ||
				! in_array( $form_data['type'], $product_types, true )
			) {
				wp_send_json(
					array(
						'status'  => false,
						'notices' => array(
							array(
								'notice_type' => 'error',
								'notice'      => esc_html__( 'Error! Invalid product type.', ' addonify-woocommerce-ajax-add-to-cart' ),
							),
						),
					)
				);
			}

			// Return if no data associated with product has been received.
			if (
				! isset( $form_data['data'] ) ||
				empty( $form_data['data'] )
			) {
				wp_send_json(
					array(
						'status'  => false,
						'notices' => array(
							array(
								'notice_type' => 'error',
								'notice'      => esc_html__( 'Error! No product data received.', ' addonify-woocommerce-ajax-add-to-cart' ),
							),
						),
					)
				);
			}

			// Clear all WC notices from the session.
			wc_clear_notices();

			$response = array(
				'status' => false,
			);

			$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

			$messages = array();

			$product_data = $form_data['data'];

			$wc_cart = WC()->cart;
			// Add simple product in the cart.
			if ( 'simple' === $form_data['type'] ) {

				$product_id = absint( $product_data['product_id'] );
				$quantity   = wc_stock_amount( wp_unslash( $product_data['quantity'] ) );
				if ( false !== $wc_cart->add_to_cart( $product_id, $quantity ) ) {
					$response['status'] = true;
					wc_add_to_cart_message( $product_id, false, false );
				}
			}
			// Add variation product in the cart.
			if ( 'variable' === $form_data['type'] ) {

				$product_id   = absint( $product_data['product_id'] );
				$quantity     = wc_stock_amount( wp_unslash( $product_data['quantity'] ) );
				$variation_id = absint( $product_data['variation_id'] );
				$variations   = isset( $product_data['variations'] ) ? $product_data['variations'] : array();

				if ( false !== $wc_cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) ) {
					$response['status'] = true;
					wc_add_to_cart_message( $product_id, false, false );
				}
			}

			// Add grouped product in the cart.
			if ( 'grouped' === $form_data['type'] ) {

				if ( $product_data ) {

					$quantity_set      = false;
					$was_added_to_cart = false;
					$added_to_cart     = array();

					foreach ( $product_data as $product_id => $quantity ) {

						$quantity = wc_stock_amount( $quantity );

						if ( $quantity <= 0 ) {
							continue;
						}

						$quantity_set = true;

						// Suppress total recalculation until finished.
						remove_action( 'woocommerce_add_to_cart', array( $wc_cart, 'calculate_totals' ), 20, 0 );

						if ( false !== $wc_cart->add_to_cart( $product_id, $quantity ) ) {
							$was_added_to_cart            = true;
							$added_to_cart[ $product_id ] = $quantity;
							wc_add_to_cart_message( $product_id, false, false );
						}

						add_action( 'woocommerce_add_to_cart', array( $wc_cart, 'calculate_totals' ), 20, 0 );
					}

					if ( ! $was_added_to_cart && ! $quantity_set ) {

						wc_add_notice( esc_html__( 'Please choose the quantity of items you wish to add to your cart&hellip;', ' addonify-woocommerce-ajax-add-to-cart' ), 'error' );
					}

					$wc_cart->calculate_totals();

					$response['status'] = ( $was_added_to_cart || count( $added_to_cart ) > 0 ) ? true : false;
				} else {

					wc_add_notice( esc_html__( 'Please choose a product to add to your cart&hellip;', ' addonify-woocommerce-ajax-add-to-cart' ), 'error' );
				}
			}

			$response['notices'] = $this->prepare_wc_notice_html();

			$response['cart_hash'] = WC()->cart->get_cart_hash();

			ob_start();

			woocommerce_mini_cart();

			$mini_cart = ob_get_clean();

			$response['fragments'] = apply_filters(
				'woocommerce_add_to_cart_fragments',
				array(
					'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
				)
			);

			// Clear all WC notices from the session.
			wc_clear_notices();

			wp_send_json( $response );
		}

		/**
		 * Prepares WC notices to displayed as default WC notices in product single.
		 *
		 * @since 1.0.17
		 */
		public function prepare_wc_notice_html() {

			$wc_notices = wc_get_notices();

			if ( ! is_array( $wc_notices ) || ! count( $wc_notices ) > 0 ) {
				return;
			}

			$messages = array();

			foreach ( $wc_notices as $notice_type => $notices ) {
				foreach ( $notices as $wc_notice ) {

					$messages[] = array(
						'notice_type' => $notice_type,
						'notice'      => $wc_notice['notice'],
					);
				}
			}

			$error_notices   = array();
			$success_notices = array();
			$info_notices    = array();

			foreach ( $messages as $message ) {
				switch ( $message['notice_type'] ) {
					case 'error':
						$error_notices[] = $message;
						break;
					case 'success':
						$success_notices[] = $message;
						break;
					default:
						$info_notices[] = $message;
				}
			}

			$notice_html = '<div class="woocommerce-notices-wrapper">';
			if ( $error_notices ) {
				$notice_html .= '<ul class="woocommerce-error" role="alert">';
				foreach ( $error_notices as $error_notice ) {
					$notice_html .= '<li>';
					$notice_html .= wp_kses_post( $error_notice['notice'] );
					$notice_html .= '</li>';
				}
				$notice_html .= '</ul>';
			}

			if ( $success_notices ) {
				foreach ( $success_notices as $success_notice ) {
					$notice_html .= '<div class="woocommerce-message" role="alert">';
					$notice_html .= wp_kses_post( $success_notice['notice'] );
					$notice_html .= '</div>';
				}
			}

			if ( $info_notices ) {
				foreach ( $info_notices as $info_notice ) {
					$notice_html .= '<div class="woocommerce-info">';
					$notice_html .= wp_kses_post( $info_notice['notice'] );
					$notice_html .= '</div>';
				}
			}
			$notice_html .= '</div>';

			return $notice_html;
		}
	}
}
