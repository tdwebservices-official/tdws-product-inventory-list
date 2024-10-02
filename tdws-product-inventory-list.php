<?php
/**
 * @package TDWS_Product_Inventory_List
 * @version 1.0.0
 */
/*
Plugin Name: TDWS Product Inventory List
Plugin URI: https://tdwebservices.com/
Description: This is used for product inventory facility addon.
Author: TDWS Web Services
Requires Plugins: woocommerce
Version: 1.0.4
Author URI: https://tdwebservices.com/
*/

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/tdwebservices-official/tdws-product-inventory-list/',
	__FILE__,
	'tdws-product-inventory-list'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('master');


function tdws_add_inventory_script_admin_side() {

	$tdws_screen    = get_current_screen();
	$tdws_screen_id = isset($tdws_screen->id) ? $tdws_screen->id : '';

	if ( $tdws_screen_id == 'admin_page_tdws-user-tracking' || $tdws_screen_id == 'tdws_page_tdws-user-tracking' ) {
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'jquery-ui-style' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'woocommerce_admin_print_reports_styles' );
	}

	wp_enqueue_style( 'tdws-custom-code-style', plugin_dir_url( __FILE__ ). '/css/tdws-custom-code.css', array(), '1.1', 'all' );
	wp_enqueue_script( 'tdws-custom-code-script', plugin_dir_url( __FILE__ ). '/js/tdws-custom-code.js', array( 'jquery' ), 1.1, true );
	wp_localize_script( 'tdws-custom-code-script', 'tdwsCustomAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce("tdwsCustomNonce") )); 

}
add_action( 'admin_enqueue_scripts', 'tdws_add_inventory_script_admin_side' );

