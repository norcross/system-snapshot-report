<?php
/*
Plugin Name: System Snapshot Report
Plugin URI: http://reaktivstudios.com
Description: Admin related functions for doing a site audit
Version: 1.0.1
Author: Reaktiv Studios
Author URI: http://reaktivstudios.com

	Copyright 2013 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

	The original code (and inspiration) was ported from Easy Digital Downloads
*/

// Plugin Folder Path
if ( ! defined( 'SSRP_DIR' ) ) {
	define( 'SSRP_DIR', plugin_dir_path( __FILE__ ) );
}

if( ! defined( 'SSRP_VER' ) ) {
	define( 'SSRP_VER', '1.0.1' );
}

/**
 * Start up the engine
 * Reaktiv_Audit_Report class.
 */
class System_Snapshot_Report
{
	/**
	 * Static property to hold our singleton instance
	 *
	 * (default value: false)
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	static $instance = false;

	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @access private
	 * @return void
	 */
	private function __construct() {
		add_action		(	'plugins_loaded',						array(	$this,	'textdomain'			)			);
		add_action		(	'admin_enqueue_scripts',				array(	$this,	'scripts_styles'		),	10		);
		add_action		(	'admin_init',							array(	$this,	'snapshot_download'		)			);
		add_action		(	'admin_menu',							array(	$this,	'menu_item'				)			);
		add_filter		(	'admin_footer_text',					array(	$this,	'admin_footer'			)			);
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * load textdomain
	 *
	 * @access public
	 * @return void
	 */
	public function textdomain() {

		load_plugin_textdomain( 'system-snapshot-report', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Scripts and stylesheets
	 *
	 * @access public
	 * @return void
	 */
	public function scripts_styles() {

		$current_screen = get_current_screen();

		if ( 'tools_page_snapshot-report' == $current_screen->base ) {
			wp_enqueue_style(	'snapshot', plugins_url( '/lib/css/snapshot.css', __FILE__ ),	array(),			SSRP_VER,	'all'	);
			wp_enqueue_script(	'snapshot', plugins_url( '/lib/js/snapshot.js', __FILE__ ),		array( 'jquery' ),	SSRP_VER,	true	);
		}

	}

	/**
	 * helper function for number conversions
	 *
	 * @access public
	 * @param mixed $v
	 * @return void
	 */
	public function num_convt( $v ) {
		$l   = substr( $v, -1 );
		$ret = substr( $v, 0, -1 );

		switch ( strtoupper( $l ) ) {
			case 'P': // fall-through
			case 'T': // fall-through
			case 'G': // fall-through
			case 'M': // fall-through
			case 'K': // fall-through
				$ret *= 1024;
				break;
			default:
				break;
		}

		return $ret;
	}

	/**
	 * build out settings page and meta boxes
	 *
	 * @access public
	 * @return void
	 */
	public function menu_item() {
		add_management_page( __( 'System Snapshot Report', 'system-snapshot-report' ), __( 'Snapshot', 'system-snapshot-report' ), 'manage_options', 'snapshot-report', array( $this, 'snapshot_report' ) );

	}

	/**
	 * Display actual report
	 *
	 * @access public
	 * @return void
	 */
	public function snapshot_report() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>

		<div class="wrap system-snapshot-wrap">
		<div class="icon32" id="icon-tools"><br></div>
		<h2><?php _e( 'System Snapshot Report ' . SSRP_VER, 'system-snapshot-report' ) ?></h2>

		<p><?php _e( 'Either copy + paste the info below or click the download button', 'system-snapshot-report' ) ?></p>

		<form action="<?php echo esc_url( admin_url( 'tools.php?page=snapshot-report' ) ); ?>" method="post" dir="ltr">

			<p>
				<input type="hidden" name="snapshot-action" value="process-report">
				<input type="submit" value="<?php _e( 'Save Snapshot File', 'system-snapshot-report' ); ?>" class="button button-primary system-snapshot-save" name="system-snapshot-save">
				<input type="button" value="<?php _e( 'Highlight Data', 'system-snapshot-report' ); ?>" class="button button-secondary snapshot-highlight" name="snapshot-highlight">
			</p>

			<p><?php echo $this->snapshot_data(); ?></p>

			<p>
				<input type="hidden" name="snapshot-action" value="process-report">
				<input type="submit" value="<?php _e( 'Save Snapshot File', 'system-snapshot-report' ); ?>" class="button button-primary system-snapshot-save" name="system-snapshot-save">
				<input type="button" value="<?php _e( 'Highlight Data', 'system-snapshot-report' ); ?>" class="button button-secondary snapshot-highlight" name="snapshot-highlight">
			</p>

		</form>

		</div>

	<?php }

	/**
	 * generate data for report
	 *
	 * @return System_Snapshot_Report
	 */

	public function snapshot_data() {

		// call WP database
		global $wpdb;

		// check for browser class add on
		if ( ! class_exists( 'Browser' ) ) {
			require_once SSRP_DIR . 'lib/browser.php';
		}

		// do WP version check and get data accordingly
		$browser = new Browser();
		if ( get_bloginfo( 'version' ) < '3.4' ) :
			$theme_data = get_theme_data( get_stylesheet_directory() . '/style.css' );
			$theme      = $theme_data['Name'] . ' ' . $theme_data['Version'];
		else:
			$theme_data = wp_get_theme();
			$theme      = $theme_data->Name . ' ' . $theme_data->Version;
		endif;

		// data checks for later
		$frontpage	= get_option( 'page_on_front' );
		$frontpost	= get_option( 'page_for_posts' );
		$mu_plugins = get_mu_plugins();
		$plugins	= get_plugins();
		$active		= get_option( 'active_plugins', array() );

		// multisite details
		$nt_plugins	= is_multisite() ? wp_get_active_network_plugins() : array();
		$nt_active	= is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$ms_sites	= is_multisite() ? get_blog_list() : null;

		// yes / no specifics
		$ismulti	= is_multisite() ? __( 'Yes', 'system-snapshot-report' ) : __( 'No', 'system-snapshot-report' );
		$safemode	= ini_get( 'safe_mode' ) ? __( 'Yes', 'system-snapshot-report' ) : __( 'No', 'system-snapshot-report' );
		$wpdebug	= defined( 'WP_DEBUG' ) ? WP_DEBUG ? __( 'Enabled', 'system-snapshot-report' ) : __( 'Disabled', 'system-snapshot-report' ) : __( 'Not Set', 'system-snapshot-report' );
		$tbprefx	= strlen( $wpdb->prefix ) < 16 ? __( 'Acceptable', 'system-snapshot-report' ) : __( 'Too Long', 'system-snapshot-report' );
		$fr_page	= $frontpage ? get_the_title( $frontpage ).' (ID# '.$frontpage.')'.'' : __( 'n/a', 'system-snapshot-report' );
		$fr_post	= $frontpage ? get_the_title( $frontpost ).' (ID# '.$frontpost.')'.'' : __( 'n/a', 'system-snapshot-report' );
		$errdisp	= ini_get( 'display_errors' ) != false ? __( 'On', 'system-snapshot-report' ) : __( 'Off', 'system-snapshot-report' );

		$jquchk		= wp_script_is( 'jquery', 'registered' ) ? $GLOBALS['wp_scripts']->registered['jquery']->ver : __( 'n/a', 'system-snapshot-report' );

		$sessenb	= isset( $_SESSION ) ? __( 'Enabled', 'system-snapshot-report' ) : __( 'Disabled', 'system-snapshot-report' );
		$usecck		= ini_get( 'session.use_cookies' ) ? __( 'On', 'system-snapshot-report' ) : __( 'Off', 'system-snapshot-report' );
		$useocck	= ini_get( 'session.use_only_cookies' ) ? __( 'On', 'system-snapshot-report' ) : __( 'Off', 'system-snapshot-report' );
		$hasfsock	= function_exists( 'fsockopen' ) ? __( 'Your server supports fsockopen.', 'system-snapshot-report' ) : __( 'Your server does not support fsockopen.', 'system-snapshot-report' );
		$hascurl	= function_exists( 'curl_init' ) ? __( 'Your server supports cURL.', 'system-snapshot-report' ) : __( 'Your server does not support cURL.', 'system-snapshot-report' );
		$hassoap	= class_exists( 'SoapClient' ) ? __( 'Your server has the SOAP Client enabled.', 'system-snapshot-report' ) : __( 'Your server does not have the SOAP Client enabled.', 'system-snapshot-report' );
		$hassuho	= extension_loaded( 'suhosin' ) ? __( 'Your server has SUHOSIN installed.', 'system-snapshot-report' ) : __( 'Your server does not have SUHOSIN installed.', 'system-snapshot-report' );
		$openssl	= extension_loaded('openssl') ? __( 'Your server has OpenSSL installed.', 'system-snapshot-report' ) : __( 'Your server does not have OpenSSL installed.', 'system-snapshot-report' );

		// start generating report
		$report	= '';
		$report	.= '<textarea readonly="readonly" id="system-snapshot-textarea" name="system-snapshot-textarea">';
		$report	.= '### Begin System Info ###'."\n";
		// add filter for adding to report opening
		$report	.= apply_filters( 'snapshot_report_before', '' );

		$report	.= "\n\t".'** WORDPRESS DATA **'."\n";
		$report	.= 'Multisite:'."\t\t\t\t".$ismulti."\n";
		$report	.= 'SITE_URL:'."\t\t\t\t".site_url()."\n";
		$report	.= 'HOME_URL:'."\t\t\t\t".home_url()."\n";
		$report	.= 'WP Version:'."\t\t\t\t".get_bloginfo( 'version' )."\n";
		$report	.= 'Permalink:'."\t\t\t\t".get_option( 'permalink_structure' )."\n";
		$report	.= 'Cur Theme:'."\t\t\t\t".$theme."\n";
		$report	.= 'Post Types:'."\t\t\t\t".implode( ', ', get_post_types( '', 'names' ) )."\n";
		$report	.= 'Post Stati:'."\t\t\t\t".implode( ', ', get_post_stati() )."\n";
		$report	.= 'User Count:'."\t\t\t\t".count( get_users() )."\n";

		$report	.= "\n\t".'** WORDPRESS CONFIG **'."\n";
		$report	.= 'WP_DEBUG:'."\t\t\t\t".$wpdebug."\n";
		$report	.= 'WP Memory Limit:'."\t\t\t".$this->num_convt( WP_MEMORY_LIMIT )/( 1024 ).'MB'."\n";
		$report	.= 'Table Prefix:'."\t\t\t\t".$wpdb->base_prefix."\n";
		$report	.= 'Prefix Length:'."\t\t\t\t".$tbprefx.' ('.strlen( $wpdb->prefix ).' characters)'."\n";
		$report	.= 'Show On Front:'."\t\t\t\t".get_option( 'show_on_front' )."\n";
		$report	.= 'Page On Front:'."\t\t\t\t".$fr_page."\n";
		$report	.= 'Page For Posts:'."\t\t\t\t".$fr_post."\n";

		if ( is_multisite() ) :
			$report	.= "\n\t".'** MULTISITE INFORMATION **'."\n";
			$report	.= 'Total Sites:'."\t\t\t\t".get_blog_count()."\n";
			$report	.= 'Base Site:'."\t\t\t\t".$ms_sites[0]['domain']."\n";
			$report	.= 'All Sites:'."\n";
			foreach ( $ms_sites as $site ) :
				if ( $site['path'] != '/' )
					$report	.= "\t\t".'- '. $site['domain'].$site['path']."\n";

			endforeach;
			$report	.= "\n";
		endif;

		$report	.= "\n\t".'** BROWSER DATA **'."\n";
		$report	.= 'Platform:'."\t\t\t\t".$browser->getPlatform()."\n";
		$report	.= 'Browser Name'."\t\t\t\t". $browser->getBrowser() ."\n";
		$report	.= 'Browser Version:'."\t\t\t".$browser->getVersion()."\n";
		$report	.= 'Browser User Agent:'."\t\t\t".$browser->getUserAgent()."\n";

		$report	.= "\n\t".'** SERVER DATA **'."\n";
		$report	.= 'jQuery Version'."\t\t\t\t".$jquchk."\n";
		$report	.= 'PHP Version:'."\t\t\t\t".PHP_VERSION."\n";
		$report	.= 'MySQL Version:'."\t\t\t\t".mysql_get_server_info()."\n";
		$report	.= 'Server Software:'."\t\t\t".$_SERVER['SERVER_SOFTWARE']."\n";

		$report	.= "\n\t".'** PHP CONFIGURATION **'."\n";
		$report	.= 'Safe Mode:'."\t\t\t\t".$safemode."\n";
		$report	.= 'Memory Limit:'."\t\t\t\t".ini_get( 'memory_limit' )."\n";
		$report	.= 'Upload Max:'."\t\t\t\t".ini_get( 'upload_max_filesize' )."\n";
		$report	.= 'Post Max:'."\t\t\t\t".ini_get( 'post_max_size' )."\n";
		$report	.= 'Time Limit:'."\t\t\t\t".ini_get( 'max_execution_time' )."\n";
		$report	.= 'Max Input Vars:'."\t\t\t\t".ini_get( 'max_input_vars' )."\n";
		$report	.= 'Display Errors:'."\t\t\t\t".$errdisp."\n";
		$report	.= 'Sessions:'."\t\t\t\t".$sessenb."\n";
		$report	.= 'Session Name:'."\t\t\t\t".esc_html( ini_get( 'session.name' ) )."\n";
		$report	.= 'Cookie Path:'."\t\t\t\t".esc_html( ini_get( 'session.cookie_path' ) )."\n";
		$report	.= 'Save Path:'."\t\t\t\t".esc_html( ini_get( 'session.save_path' ) )."\n";
		$report	.= 'Use Cookies:'."\t\t\t\t".$usecck."\n";
		$report	.= 'Use Only Cookies:'."\t\t\t".$useocck."\n";
		$report	.= 'FSOCKOPEN:'."\t\t\t\t".$hasfsock."\n";
		$report	.= 'cURL:'."\t\t\t\t\t".$hascurl."\n";
		$report	.= 'SOAP Client:'."\t\t\t\t".$hassoap."\n";
		$report	.= 'SUHOSIN:'."\t\t\t\t".$hassuho."\n";
		$report	.= 'OpenSSL:'."\t\t\t\t".$openssl."\n";

		$report	.= "\n\t".'** PLUGIN INFORMATION **'."\n";
		if ( $plugins && $mu_plugins ) :
			$report	.= 'Total Plugins:'."\t\t\t\t".( count( $plugins ) + count( $mu_plugins ) + count( $nt_plugins ) )."\n";
		endif;

		// output must-use plugins
		if ( $mu_plugins ) :
			$report	.= 'Must-Use Plugins: ('.count( $mu_plugins ).')'. "\n";
			foreach ( $mu_plugins as $mu_path => $mu_plugin ) :
				$report	.= "\t".'- '.$mu_plugin['Name'] . ' ' . $mu_plugin['Version'] ."\n";
			endforeach;
			$report	.= "\n";
		endif;

		// if multisite, grab active network as well
		if ( is_multisite() ) :
			// active network
			$report	.= 'Network Active Plugins: ('.count( $nt_plugins ).')'. "\n";

			foreach ( $nt_plugins as $plugin_path ) :
				if ( array_key_exists( $plugin_base, $nt_plugins ) )
					continue;

				$plugin = get_plugin_data( $plugin_path );

				$report	.= "\t".'- '.$plugin['Name'] . ' ' . $plugin['Version'] ."\n";
			endforeach;
			$report	.= "\n";

		endif;

		// output active plugins
		if ( $plugins ) :
			$report	.= 'Active Plugins: ('.count( $active ).')'. "\n";
			foreach ( $plugins as $plugin_path => $plugin ) :
				if ( ! in_array( $plugin_path, $active ) )
					continue;
				$report	.= "\t".'- '.$plugin['Name'] . ' ' . $plugin['Version'] ."\n";
			endforeach;
			$report	.= "\n";
		endif;

		// output inactive plugins
		if ( $plugins ) :
			$report	.= 'Inactive Plugins: ('.( count( $plugins ) - count( $active ) ).')'. "\n";
			foreach ( $plugins as $plugin_path => $plugin ) :
				if ( in_array( $plugin_path, $active ) )
					continue;
				$report	.= "\t".'- '.$plugin['Name'] . ' ' . $plugin['Version'] ."\n";
			endforeach;
			$report	.= "\n";
		endif;

		// add filter for end of report
		$report	.= apply_filters( 'snapshot_report_after', '' );

		// end it all
		$report	.= "\n".'### End System Info ###';
		$report	.= '</textarea>';

		return $report;
	}

	/**
	 * generate snapshot text file for download
	 *
	 * @return System_Snapshot_Report
	 */

	public function snapshot_download() {

		if ( ! isset( $_POST['snapshot-action'] ) || isset( $_POST['snapshot-action'] ) && $_POST['snapshot-action'] !== 'process-report' ) {
			return;
		}


		// build out filename and timestamp
		$name	= sanitize_title_with_dashes( get_bloginfo( 'name' ), '', 'save' );
		$file	= $name.'-snapshot.txt';

		$now	= time();
		$stamp	= __( 'Report Generated: ', 'system-snapshot-report' ).date( 'm-d-Y @ g:i:sa', $now ).' system time';

		$data	= '';
		$data	.= $stamp."\n\n";
		$data	.= wp_strip_all_tags( $_POST['system-snapshot-textarea'] );
		$data	.= "\n\n".$stamp;

		nocache_headers();

		header( "Content-type: text/plain" );
		header( 'Content-Disposition: attachment; filename="'.$file.'"' );

		echo $data;

		die();
	}

	/**
	 * add attribution link to report page
	 *
	 * @return System_Snapshot_Report
	 */

	public function admin_footer($text) {

		$screen = get_current_screen();

		if ( 'tools_page_snapshot-report' !== $screen->base ) {
			return $text;
		}

		// set our footer link with GA campaign tracker
		$link	= 'http://reaktivstudios.com/?utm_source=plugin&utm_medium=link&utm_campaign=snapshot';

		// build footer
		$text	= '<span id="footer-thankyou">';
		$text	.= sprintf( __( 'This plugin brought to you by the fine folks at <a href="%1$s" title="%2$s" target="_blank">Reaktiv Studios</a>', 'system-snapshot-report' ), esc_url( $link ), esc_html( 'Reaktiv Studios', 'system-snapshot-report' ) );
		$text	.= '</span>';

		return $text;
	}

/// end class
}



// Instantiate our class
$System_Snapshot_Report = System_Snapshot_Report::getInstance();
