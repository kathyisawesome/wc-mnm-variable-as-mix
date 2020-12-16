<?php
/**
 * Plugin Name: WooCommerce Mix and Match -  Variations as Mix
 * Plugin URI: http://www.woocommerce.com/products/wc-mnm-variable-as-mix/
 * Description: Treat a variable product as a mix and match product, using it's variations as allowed content
 * Version: 1.0.0-beta-1
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Developer: Kathy Darling
 * Developer URI: http://kathyisawesome.com/
 * Text Domain: wc-mnm-variations-as-mix
 * Domain Path: /languages
 *
 * Copyright: © 2020 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */



namespace WC_MNM_Variable_Mix;

define( 'WC_MNM_GROUPED_VERSION', '1.0.0' );

/**
 * WC_MNM_Variable_Mix Constructor
 *
 * @access 	public
 * @return 	WC_MNM_Variable_Mix
 */
function init() {

	// Load translation files.
	add_action( 'init', __NAMESPACE__ . '\load_plugin_textdomain' );

	// Product type option.
	add_filter( 'product_type_options', __NAMESPACE__ . '\product_type_options' );

	// Admin scripts.
	add_filter( 'admin_enqueue_scripts', __NAMESPACE__ . '\admin_enqueue_scripts', 20 );

	// Display fields.
	add_action( 'woocommerce_mnm_product_options', __NAMESPACE__ . '\allowed_contents_options', 21, 2 );

	// Save MNM data when saving Variations.
	add_action( 'woocommerce_after_product_object_save', __NAMESPACE__ . '\save_additional_meta', 10, 2 );

	// Save data.
	add_action( 'woocommerce_admin_process_product_object', __NAMESPACE__ . '\process_meta', 20 );

	// Flip the product type.
	add_filter( 'woocommerce_product_class', __NAMESPACE__ . '\convert_to_mnm', 10, 2 );

}


/*-----------------------------------------------------------------------------------*/
/* Localization */
/*-----------------------------------------------------------------------------------*/


/**
 * Make the plugin translation ready
 *
 * @return void
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'wc-mnm-variations-as-mix' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
}


/*-----------------------------------------------------------------------------------*/
/* Admin */
/*-----------------------------------------------------------------------------------*/


/**
 * Adds support for the 'grouped mix and match' product type.
 *
 * @param  array 	$options
 * @return array
 * @since  1.0.0
 */
function product_type_options( $options ) {

	$options['display_as_mnm'] = array(
		'id'            => '_display_as_mnm',
		'wrapper_class' => 'show_if_variable',
		'label'         => __( 'Display as Mix and Match', 'wc-mnm-variations-as-mix' ),
		'description'   => __( 'Displays all variations as part of Mix and Match style product', 'wc-mnm-variations-as-mix' ),
		'default'       => 'no',
	);
	return $options;
}


/**
 * jQuery scripts.
 *
 * @param  array 	$options
 * @return array
 * @since  1.0.0
 */
function admin_enqueue_scripts() {
	wp_add_inline_script( 'wc-mnm-admin-product-panel', '

		jQuery( function( $ ) {

			$( "#_display_as_mnm" ).on( "change", function(e) {
				if ( "variable"  === $("#product-type").val() ) {
					if ( $(this).prop("checked") ) {
						$( ".mnm_options_options.mnm_options_tab > a" ).trigger( "click" );
						$( ".product_data_tabs .mnm_options_tab" ).show();
						$( "#mnm_allowed_contents_options_variations" ).show();
						$( "#mnm_allowed_contents_options" ).hide();
						$( "#general_product_data .options_group.pricing" ).show();
						$( "#general_product_data .show_if_mix-and-match" ).show();
					} else {
						$( ".product_data_tabs .mnm_options_tab" ).hide();
						$( "#mnm_allowed_contents_options_variations" ).hide();
						$( "#mnm_allowed_contents_options" ).show();
						$("#mnm_product_data").hide();
						$( ".product_data_tabs .general_options a" ).trigger( "click" );
						$( "#general_product_data .options_group.pricing" ).hide();
					}
				} else {
					$( "#mnm_allowed_contents_options" ).show();
				}
			});

			$( document.body ).on( "woocommerce-product-type-change", function( event, select_val ) {
				if ( "variable"  === select_val ) {
					$( "#_display_as_mnm" ).change();
				}
			});

		});

	' );
}



/**
 * Adds allowed contents select2 writepanel options.
 *
 * @since  1.0.0
 *  
 * @param int $post_id
 * @param  WC_Product_Mix_and_Match  $mnm_product_object
 */
function allowed_contents_options( $post_id, $mnm_product_object ) {
?>
	<p id="mnm_allowed_contents_options_variations" class="form-field">
		<label><?php esc_html_e( 'Allowed Contents', 'wc-mnm-variations-as-mix' ); ?></label>

		<?php esc_html_e( 'As a variable product, allowed contents are being pulled automatically from your variations. Please note that variations need to be "fully-defined" in order to be a Mix and Match option.', 'wc-mnm-variations-as-mix' ); ?>
	</p>
	<?php
}


/**
 * Trigger action before saving to the DB. Allows you to adjust object props before save.
 *
 * @param WC_Data          $this The object being saved.
 * @param WC_Data_Store_WP $data_store The data store persisting the data.
 */
function save_additional_meta( $product, $data_store ) {
	if ( $product->is_type( 'variable' ) && wc_string_to_bool( $product->get_meta( '_display_as_mnm' ) ) ) {

		\add_filter( 'wc_mnm_display_empty_container_error', '__return_false' );

		

		$mnm_product = new \WC_Product_Mix_and_Match ( $product );

		// Set up props for MNM product.
		\WC_MNM_Meta_Box_Product_Data::process_mnm_data( $mnm_product );

		//$data_store = \WC_Data_Store::load( 'product-mix-and-match' );

		$data_store = $mnm_product->get_data_store();

		error_log('data store class ' . $data_store->get_current_class_name());

		// Save them.
		$data_store->update_post_meta( $mnm_product );  // This isn't calling the MNM data store, but rather the generic one!

	}
}


/**
 * Saves the new meta field.
 *
 * @param  WC_Product_Mix_and_Match  $product
 */
function process_meta( $product ) {
	$value = \wc_bool_to_string( isset( $_POST['_display_as_mnm'] ) );
	$product->update_meta_data( '_display_as_mnm', $value );
}


/*-----------------------------------------------------------------------------------*/
/* Front End Display */
/*-----------------------------------------------------------------------------------*/

/**
 * Switch the product type on the front-end.
 *
 * @param  string $product_type Product type.
 * @param  int    $product_id   Product ID.
 * @return string
 */
function convert_to_mnm( $product_type, $product_id ) {

	if ( ! is_admin() && 'variable' === $product_type ) {
		if ( wc_string_to_bool( get_post_meta( $product_id, '_display_as_mnm' ), true ) ) {
			$product_type = 'mix-and-match';
		}
	}

	return $product_type;

}


/*-----------------------------------------------------------------------------------*/
/* Launch the whole plugin. */
/*-----------------------------------------------------------------------------------*/
add_action( 'woocommerce_mnm_loaded', __NAMESPACE__ . '\init' );