add_filter( 'woocommerce_reports_screen_ids', 'tdws_custom_wc_reports_user_tracking_screen_ids', 11, 1 );
function tdws_custom_wc_reports_user_tracking_screen_ids( $screen_id_list ){
	$screen_id_list[] = 'admin_page_tdws-user-tracking';
	$screen_id_list[] = 'tdws_page_tdws-user-tracking';
	return $screen_id_list;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Register the admin menu
add_action('admin_menu', 'tdws_add_product_list_menu');

function tdws_add_product_list_menu() {	
	add_submenu_page( 'tdws_order_tracking', __( 'TDWS Product Inventory', 'tdws-product-inventory-list' ), __( 'TDWS Product Inventory', 'tdws-product-inventory-list' ), 'view_tdws_product_inventory', 'tdws-product-inventory', 'tdws_add_product_list_menu_option' );
	add_submenu_page( 'tdws_order_tracking', __( 'TDWS User Tracking', 'tdws-product-inventory-list' ), __( 'TDWS User Tracking', 'tdws-product-inventory-list' ), 'administrator', 'tdws-user-tracking', 'tdws_user_tracking_report_page' );
}

function tdws_add_custom_capability_to_all_roles() {
    // Define the custom capability
	$tdws_capability = 'view_tdws_product_inventory';
    // Optionally add the capability to Administrators as well, if needed
	$tdws_admin_role = get_role( 'administrator' );
	if ( $tdws_admin_role ) {
		$tdws_admin_role->add_cap( $tdws_capability );
	}
}
add_action( 'admin_init', 'tdws_add_custom_capability_to_all_roles' );

function tdws_add_product_list_menu_option() {
	?>
	<div class="wrap">
		<h1><?php _e('TDWS Product Inventory List', 'tdws-product-inventory-list' ); ?></h1>

		<div class="tdws-import-form-wrap">
			<form method="post" method="post" class="tdws_import_form" enctype="multipart/form-data">
				<h2><?php _e('Import Data', 'tdws-product-inventory-list' ); ?></h2>
				<div style="margin-bottom: 15px;">
					<label><span><?php _e('Enter Import Item Per Page', 'tdws-product-inventory-list' ); ?> : </span><input type="number" placeholder="<?php _e('Enter Import Item Per Page', 'tdws-product-inventory-list' ); ?>" class="tdws_import_per_Item" value="5"></label>
				</div>
				<input type="file" accept=".csv" name="tdws_import_file" class="tdws_import_file">
				<?php wp_nonce_field('importWebLinkNonce', 'TDWSType'); ?>
				<button type="submit" class="button tdws_import_button"><?php _e('Import', 'tdws-product-inventory-list' ); ?></button><span class="tdws-web-loader tdws-hide"><img src="<?php echo esc_url(plugin_dir_url( __FILE__ ).'/images/loader.gif'); ?>"/></span>
				<p><?php _e('Download Sample File Please,', 'tdws-product-inventory-list' ); ?> <a href="<?php echo plugin_dir_url( __FILE__ ).'/SampleVendorlink.csv'; ?>" download><?php _e('Click Here', 'tdws-product-inventory-list' ); ?></a></p>
				<p>If you don't download sample file, you need to make 2 column. Column 1 Name : SKU/URL  And Column 2 Name : Vendor Links </p>
				<h3 class="tdws-cnt-box tdws-hide"><?php _e('Records :', 'tdws-product-inventory-list' ); ?> <span class="tdws-success-cnt"><?php _e('Success:', 'tdws-product-inventory-list' ); ?> <span>0</span></span> / <span class="tdws-error-cnt"><?php _e('Error:', 'tdws-product-inventory-list' ); ?> <span>0</span></span></h3>
				<p class="tdws_download_error tdws-hide"><?php _e('Download Error Data File Please,', 'tdws-product-inventory-list' ); ?> <a href="javascript:;" class="tdws_download_error_file"><?php _e('Click Here', 'tdws-product-inventory-list' ); ?></a></p>

				<p class="tdws_download_success tdws-hide"><?php _e('Download Import Data File Please,', 'tdws-product-inventory-list' ); ?> <a href="javascript:;" class="tdws_download_success_file"><?php _e('Click Here', 'tdws-product-inventory-list' ); ?></a></p>
			</form>
		</div>

		<?php
    // Instantiate and display the custom table
		$table = new TDWS_Product_Inventory_List_Table();
		$table->prepare_items();
		?>
		<form method="post">
			<?php
			$table->display();
			?>
		</form>
	</div>
	<?php
}

function tdws_user_tracking_report_page() {
	?>
	<div class="wrap">
		<h1><?php _e('TDWS User Tracking List', 'tdws-product-inventory-list' ); ?></h1>
		<style type="text/css">
			.chart-sidebar {
				height: 695px !important;
				overflow-y: auto;
			}
		</style>
		<?php

		include_once dirname( __FILE__ ) . '/class-tdws-user-tracking-reports.php';

		$user_by_date                 = new Tdws_User_Tracking_Report();
		$user_by_date->start_date     = strtotime( gmdate( 'Y-m-01', current_time( 'timestamp' ) ) );
		$user_by_date->end_date       = strtotime( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) );
		$user_by_date->chart_groupby  = 'day';
		$user_by_date->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date), DAY(posts.post_date)';

		$user_by_date->output_report();
		?>
	</div>
	<?php
}

// Include the custom list table class
include(plugin_dir_path(__FILE__) . 'class-tdws-product-list-table.php');


add_action( "wp_ajax_tdws_save_web_link", "tdws_save_web_link" );
add_action( "wp_ajax_nopriv_tdws_save_web_link", "tdws_save_web_link" );

function tdws_save_web_link() {

	$result_arr = array( 'type' => 'fail', 'msg' => 'Something Went Wrong' );

	if ( !wp_verify_nonce( $_POST['nonce'], "tdwsCustomNonce")) {
		$result_arr = array( 'type' => 'fail', 'msg' => 'No verify nonce' );
		wp_send_json( $result_arr );
	}   

	$post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;
	$tdws_web_link = isset($_POST['tdws_web_link']) ? tdws_remove_all_query_string( $_POST['tdws_web_link'] ) : 0;
	update_post_meta( $post_id, "tdws_web_link", $tdws_web_link );
	$result_arr = array( 'type' => 'success', 'msg' => 'Save Web Url Successfully...' );
	wp_send_json( $result_arr );

}


