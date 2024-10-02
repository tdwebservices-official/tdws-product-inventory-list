<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://tdwebservices.com
 * @since      1.1.0
 *
 * @package    Tdws_Order_Tracking_System
 * @subpackage Tdws_Order_Tracking_System/admin/includes
 */

include_once(WC()->plugin_path().'/includes/admin/reports/class-wc-admin-report.php');

/**
 * Tdws_Order_Tracking_17track_Report
 */
class Tdws_User_Tracking_Report extends WC_Admin_Report {

	/**
	 * Chart colors.
	 *
	 * @var array
	 */
	public $chart_colours = array();

	/**
	 * The report data.
	 *
	 * @var stdClass
	 */
	private $report_data;

	/**
	 * Get report data.
	 *
	 * @return stdClass
	 */
	public function get_report_data() {
		$this->query_report_data();		
		return $this->report_data;
	}

	/**
	 * Get all data needed for this report and store in the class.
	 */
	private function query_report_data() {
		$this->report_data = new stdClass();
		global $wpdb;

		$this->report_data->tdws_user_data = $this->report_data->tdws_user_status_data = array();

		$args = array(
			'role'    => 'shop_manager',
			'orderby' => 'user_nicename',
			'order'   => 'ASC'
		);
		$users = get_users( $args );

		foreach ( $users as $user ) {
			$this->report_data->tdws_user_status_data[$user->ID] = 0;
			$this->report_data->tdws_user_data[$user->ID] = $user;
		}

		$tdws_status_count_data = $tdws_user_status_data = array();

		$table_name = $wpdb->base_prefix.'posts';

		$new_chart_data = array();
		if( $this->report_data->tdws_user_status_data ){
			foreach ( $this->report_data->tdws_user_status_data as $c_key => $c_value ) {

				$chart_result = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(id) as track_cnt, DATE(post_date) as post_date FROM $table_name WHERE post_status = 'publish' AND post_author  = ".$c_key." GROUP BY post_author" ) );	


				$all_tdws_rearrage_data = $this->prepare_chart_data( $chart_result, 'post_date', 'track_cnt', $this->chart_interval, $this->start_date, $this->chart_groupby );

				$new_chart_data[ $c_key ] = array_values( $all_tdws_rearrage_data );
				$total_count = 0;
				if( $all_tdws_rearrage_data ){
					foreach ( $all_tdws_rearrage_data as $key => $c_value ) {
						$track_cnt = isset($c_value[1]) ? $c_value[1] : 0;
						$total_count = (int)$total_count + (int)$track_cnt;
					}
				}

				$tdws_status_count_data[ $c_key ] = $total_count;
			}
		}

		$this->report_data->tdws_user_status_data = $new_chart_data;
		$this->report_data->tdws_status_count_data = $tdws_status_count_data;

		// 3rd party filtering of report data
		$this->report_data = apply_filters( 'tdws_user_tracking_status_admin_report_data', $this->report_data );
	}

	/**
	 * Get the legend for the main chart sidebar.
	 *
	 * @return array
	 */
	public function get_chart_legend() {
		$legend = array();

		$data   = $this->get_report_data();	

		if( is_array($this->report_data->tdws_user_data) && count($this->report_data->tdws_user_data) > 0 ){
			foreach ( $this->report_data->tdws_user_data as $t_key => $t_value ) { 
				$tdws_count = isset($this->report_data->tdws_status_count_data[$t_key]) ? $this->report_data->tdws_status_count_data[$t_key] : 0;
				$legend[] = array(
					'title'            => sprintf(
						/* translators: %s: total orders */
						__( '#'.$t_key.' ', 'woocommerce' ).esc_html( $t_value->display_name ).'<strong>'.esc_html( $tdws_count ).'</strong>',						
					),
					'color'            => isset($this->chart_colours[$t_key]) ? $this->chart_colours[$t_key] : '#000',
					'highlight_series' => 1,
				);
			}
		}


		return $legend;
	}

