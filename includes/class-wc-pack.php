<?php
/**
 * Product Pack Class
 */

class WC_Product_wc_pack extends WC_Product
{	
	function __construct( $product )
	{
		$this->product_type = 'wc_pack';
    parent::__construct( $product );
	}

	public function get_custom_price()
	{
		$sale_price = $this->get_sale_price();
		if ($sale_price) return $sale_price;
		$regular_price = $this->get_regular_price();
		if ($regular_price) return $regular_price;
	}

	public function set_custom_price()
	{
		$price = 0;
		$product_items = $this->get_products_id();
    if (count($product_items) > 0) {
      foreach ($product_items as $id) {
        $temp_prod = wc_get_product($id);
        $price += (float)$temp_prod->get_price();
      }
    }
    delete_post_meta( $this->id, '_sale_price');
    update_post_meta( $this->id, '_regular_price', $price );
    update_post_meta( $this->id, '_price', $price );
    $this->set_price($price);
	}

	public function get_products_id()
	{
		$product_items = get_post_meta( $this->id, 'pack_ids', true );
    if ( is_array($product_items) && count($product_items) > 0 ) {
    	return $product_items;
    }
    else {
    	return false;
    }
	}

	public function set_custom_stock()
	{
    $stock = false;
		$product_items = $this->get_products_id();
    if ($product_items) {
      $stock = true;
      foreach ($product_items as $id) {
        $temp_prod = wc_get_product($id);
        if (!$temp_prod->is_in_stock()) $stock = false;
      }
    }
    if ($stock) $this->set_stock_status('instock');
    else $this->set_stock_status('outofstock');
	}
}