add_action( "wp_ajax_tdws_save_web_link_by_file", "tdws_save_web_link_by_file" );
add_action( "wp_ajax_nopriv_tdws_save_web_link_by_file", "tdws_save_web_link_by_file" );

function tdws_save_web_link_by_file() {

	$result_arr = array( 'type' => 'fail', 'msg' => 'Something Went Wrong', 'items' => array() );
	if ( !wp_verify_nonce( $_POST['nonce'], "tdwsCustomNonce")) {
		$result_arr = array( 'type' => 'fail', 'msg' => 'No verify nonce', 'items' => array() );
		wp_send_json( $result_arr );
	}   

	$web_link_items = isset($_POST['web_link_items']) ? $_POST['web_link_items'] : array();	
	$data_obj = array();
	if( $web_link_items ){
		foreach ( $web_link_items as $key => $value_item ) {
			$sku = isset($value_item[0]) ? $value_item[0] : '';
			$post_id = tdws_by_get_product_by_sku( $sku );
			if( $post_id ){
				$tdws_web_link = isset($value_item[1]) ? tdws_remove_all_query_string( $value_item[1], 1 ) : '';	
				update_post_meta( $post_id, "tdws_web_link", $tdws_web_link );
				$data_obj[] = array( 'type' => 'success', 'link_item' => $value_item );
			}else{
				$update_flag = 0;
				$sku = rtrim($sku, '/');
				if( $sku ){
					$sku_arr = explode( '/', $sku );	
					if( is_array($sku_arr) ){
						$product_slug = end( $sku_arr );	
						if( $product_slug ){
							$post_id = tdws_get_post_id_by_slug( $product_slug, 'product' );
							if( $post_id ){
								$tdws_web_link = isset($value_item[1]) ? tdws_remove_all_query_string( $value_item[1], 1 ) : '';	
								update_post_meta( $post_id, "tdws_web_link", $tdws_web_link );								
								$update_flag = 1;
							}
						}	
					}					
				}
				if( $update_flag ){
					$data_obj[] = array( 'type' => 'success', 'link_item' => $value_item );	
				}else{
					$data_obj[] = array( 'type' => 'error', 'link_item' => $value_item );
				}				
			}
		}
	}
	$result_arr = array( 'type' => 'success', 'msg' => 'Vendor Link Updated Successfully', 'items' => $data_obj );
	wp_send_json( $result_arr );
}


add_action( "wp_ajax_tdws_import_read_web_link_data", "tdws_import_read_web_link_data" );
add_action( "wp_ajax_nopriv_tdws_import_read_web_link_data", "tdws_import_read_web_link_data" );

function tdws_import_read_web_link_data() {

	require dirname(__FILE__).'/phpspreadsheet/vendor/autoload.php';
	$result_arr = array( 'type' => 'fail', 'msg' => 'Something Went Wrong' );

	if (isset($_POST['TDWSType']) && wp_verify_nonce($_POST['TDWSType'], 'importWebLinkNonce')) {

		$tdws_import_temp = isset($_FILES['tdws_import_file']['tmp_name']) ? $_FILES['tdws_import_file']['tmp_name'] : '';
		$tdws_import_file = isset($_FILES['tdws_import_file']['name']) ? $_FILES['tdws_import_file']['name'] : '';
		if( $tdws_import_file ){
			try {
				$tdws_import_ext = pathinfo( $tdws_import_file, PATHINFO_EXTENSION );
				if( 'csv' == $tdws_import_ext ) {     
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
				} else if('xls' == $tdws_import_ext) {     
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
				} else  {
					$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();	
				}   
				$spreadsheet = $reader->load( $tdws_import_temp );
				$productData = $spreadsheet->getActiveSheet()->toArray();
				$col_head = isset($productData[0]) ? $productData[0] : array();
				if( ( isset($col_head[0]) && $col_head[0] == 'SKU/URL' ) && ( isset($col_head[1]) && $col_head[1] == 'Vendor Links' ) ){
					if( is_array($productData) && count($productData) > 0 ){
						unset($productData[0]);
						$productData = array_values( $productData );
					}				
					$result_arr = array( 'type' => 'success', 'msg' => 'Read File Data Successfully', 'productData' => $productData );
				}else{
					$result_arr = array( 'type' => 'fail', 'msg' => 'Invalid file columns, Make them proper, follow below instructions' );	
				}				
			} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
				$result_arr = array( 'type' => 'fail', 'msg' => 'Invalid file data file' );
			}
		} 		
	}   
	wp_send_json( $result_arr );

}

