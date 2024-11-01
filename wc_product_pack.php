<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/*
 * Plugin Name: Woocommerce products bundles
 * Plugin URL: http://castillogomez.com/
 * Description: Add several products to your bundle and sell them together with a custom price.
 * Version: 1.0.2
 * Author: Paco Castillo
 * Author URI: http://castillogomez.com/
 * Text Domain: wc_packs
 * Domain Path: languages
 */  

if (!function_exists('is_plugin_active_for_network'))
  require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

// Check if WooCommerce is active and bail if it's not
if ( !is_woocommerce_active() ) {
  return;
}


/**
 *  
 */

add_filter( 'product_type_selector', 'wc_pack_add_product_type' );
add_action( 'init', 'wc_pack_init' );
add_action( 'woocommerce_loaded', 'wc_pack_includes' );

// Admin pack tabs
add_filter( 'woocommerce_product_data_tabs', 'wc_pack_tabs' );
add_action( 'woocommerce_product_data_panels', 'wc_pack_tabs_content' );

// Save product
add_action( 'save_post', 'wc_pack_save', 10, 3 );

// Front
add_action( 'woocommerce_single_product_summary', 'wc_pack_product_items', 15 );
add_action( 'woocommerce_wc_pack_add_to_cart', 'wc_pack_add_to_cart' );

function is_woocommerce_active()
{
  return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || is_plugin_active_for_network('woocommerce/woocommerce.php');
}

function wc_pack_init()
{
  load_theme_textdomain('wc_packs', plugin_dir_path( __FILE__ ) . 'languages');
}

function wc_pack_includes()
{
  require_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-pack.php' );
}

function wc_pack_add_product_type( $types )
{
  $types[ 'wc_pack' ] = __( 'Product Pack', 'wc_packs' );
  return $types;
}

function wc_pack_admin_js()
{
  wp_enqueue_script( 'wc_pack_js', plugins_url( 'assets/wc_pack.js', __FILE__ ), array('jquery'), false, true );
}

function wc_pack_tabs( $tabs )
{
  $tabs['variations']['class'][] = 'hide_if_pack';
  $tabs['pack'] = array(
          'label' => 'Pack',
          'target' => 'pack_product_data',
          'class' => array('hide_if_simple', 'hide_if_variable', 'hide_if_grouped', 'show_if_pack')
        );

  return $tabs;
}