	/**
	 * Output the report.
	 */
	public function output_report() {

		$ranges = array(
			'year'       => __( 'Year', 'woocommerce' ),
			'last_month' => __( 'Last month', 'woocommerce' ),
			'month'      => __( 'This month', 'woocommerce' ),
			'7day'       => __( 'Last 7 days', 'woocommerce' ),
		);


		$this->chart_colours = array();

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ), true ) ) {
			$current_range = '7day';
		}

		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );

		include WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php';
	}

	/**
	 * Output an export link.
	 */
	public function get_export_button() {
			$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<a
			href="#"
			download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>.csv"
			class="export_csv"
			data-export="chart"
			data-xaxes="<?php esc_attr_e( 'Date', 'woocommerce' ); ?>"
			data-exclude_series="2"
			data-groupby="<?php echo esc_attr( $this->chart_groupby ); ?>"
			>
			<?php esc_html_e( 'Export CSV', 'woocommerce' ); ?>
		</a>
		<?php
	}

	/**
	 * Round our totals correctly.
	 *
	 * @param array|string $amount Chart total.
	 *
	 * @return array|string
	 */
	private function round_chart_totals( $amount ) {
		if ( is_array( $amount ) ) {
			return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
		} else {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}
	}

	/**
	 * Get the main chart.
	 */
	public function get_main_chart() {
		global $wp_locale, $wpdb;		

		$table_name = $wpdb->base_prefix.'tdws_order_tracking_status';

		// Prepare data for report.
		$chart_data = $this->report_data->tdws_user_status_data;
		// 3rd party filtering of report data.
		$chart_data = apply_filters( 'tdws_tracking_status_admin_report_chart_data', $chart_data );

		$tdws_series = array();
		if( is_array($this->report_data->tdws_user_data) && count($this->report_data->tdws_user_data) > 0 ){
			foreach ( $this->report_data->tdws_user_data as $t_key => $t_value ) {
				$fillColor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
				$tdws_series[] = array(					
					"label" => __( '#'.$t_key.' ', 'woocommerce' ).esc_html( $t_value->display_name ),
					"data" => isset($chart_data[$t_key]) ? $chart_data[$t_key] : array(),
					"color" => $fillColor,
					"bars" => array(  'fillColor' => $fillColor, 'fill' => true, 'show' => true, 'lineWidth' => 0, 'barWidth' => 1 * 0.5, 'align' => 'center' ),
					"points" => array(  'show' => true, 'radius' => 5, 'lineWidth' => 3, 'fillColor' => '#fff', 'fill' => true ),
					"lines" => array(  'show' => true, 'lineWidth' => 4, 'fillColor' => $fillColor, 'fill' => false ),
					"shadowSize" => 0,
					"enable_tooltip" => true,
					"append_tooltip" => __( '#'.$t_key.' ', 'woocommerce' ).esc_html( $t_value->display_name ),
					"hoverable" => true
				);
			}
		}

		$chart_data = wp_json_encode( $chart_data );

		?>
		<div class="chart-container">
			<div class="chart-placeholder main"></div>
		</div>
		<script type="text/javascript">

			var main_chart;

			jQuery(document).ready( function() {

				var order_data = JSON.parse( decodeURIComponent( '<?php echo rawurlencode( $chart_data ); ?>' ) );
				var drawGraph = function( highlight ) {
					var series = '<?php echo json_encode($tdws_series); ?>';
					series = jQuery.parseJSON(series);
					var plotSeries = jQuery.extend(true, [], series);
					if ( highlight !== 'undefined' && plotSeries[ highlight ] ) {
						highlight_series = plotSeries[ highlight ];

						highlight_series.color = '#9c5d90';

						if ( highlight_series.bars ) {
							highlight_series.bars.fillColor = '#9c5d90';
						}

						if ( highlight_series.lines ) {
							highlight_series.lines.lineWidth = 5;
						}
					}

					main_chart = jQuery.plot(
						jQuery('.chart-placeholder.main'),
						plotSeries,
						{
							legend: {
								show: false
							},
							grid: {
								color: '#aaa',
								borderColor: 'transparent',
								borderWidth: 0,
								hoverable: true
							},
							xaxes: [ {
								color: '#aaa',
								position: "bottom",
								tickColor: 'transparent',
								mode: "time",
								timeformat: "<?php echo ( 'day' === $this->chart_groupby ) ? '%d %b' : '%b'; ?>",
								monthNames: JSON.parse( decodeURIComponent( '<?php echo rawurlencode( wp_json_encode( array_values( $wp_locale->month_abbrev ) ) ); ?>' ) ),
								tickLength: 1,
								minTickSize: [1, "<?php echo esc_js( $this->chart_groupby ); ?>"],
								font: {
									color: "#aaa"
								}
							} ],
							yaxes: [
							{
								min: 0,
								minTickSize: 1,
								tickDecimals: 0,
								color: '#d4d9dc',
								font: { color: "#aaa" }
							},
							{
								position: "right",
								min: 0,
								tickDecimals: 2,
								alignTicksWithAxis: 1,
								color: 'transparent',
								font: { color: "#aaa" }
							}
							],
						}
						);

					jQuery('.chart-placeholder').trigger( 'resize' );
				}


				drawGraph();

				jQuery('.highlight_series').on( 'mouseenter',
					function() {
						drawGraph( jQuery(this).data('series') );
					} ).on( 'mouseleave',
					function() {
						drawGraph();
					}
					);
				});
			</script>
			<?php
		}
	}