function tdws_remove_all_query_string( $web_links = '', $via_file = 0 ) {
	$new_web_link = [];
	if (trim($web_links) != '') {

		if( $via_file == 0 ){
			$web_link_arr = explode(',', $web_links);
			if (count($web_link_arr) > 0) {
				foreach ($web_link_arr as $v_link) {
					$v_link_parts = explode('?', $v_link);
					$new_web_link[] = isset($v_link_parts[0]) ? $v_link_parts[0] : '';
				}
			}	
		}else{
			$web_link_arr = explode('?', $web_links);
			$new_web_link[] = isset($web_link_arr[0]) ? $web_link_arr[0] : '';
		}        
	}
	$new_web_link_str = implode(',', $new_web_link);
	return $new_web_link_str;
}


function tdws_by_get_product_by_sku( $sku ) {
	global $wpdb;
	$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );
	if ( $product_id ){
		return $product_id;
	}
	return 0;
}


// Add TDWS Vendor Link MetaBox Product Edit
add_action( "add_meta_boxes", "tdws_add_vendor_link_meta_box" , 99, 2 );

function tdws_add_vendor_link_meta_box(){
	add_meta_box( "tdws-order-tracking-box", __( "Vendor Link Box", 'tdws-product-inventory-list' ), 'tdws_vendor_link_box_html', array( "product" ), "normal", "high", null ); 
}

/**
 * Show meta box html
 *
 * @since    1.0.0
 */
function tdws_vendor_link_box_html( $post ){

	$post_id = $post->ID;
	$tdws_web_link = get_post_meta( $post_id, 'tdws_web_link', true );	
	?>		
	<div class="tdws-vendor-link-wrap">
		<label for="tdws_web_link"><strong><?php _e( 'Vendor Links', 'tdws-product-inventory-list' ); ?></strong></label>
		<textarea id="tdws_web_link" name="tdws_web_link" class="tdws_web_link"><?php echo $tdws_web_link; ?></textarea>
		<?php wp_nonce_field( 'tdws_vendor_link_save', 'tdws_vendor_link_save_field' ); ?>			
	</div>
	<?php	
}

add_action( 'save_post', 'save_tdws_vendor_link_box' );


function save_tdws_vendor_link_box( $post_id ) {

	if ( ! wp_verify_nonce( $_POST['tdws_vendor_link_save_field'], 'tdws_vendor_link_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['post_type'] ) && 'product' === $_POST['post_type'] ) {

		$tdws_web_link = isset($_POST['tdws_web_link']) ? tdws_remove_all_query_string( $_POST['tdws_web_link'], 0 ) : '';	
		update_post_meta( $post_id, "tdws_web_link", $tdws_web_link );

	}

}

function tdws_get_post_id_by_slug( $slug, $post_type = 'post' ) {
    // Use get_page_by_path to get the post object by slug
	$post = get_page_by_path($slug, OBJECT, $post_type);
	$p_id = isset($post->ID) ?  $post->ID : 0;
    // Return the ID if the post exists
	return $p_id;
}