function wc_pack_tabs_content()
{
  global $post;
  ?>
  <div id="pack_product_data" class="panel woocommerce_options_panel">
    <div class="options_group hide_if_simple hide_if_variable hide_if_grouped">
      <p class="form-field">
        <label for="pack_ids"><?php _e('Products in pack', 'wc_packs'); ?></label>
        <input type="hidden" class="wc-product-search" id="pack_ids" name="pack_ids" style="width: 50%" data-placeholder="<?php esc_attr_e( 'Search for a product', 'wc_packs' ); ?>" data-action="woocommerce_json_search_products" data-multiple="true" data-exclude="<?php echo intval( $post->ID ); ?>" data-selected="<?php
        $product_ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, 'pack_ids', true ) ) );
        $json_ids    = array();

        foreach ( $product_ids as $product_id ) {
          $product = wc_get_product( $product_id );
          if ( is_object( $product ) ) {
            $json_ids[ $product_id ] = wp_kses_post( html_entity_decode( $product->get_formatted_name(), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
          }
        }

        echo esc_attr( json_encode( $json_ids ) );
        ?>" value="<?php echo implode( ',', array_keys( $json_ids ) ); ?>" />
      </p>
    </div>

    <div class="options_group hide_if_simple hide_if_variable hide_if_grouped">
      <p class="form-field">
        <label for="_regular_price"><?php echo __('Pack price', 'wc_packs') . ' (' . get_woocommerce_currency_symbol() . ')'; ?></label>
        <input class="short wc_input_price" type="number" name="_regular_price" value="<?php echo get_post_meta( $post->ID, '_regular_price', true ); ?>">
      </p>
      <p class="form-field">
        <label for="_sale_price"><?php echo __('Sale pack price', 'wc_packs') . ' (' . get_woocommerce_currency_symbol() . ')'; ?></label>
        <input class="short wc_input_price" type="number" name="_sale_price" value="<?php echo get_post_meta( $post->ID, '_sale_price', true ); ?>">
        <span class="description"><?php _e('If no price enter, the pack price will be the sum of individual products price', 'wc_packs'); ?></span>
      </p>
    </div>
  </div>
  <?php
}

function wc_pack_save( $post_id, $post, $update )
{
  if (get_post_type( $post_id ) == 'product' && (isset($_POST['product-type'])) && $_POST['product-type'] == 'wc_pack') {
    $product = wc_get_product($post_id);
    if ( isset($_POST['_regular_price']) && $_POST['_regular_price'] != '' ) {
      delete_post_meta( $post_id, '_sale_price' );
      update_post_meta( $post_id, '_regular_price', (float)$_POST['_regular_price'] );
      $product->set_price((float)$_POST['_regular_price']);
    }
    if ( isset($_POST['_sale_price']) && $_POST['_sale_price'] != '' ) {
      update_post_meta( $post_id, '_sale_price', (float)$_POST['_sale_price'] );
      $product->set_price((float)$_POST['_sale_price']);
    }
    if ( (!isset($_POST['_regular_price']) || $_POST['_regular_price'] == '') && (!isset($_POST['_sale_price']) || $_POST['_sale_price'] == '') ) {
      $product->set_custom_price();
    }

    // stock and dependecies
    $stock = false;
    if ( isset($_POST['pack_ids']) && $_POST['pack_ids'] != '' ) {
      $ids = explode(',', $_POST['pack_ids']);
      $stock = true;
      foreach ($ids as $id) {
        $in_pack = get_post_meta( $id, '_in_pack', true );
        update_post_meta( $id, '_in_pack', $in_pack . '"' . $post_id . '",' );
        $temp_prod = wc_get_product($id);
        if (!$temp_prod->is_in_stock()) $stock = false;
      }
      update_post_meta( $post_id, 'pack_ids', $ids );
    }
    if ($stock) $product->set_stock_status('instock');
    else $product->set_stock_status('outofstock');
  }
  else if (get_post_type( $post_id ) == 'product') {
    // check if this product is in pack and change stock
    $in_pack = get_post_meta( $post_id, '_in_pack', true );
    if ( $in_pack && $in_pack != '' ) {
      $in_pack = explode(',', substr($in_pack, 0, -1));
      foreach ($in_pack as $id) {
        $temp_prod = wc_get_product((int)str_replace('"', '', $id));
        $temp_prod->set_custom_stock();
      }
    }
  }
  else if ( (get_post_type( $post_id ) == 'shop_order') && !$update ) {
    // stock for products pack
    wc_pack_after_payment($post_id);
  }
}

function wc_pack_product_items()
{
  global $product;
  if ( $product->get_type() == 'wc_pack' ) {
    // d($product);
    $product_items = get_post_meta( $product->id, 'pack_ids', true );
    if ( is_array($product_items) && count($product_items) > 0 ) :
      ?>
      <div class="pack-product-items">
        <h5><?php _e('This pack contains: ', 'wc_packs'); ?></h5>
        <ul>
        <?php foreach ($product_items as $p_id) : ?>
          <?php $temp_prod = wc_get_product($p_id); ?>
          <li>
            <?php if ( $thumb = $temp_prod->get_image() ) : ?>
              <div class="thumb"><?php echo $thumb; ?></div>
            <?php endif; ?>
            <div class="wrap">
              <div class="name"><?php echo $temp_prod->get_title(); ?></div>
              <div class="item-price"><?php echo $temp_prod->get_price_html(); ?></div>
            </div>
          </li>
        <?php endforeach; ?>
        </ul>
      </div>
    <?php
    endif;
  }
}

function wc_pack_add_to_cart()
{
  global $product;
  wc_get_template( 'single-product/add-to-cart/simple.php' );
}

function wc_pack_after_payment( $order_id = null )
{
  if ( !$order_id ) return;

  // change stock products in pack
  $order = new WC_Order( $order_id );
  $items = $order->get_items();
  foreach ($items as $item) {
    $product = wc_get_product($item['product_id']);
    if ( $product->get_type() == 'wc_pack' ) {
      $product_items = get_post_meta( $product->id, 'pack_ids', true );
      if ( is_array($product_items) && count($product_items) > 0 ) {
        foreach ($product_items as $p_id) {
          $temp_prod = wc_get_product($p_id);
          $temp_prod->reduce_stock();
        }
      }
      $product->set_custom_stock();
    }
  }
}


if (!function_exists('d')) {
	function d($var) {
	  echo '<pre>';
	  ob_start();
	  var_dump($var);
	  echo ob_get_clean();
	  echo '</pre>';
	}
}