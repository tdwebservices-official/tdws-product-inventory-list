<?php
function tdws_post_taxonomy_analysis_page() {
	?>
	<div class="wrap">
		<h1><?php _e('TDWS Posts & Taxonomy Analysis', 'tdws-product-inventory-list' ); ?></h1>

		<div class="tdws-export-form-wrap">

			<?php 
   				tdws_display_post_types_and_taxonomies(); // Call the function to display table
			?>

		</div>
		
	</div>
	<?php
}

function tdws_display_post_types_and_taxonomies() {
    // Define the post types you want to target
    $target_post_types = array('post', 'product');

    $excludes_taxonomies = array( 'post_format', 'product_type', 'product_visibility', 'product_shipping_class' );
    
    foreach ($target_post_types as $post_type_name) {
        $post_type = get_post_type_object($post_type_name);
        
        if ($post_type) {
            // Get taxonomies for the current post type
            $taxonomies = get_object_taxonomies($post_type_name, 'objects');
            
            echo '<h2>' . $post_type->label . ' (' . $post_type->name . ')</h2>';
            echo '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse;">';
            echo '<thead><tr><th>Taxonomy</th><th>Export</th></tr></thead>';
            echo '<tbody>';
            
            // Display taxonomies and export button
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy) {

                	if( in_array($taxonomy->name, $excludes_taxonomies) ){
                		continue;
                	}

                    echo '<tr>';
                    echo '<td>' . $taxonomy->label . ' (' . $taxonomy->name . ')</td>';
                    
                    // Export button for each taxonomy
                    echo '<td>';
                    echo '<form method="GET">';
                    echo '<input type="hidden" name="taxonomy_export" value="' . esc_attr($taxonomy->name) . '">';
                    echo '<input type="submit" value="Export Data" class="button button-primary">';
                    echo '</form>';
                    echo '</td>';

                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="2">No taxonomies found</td></tr>';
            }

            echo '</tbody>';
            echo '</table><br>';
        }
    }
}
function export_taxonomy_terms_to_csv() {
    if (isset($_GET['taxonomy_export'])) {
        $taxonomy_name = sanitize_text_field($_GET['taxonomy_export']);

        // Get all terms for the selected taxonomy, including hierarchy
        $terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
        ));

        $parent_terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'parent' => 0,
            'hide_empty' => false,
        ));

        $only_level_taxonomies = array('category', 'product_cat');
        $output = fopen('php://output', 'w');
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $taxonomy_name . '_terms.csv"');

        // Write the header row for the CSV
        if (in_array($taxonomy_name, $only_level_taxonomies)) {
            fputcsv($output, array('Level 1', 'Level 2', 'Level 3', 'Count'));
        } else {
            fputcsv($output, array('Term Name', 'Count'));
        }

        if (!is_wp_error($parent_terms) && !empty($parent_terms)) {
            // Loop through terms and generate CSV rows
            foreach ($parent_terms as $term) {
                // For tags or non-hierarchical taxonomies, directly write to CSV
                if (!in_array($taxonomy_name, $only_level_taxonomies) ) {
                    // If it's a tag or a non-hierarchical term, output it directly
                    fputcsv($output, array($term->name, $term->count));
                } else {
                    // Otherwise, handle hierarchical terms
                    tdws_generate_term_row($term, $terms, $output, 1, 3); // Start at level 1
                }
            }
        } else {
            // If no terms found, write a blank row
            fputcsv($output, array(''));
        }
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'export_taxonomy_terms_to_csv');

function tdws_generate_term_row($term, $terms, $output, $level, $max_levels) {
    // Write the current term directly for hierarchical taxonomies
    $parent_chain = array_fill(0, $max_levels, ''); // Create empty placeholders for all levels

    $current_term = $term;
    $current_level = $level;

    // Traverse up the parent chain for hierarchical taxonomies
    while ($current_term && $current_term->parent != 0 && $current_level > 1) {
        $parent_term = get_term($current_term->parent, $current_term->taxonomy);
        $parent_chain[$current_level - 2] = $parent_term->name; // Store parent name
        $current_term = $parent_term;
        $current_level--;
    }

    // Place the current term at its correct level in the hierarchy
    $parent_chain[$level - 1] = $term->name;

    // Write the term data row, ensuring we only include up to 3 levels
    fputcsv($output, array_slice(array_merge($parent_chain, array($term->count)), 0, $max_levels + 1));

    // Recursively process any child terms if we're still within the max levels
    if ($level < $max_levels) {
        foreach ($terms as $child_term) {
            if ($child_term->parent == $term->term_id) {
                tdws_generate_term_row($child_term, $terms, $output, $level + 1, $max_levels);
            }
        }
    }
}
