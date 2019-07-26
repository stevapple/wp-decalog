<?php
/**
 * Events list
 *
 * Lists all events.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace Decalog\Plugin\Feature;

use Decalog\System\Date;
use Decalog\System\Option;
use Decalog\System\Role;
use Decalog\System\Timezone;
use Decalog\Log;
use Monolog\Logger;


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Define the events list functionality.
 *
 * Lists all events.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Events extends \WP_List_Table {

	/**
	 * The available events logs.
	 *
	 * @since    1.0.0
	 * @var      array    $logs    The loggers list.
	 */
	private static $logs = [];

	/**
	 * The columns always shown.
	 *
	 * @since    1.0.0
	 * @var      array    $standard_columns    The columns always shown.
	 */
	private static $standard_columns = [];

	/**
	 * The columns which may be shown.
	 *
	 * @since    1.0.0
	 * @var      array    $extra_columns    The columns which may be shown.
	 */
	private static $extra_columns = [];

	/**
	 * The columns which must be shown to the current user.
	 *
	 * @since    1.0.0
	 * @var      array    $extra_columns    The columns which must be shown to the current user.
	 */
	private static $user_columns = [];

	/**
	 * The events types icons.
	 *
	 * @since    1.0.0
	 * @var      array    $icons    The icons list.
	 */
	private $icons = [];

	private $limit        = 25;
	private $logger       = null;
	private $filters      = [];
	private $force_siteid = null;
	private $name         = null;



	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'event',
				'plural'   => 'events',
				'ajax'     => true,
			]
		);
	}

	/**
	 * Default column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @param   string  $column_name    The name of the current rendered column.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * "event" column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_event($item){
		$icon = '<img style="width:18px;float:left;padding-right:6px;" src="' . EventTypes::$icons[$item['level']] . '" />';
		//$name = sprintf(esc_html__('%1$s : %2$s (%3$s)', 'decalog'), strtoupper($item['channel']), $item['component'], $item['class']);
		$name = ChannelTypes::$channel_names[strtoupper($item['channel'])] . ' - ' . ucfirst($item['level']);
		$result = $icon . $name;
		$result .= '<br /><span style="color:silver">' . sprintf(esc_html__('Event #%1$s / %2$s code %3$s', 'decalog'), $item['id'], ucfirst($item['level']), $item['code'] ) . '</span>';
		return $result;
	}

	/**
	 * "component" column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_component($item){
		$name = $item['component'] . ' <span style="color:silver">' . $item['version'] . '</span>';
		$result = $name . '<br /><span style="color:silver">' . $item['class'] . '</span>';
		return $result;
	}

	/**
	 * "time" column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_time($item){
		$result = Date::get_date_from_mysql_utc($item['timestamp'], Timezone::get_wp()->getName(), 'Y-m-d H:i:s') ;
		$result .='<br /><span style="color:silver">' . Date::get_positive_time_diff_from_mysql_utc($item['timestamp']) . '</span>';
		return $result;
	}

	/**
	 * "user" column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_user($item){
		$result = $item['user_name'];
		//$result = Date::get_date_from_mysql_utc($item['timestamp'], Timezone::get_wp()->getName(), 'Y-m-d H:i:s') ;
		//$result .='<br /><span style="color:silver">' . Date::get_positive_time_diff_from_mysql_utc($item['timestamp']) . '</span>';
		return $result;
	}

	/**
	 * "ip" column formatter.
	 *
	 * @param   object  $item   The current item to render.
	 * @return  string  The cell formatted, ready to print.
	 * @since   1.0.0
	 */
	protected function column_ip($item){
		$result = $item['remote_ip'];
		//$result .='<br /><span style="color:silver">' . Date::get_positive_time_diff_from_mysql_utc($item['timestamp']) . '</span>';
		return $result;
	}

	public function get_columns() {
		if (is_multisite() && Role::LOCAL_ADMIN !== Role::admin_type()) {
			$columns = [
				'event'       => esc_html__( 'Event', 'decalog' ),
				'component'   => esc_html__( 'Component', 'decalog' ),
				'time'        => esc_html__( 'Time', 'decalog' ),
				'site'        => esc_html__( 'Site', 'decalog' ),
				'user'        => esc_html__( 'User', 'decalog' ),
				'ip'          => esc_html__( 'IP', 'decalog' ),
				'message'     => esc_html__( 'Message', 'decalog' ),
			];
		} else {
			$columns = [
				'event'       => esc_html__( 'Event', 'decalog' ),
				'component'   => esc_html__( 'Component', 'decalog' ),
				'time'        => esc_html__( 'Time', 'decalog' ),
				'user'        => esc_html__( 'User', 'decalog' ),
				'ip'          => esc_html__( 'IP', 'decalog' ),
				'message'     => esc_html__( 'Message', 'decalog' ),
			];
		}

		return $columns;
	}

	protected function get_hidden_columns() {
		return [];
	}

	protected function get_sortable_columns() {
		return [];
	}

	public function get_bulk_actions() {
		return [];
	}

	protected function init_values() {
		$this->limit = filter_input( INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT );
		if ( ! $this->limit ) {
			$this->limit = 25;
		}
		$this->force_siteid = null;
		$this->logger       = filter_input( INPUT_GET, 'logger_id', FILTER_SANITIZE_STRING );
		if ( $this->logger ) {
			$this->set_level_access();
		} else {
			$this->set_first_available();
		}
		$this->filters = [];
		$level = filter_input( INPUT_GET, 'level', FILTER_SANITIZE_STRING );
		if ($level && array_key_exists(strtolower($level), EventTypes::$levels) && 'debug' !== strtolower($level)) {
			$this->filters['level'] = strtolower($level);
		}
		/*
		if (isset($_GET['limit'])) {
			$this->limit = intval($_GET['limit']);
			if (!$this->limit) {
				$this->limit = 25;
			}
		}
		if (isset($_GET['level'])) {
			$this->level = strtolower(sanitize_text_field(urldecode($_GET['level'])));
			if (!array_key_exists($this->level, Logger::$severity)) {
				$this->level = '';
			}
			else {
				if ($this->level != '') {
					$this->filters['level'] = $this->level;
				}
			}
		}
		if (isset($_GET['station'])) {
			$this->station = sanitize_text_field(urldecode($_GET['station']));
			if (!array_key_exists($this->station, $this->stations)) {
				$this->station = '';
			}
			else {
				if ($this->station != '') {
					$this->filters['station'] = $this->station;
				}
			}
		}
		if (isset($_GET['system'])) {
			$this->system = sanitize_text_field(urldecode($_GET['system']));
			if (!array_key_exists($this->system, $this->systems)) {
				$this->system = '';
			}
			else {
				if ($this->system != '') {
					$this->filters['system'] = $this->system;
				}
			}
		}
		if (isset($_GET['service'])) {
			$this->service = sanitize_text_field(urldecode($_GET['service']));
			if (!array_key_exists($this->service, $this->services)) {
				$this->service = '';
			}
			else {
				if ($this->service != '') {
					$this->filters['service'] = $this->service;
				}
			}
		}*/
		if ( $this->force_siteid ) {
			$this->filters['site_id'] = $this->force_siteid;
		}
	}

	public function prepare_items() {
		$this->init_values();
		$columns               = $this->get_columns();
		$hidden                = $this->get_hidden_columns();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$current_page          = $this->get_pagenum();
		$total_items           = $this->get_count();
		$this->items           = $this->get_list( ( $current_page - 1 ) * $this->limit, $this->limit );
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $this->limit,
				'total_pages' => ceil( $total_items / $this->limit ),
			]
		);
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @param string $which Position of extra control.
	 * @since 1.0.0
	 */
	protected function display_tablenav( $which ) {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear" />';
		echo '</div>';
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which Position of extra control.
	 * @since 1.0.0
	 */
	public function extra_tablenav( $which ) {
		$list = $this;
		$args = compact( 'list' );
		foreach ( $args as $key => $val ) {
			$$key = $val;
		}
		if ( 'top' === $which ) {
			include DECALOG_ADMIN_DIR . 'partials/decalog-admin-view-events-top.php';
		}
		if ( 'bottom' === $which ) {
			include DECALOG_ADMIN_DIR . 'partials/decalog-admin-view-events-bottom.php';
		}
	}

	/**
	 * Get the page url with args.
	 *
	 * @return  string  The url.
	 * @since 1.0.0
	 */
	public function get_page_url() {
		$args = [];
		$args['page'] = 'decalog-viewer';
		$args['logger_id'] = $this->logger;
		if (count($this->filters) > 0) {
			foreach ($this->filters as $key => $filter) {
				if ($filter != '') {
					$args[$key] = $filter;
				}
			}
		}
		if ($this->limit != 25) {
			$args['limit'] = $this->limit;
		}
		$url = add_query_arg($args, admin_url('tools.php'));
		return $url;
	}

	/**
	 * Get available views line.
	 *
	 * @since 1.0.0
	 */
	public function get_views() {
		$filters = $this->filters;
		$level = array_key_exists('level', $this->filters) ? $this->filters['level'] : '';
		unset($this->filters['level']);
		$s1 = '<a href="' . $this->get_page_url() . '"' . ( $level === '' ? ' class="current"' : '') . '>' . __('All', 'live-weather-station') . ' <span class="count">(' . $this->get_count() . ')</span></a>';
		$this->filters['level'] = 'notice';
		$s2 = '<a href="' . $this->get_page_url() . '"' . ( $level === 'notice' ? ' class="current"' : '') . '>' . __('Notices &amp; beyond', 'live-weather-station') . ' <span class="count">(' . $this->get_count() . ')</span></a>';
		$this->filters['level'] = 'error';
		$s3 = '<a href="' . $this->get_page_url() . '"' . ( $level === 'error' ? ' class="current"' : '') . '>' . __('Errors &amp; beyond', 'live-weather-station') . ' <span class="count">(' . $this->get_count() . ')</span></a>';
		$status_links = array( 'all' => $s1, 'notices' => $s2, 'errors' => $s3);
		$this->filters = $filters;
		return $status_links;
	}

	/**
	 * Get the available events logs.
	 *
	 * @return  array   The list of available events logs.
	 * @since    1.0.0
	 */
	public function get_loggers() {
		return self::$logs;
	}

	/**
	 * Get the current events log id.
	 *
	 * @return  string   The current events log id.
	 * @since    1.0.0
	 */
	public function get_current_Log_id() {
		return $this->logger;
	}

	/**
	 * Get available lines breakdowns.
	 *
	 * @since 1.0.0
	 */
	public function get_line_number_select() {
		$_disp  = [ 25, 50, 100, 250, 500 ];
		$result = [];
		foreach ( $_disp as $d ) {
			$l             = [];
			$l['value']    = $d;
			$l['text']     = sprintf( esc_html__( 'Show %d lines per page', 'decalog' ), $d );
			$l['selected'] = ( $d == $this->limit ? 'selected="selected" ' : '' );
			$result[]      = $l;
		}
		return $result;
	}

	/**
	 * Set the level access to an events log.
	 *
	 * @since    1.0.0
	 */
	private function set_level_access() {
		$this->force_siteid = null;
		$id                 = $this->logger;
		$this->logger       = null;
		foreach ( self::$logs as $log ) {
			if ( $id === $log['id'] ) {
				$this->logger = $id;
				if ( array_key_exists( 'limit', $log ) ) {
					$this->force_siteid = $log['limit'];
				}
			}
		}
	}

	/**
	 * Set the level access to an events log.
	 *
	 * @since    1.0.0
	 */
	private function set_first_available() {
		$this->force_siteid = null;
		$this->logger       = null;
		foreach ( self::$logs as $log ) {
			$this->force_siteid = $log['limit'];
			$this->logger       = $log['id'];
			break;
		}
	}

	/**
	 * Get list of logged errors.
	 *
	 * @param integer $offset The offset to record.
	 * @param integer $rowcount Optional. The number of rows to return.
	 * @return array An array containing the filtered logged errors.
	 * @since 3.0.0
	 */
	protected function get_list( $offset = null, $rowcount = null ) {
		$result = [];
		$limit  = '';
		if ( ! is_null( $offset ) && ! is_null( $rowcount ) ) {
			$limit = 'LIMIT ' . $offset . ',' . $rowcount;
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'decalog_' . str_replace( '-', '', $this->logger );
		$sql        = 'SELECT * FROM ' . $table_name . ' ' . $this->get_where_clause() . ' ORDER BY id DESC ' . $limit;
		// phpcs:ignore
		$query = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $query as $val ) {
			$result[] = (array) $val;
		}
		return $result;
	}

	/**
	 * Count logged errors.
	 *
	 * @return integer The count of the filtered logged errors.
	 * @since 3.0.0
	 */
	protected function get_count() {
		$result = 0;
		if ( $this->logger ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'decalog_' . str_replace( '-', '', $this->logger );
			$sql        = 'SELECT COUNT(*) as CNT FROM ' . $table_name . ' ' . $this->get_where_clause();
			// phpcs:ignore
			$cnt = $wpdb->get_results( $sql, ARRAY_A );
			if ( count( $cnt ) > 0 ) {
				if ( array_key_exists( 'CNT', $cnt[0] ) ) {
					$result = $cnt[0]['CNT'];
				}
			}
		}
		return $result;
	}

	/**
	 * Get "where" clause for log table.
	 *
	 * @return string The "where" clause.
	 * @since 1.0.0
	 */
	private function get_where_clause() {
		$result = '';
		$w      = [];
		foreach ( $this->filters as $key => $filter ) {
			if ( $filter ) {
				if ( $key == 'level' ) {
					$l =[];
					foreach ( EventTypes::$levels as $str => $val ) {
						if ( EventTypes::$levels[$filter] <=  $val ) {
							$l[] = "'" . $str . "'";
						}
					}
					$w[] = $key . ' IN (' . implode( ',', $l ) . ')';
				} else {
					$w[] = $key . '="' . $filter . '"';
				}
			}
		}
		if ( count( $w ) > 0 ) {
			$result = 'WHERE (' . implode( ' AND ', $w ) . ')';
		}
		return $result;
	}

	/**
	 * Save the screen option setting.
	 *
	 * @param string $status The default value for the filter. Using anything other than false assumes you are handling saving the option.
	 * @param string $option The option name.
	 * @param array  $value  Whatever option you're setting.
	 */
	public static function save_screen_option( $status, $option, $value ) {
		if ( isset( $_POST['wp_screen_options_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_screen_options_nonce'] ) ), 'wp_screen_options_nonce' ) ) {
			if ( 'decalog_options' === $option ) {
				$value = filter_input( INPUT_POST, 'decalog', FILTER_FORCE_ARRAY );//isset( $_POST['decalog'] ) && is_array( $_POST['decalog'] ) ? $_POST['decalog'] : []; // WPCS: Sanitization ok.
				$user = wp_get_current_user();
				update_user_meta( $user->ID, $option, $value );
				$value = false;
			}
		}
		return $value;
	}

	/**
	 * Get the column options checkboxes.
	 *
	 * @return string The HTML code to append.
	 * @since 1.0.0
	 */
	public static function get_column_options() {
		$result = '';
		foreach ( self::$extra_columns as $key=>$extra_column ) {
			$result .= '<label for="decalog_' . $key . '" ><input name="decalog[' . $key . ']" type="checkbox" id="decalog_' . $key . '" ' . (in_array($key, self::$user_columns) ? ' checked="checked"' : '') . ' />' . $extra_column . '</label>';
		}
		return $result;
	}

	/**
	 * Append custom panel HTML to the "Screen Options" box of the current page.
	 * Callback for the 'screen_settings' filter.
	 *
	 * @param string $current Current content.
	 * @param \WP_Screen $screen Screen object.
	 * @return string The HTML code to append to "Screen Options".
	 * @since 1.0.0
	 */
	public static function display_screen_settings($current, $screen){
		if(!is_object($screen) || 'tools_page_decalog-viewer' !== $screen->id ) {
			return $current;
		}
		$current .= '<fieldset>';
		$current .= '<input type="hidden" name="wp_screen_options_nonce" value="' . wp_create_nonce('wp_screen_options_nonce') . '" />';
		$current .= '<legend>' . esc_html__('Extra columns', 'decalog') . '</legend>';
		$current .= '<div class="metabox-prefs">';
		$current .= '<div><input type="hidden" name="wp_screen_options[option]" value="decalog_options"></div>';
		$current .= '<div><input type="hidden" name="wp_screen_options[value]" value="yes"></div>';
		$current .= '<div class="decalog_custom_fields">' . self::get_column_options() . '</div>';
		$current .= '</div>';
		$current .= get_submit_button( __( 'Apply', 'decalog' ), 'primary', 'screen-options-apply' );
		return $current ;
	}

	/**
	 * Adds the extra-columns options.
	 *
	 * @since 1.0.0
	 */
	public static function add_column_options() {
		$screen = get_current_screen();
		if(!is_object($screen) || 'tools_page_decalog-viewer' !== $screen->id ) {
			return;
		}
		foreach ( self::$extra_columns as $key=>$extra_column ) {
			add_screen_option( 'decalog_' . $key, ['option' => $extra_column, 'value' => false ]);
		}
	}

	/**
	 * Initialize the meta class and set its columns properties.
	 *
	 * @since    1.0.0
	 */
	private static function load_columns() {
		self::$standard_columns = [];
		self::$standard_columns['event'] = esc_html__( 'Event', 'decalog' );
		self::$standard_columns['time'] = esc_html__( 'Time', 'decalog' );
		self::$standard_columns['message'] = esc_html__( 'Message', 'decalog' );
		self::$extra_columns = [];
		self::$extra_columns['component'] = esc_html__( 'Component', 'decalog' );
		self::$extra_columns['site'] = esc_html__( 'Site', 'decalog' );
		self::$extra_columns['user'] = esc_html__( 'User', 'decalog' );
		self::$extra_columns['ip'] = esc_html__( 'IP', 'decalog' );
		$user_meta = get_user_meta( get_current_user_id() );
		error_log(print_r($user_meta, true));
		self::$user_columns = [];
		if ( $user_meta ) {
			foreach (self::$extra_columns as $key=>$extra_column) {
				if (array_key_exists( $key, $user_meta )) {
					self::$user_columns[] = $key;
				}
			}
		}
	}

	/**
	 * Initialize the meta class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		self::$logs = [];
		foreach ( Option::get( 'loggers' ) as $key => $logger ) {
			if ( 'WordpressHandler' === $logger['handler'] ) {
				if ( array_key_exists( 'configuration', $logger ) ) {
					if ( array_key_exists( 'local', $logger['configuration'] ) ) {
						$local = $logger['configuration']['local'];
					} else {
						$local = false;
					}

					if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() || ( Role::LOCAL_ADMIN === Role::admin_type() && $local ) ) {
						self::$logs[] = [
							'name'  => $logger['name'],
							'running'  => $logger['running'],
							'id'    => $key,
							'limit' => ( Role::LOCAL_ADMIN === Role::admin_type() ? [ get_current_blog_id() ] : [] ),
						];
					}
				}
			}
		}
		self::load_columns();
	}

	/**
	 * Get the number of available logs.
	 *
	 * @return  integer     The number of logs.
	 * @since    1.0.0
	 */
	public static function loggers_count() {
		return count( self::$logs );
	}
}

Events::init();
