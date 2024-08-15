<?php
if (!class_exists('TDWS_Product_Inventory_List_Table')) {

    class TDWS_Product_Inventory_List_Table extends WP_List_Table {

        private $per_page_options = array( 10, 25, 50, 100, 200, 500, 1000 );


        public function __construct() {
            parent::__construct(array(
                'singular' => 'tdws-product-inventory-list',
                'plural'   => 'tdws-product-inventory-list',
                'ajax'     => false,
            ));
        }

        // Prepare items for display
        public function prepare_items() {
            $per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 10;
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;

            // Handle search query
            $sku_search = isset($_REQUEST['sku_search']) ? sanitize_text_field($_REQUEST['sku_search']) : '';
            $title_search = isset($_REQUEST['title_search']) ? sanitize_text_field($_REQUEST['title_search']) : '';

            $query_args = array(
                'post_type'   => 'product',
                'post_status' => 'any',
                'posts_per_page' => $per_page, // Number of items per page
                'paged'       => $current_page,
                'orderby'     => $this->get_orderby(),
                'order'       => $this->get_order(),
                'offset'      => $offset,                
                'meta_query'  => $meta_query
            );

            if (!empty($sku_search)) {
                $query_args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_sku',
                         'value'   => $sku_search,
                        'compare' => 'LIKE',
                    )
                );

            }
            if( $title_search ){
                $query_args['search_prod_title'] = $title_search;
                add_filter( 'posts_where', array( $this, 'tdws_product_title_filter') , 99, 2 );
            }
            $query = new WP_Query($query_args);
            if( $title_search ){
                remove_filter( 'posts_where', array( $this, 'tdws_product_title_filter') , 99, 2 );
            }


            $data = $query->posts;

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);
            $this->items = $data;

            $total_items = $query->found_posts;

            $this->set_pagination_args(array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil($total_items / $per_page)
            ));
        }

        // Search By Title
        public function tdws_product_title_filter( $where, &$wp_query ){
            global $wpdb;
            if ( $search_term = $wp_query->get( 'search_prod_title' ) ) {
                $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( $wpdb->esc_like( $search_term ) ) . '%\'';
            }
            return $where;
        }

        // Define the columns
        public function get_columns() {
            return array(
                'cb'         => '<input type="checkbox" />',
                'id'         => 'ID',                
                'sku'        => 'SKU',     
                'name'       => 'Name',           
                'tdws_web_link'        => 'Web Link',                
                'status'     => 'Status'
            );
        }

        // Define sortable columns
        protected function get_sortable_columns() {
            return array(
                'id'   => array('ID', true),                
                'sku'  => array('meta_sku', false),
                'name' => array('post_title', false),
            );
        }

        // Display checkbox column
        public function column_cb($item) {
            return sprintf(
                '<input type="checkbox" name="product[]" value="%s" />', $item->ID
            );
        }

        // Display a column value
        public function column_default($item, $column_name) {
            global $wpdb;

            $tdws_web_link = get_post_meta($item->ID, 'tdws_web_link', true);
            $sku = get_post_meta($item->ID, '_sku', true);
            $status = $item->post_status;

            switch ($column_name) {
                case 'id':
                    return $item->ID;
                case 'sku':
                    return $sku;
                case 'name':
                    return $item->post_title;                
                case 'tdws_web_link':
                    return '<span class="tdws-weblink-box"><textarea name="weblink['.$item->ID.']" rows="5" cols="20" >'.$tdws_web_link.'</textarea><button data-id="'.$item->ID.'" type="button" class="button tdws_save_web_link">Update</button><span class="tdws-web-loader tdws-hide"><img src="'.esc_url(plugin_dir_url( __FILE__ ).'/images/loader.gif').'"/></span></span>';
                case 'status':
                    return ucfirst($status);
                default:
                    return print_r($item, true);
            }
        }

        // Get orderby parameter for WP_Query
        private function get_orderby() {
            return !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'ID';
        }

        // Get order parameter for WP_Query
        private function get_order() {
            return !empty($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'asc';
        }

        // Render the search form
        public function search_box($text, $input_id) {
            if (empty($_REQUEST['sku_search']) && empty($_REQUEST['title_search']) && !$this->has_items()) {
                return;
            }

            $input_id_sku = $input_id . '-sku-search-input';
            $input_id_title = $input_id . '-title-search-input';

            echo "<div class='tdws-search-box'>";
echo '<p class="search-box">';
            submit_button($text, '', '', false, array('id' => 'search-submit'));
            echo '</p>';
            echo '<p class="search-box">';
            echo '<label class="screen-reader-text" for="' . esc_attr($input_id_sku) . '">' . esc_html__('Search SKU:', 'text-domain') . ':</label>';
            echo '<input type="search" id="' . esc_attr($input_id_sku) . '" name="sku_search" value="' . esc_attr(isset($_REQUEST['sku_search']) ? wp_unslash($_REQUEST['sku_search']) : '') . '" placeholder="Search SKU" />';
            echo '</p>';

            echo '<p class="search-box">';
            echo '<label class="screen-reader-text" for="' . esc_attr($input_id_title) . '">' . esc_html__('Search Title:', 'text-domain') . ':</label>';
            echo '<input type="search" id="' . esc_attr($input_id_title) . '" name="title_search" value="' . esc_attr(isset($_REQUEST['title_search']) ? wp_unslash($_REQUEST['title_search']) : '') . '" placeholder="Search Title" />';
            echo '</p>';

            $this->per_page_dropdown();

            echo "</div>";
        }

        // Override display method to include search form
        public function display() {

            $this->search_box('Search Products', 'search_id');
            parent::display();
           
        }


        private function per_page_dropdown() {
            $current_per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 10;

            echo '<p class="search-box" style="margin-right: 25px; ">';
            echo '<label for="per-page-dropdown" >Items per page</label>';
            echo '<select id="per-page-dropdown" name="per_page" onchange="this.form.submit();">';

            foreach ($this->per_page_options as $option) {
                $selected = ($option == $current_per_page) ? ' selected="selected"' : '';
                echo "<option value=\"" . esc_attr($option) . "\"" . $selected . ">$option</option>";
            }

            echo '</select>';
            echo '</p>';
        }
    }
}
