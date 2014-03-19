<?php
/*
Plugin Name: Reaktiv Audit Report
Plugin URI: http://reaktivstudios.com
Description: Admin related functions for doing a site audit
Version: 0.0.1
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
*/

// Plugin Folder Path
	if ( ! defined( 'RKRP_PLUGIN_DIR' ) )
		define( 'RKRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Start up the engine
	 * Reaktiv_Audit_Report class.
	 */
class Reaktiv_Audit_Report
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
		add_action      ( 'plugins_loaded',                     array( $this, 'textdomain'              )			);
		add_action		( 'admin_enqueue_scripts',				array( $this, 'scripts_styles'			),	10		);
		add_action		( 'admin_init',							array( $this, 'audit_download'			)			);
		add_action      ( 'admin_menu',                 		array( $this, 'menu_item'    			)			);

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
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}


	/**
	 * load textdomain
	 *
	 * @access public
	 * @return void
	 */
	public function textdomain() {

		load_plugin_textdomain( 'rkrp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Scripts and stylesheets
	 *
	 * @access public
	 * @return void
	 */
	public function scripts_styles() {

		$current_screen = get_current_screen();

		if ( 'tools_page_reaktiv-audit' == $current_screen->base ) {
			wp_enqueue_style( 'audit', plugins_url('/lib/css/audit.css', __FILE__), array(), null, 'all' );
			wp_enqueue_script( 'audit', plugins_url('/lib/js/audit.js', __FILE__) , array('jquery'), '1.0', true );
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
		add_management_page( __('Reaktiv Audit Report', 'rkrp'), __('Audit Report', 'rkrp'), 'manage_options', 'reaktiv-audit', array( $this, 'audit_report' ) );

	}


	/**
	 * Display audit stuff
	 *
	 * @access public
	 * @return void
	 */
	public function audit_report() {
		if (!current_user_can('manage_options') )
			return;

		global $wpdb;

		if ( ! class_exists( 'Browser' ) )
			require_once RKRP_PLUGIN_DIR . 'lib/browser.php';

		/**
		 * Browser
		 *
		 * (default value: new Browser())
		 *
		 * @var mixed
		 * @access public
		 */
		$browser = new Browser();
		if ( get_bloginfo( 'version' ) < '3.4' ) {
			$theme_data = get_theme_data( get_stylesheet_directory() . '/style.css' );
			$theme      = $theme_data['Name'] . ' ' . $theme_data['Version'];
		} else {
			$theme_data = wp_get_theme();
			$theme      = $theme_data->Name . ' ' . $theme_data->Version;
		}

		?>

		<div class="wrap reaktiv-audit-wrap">
    	<div class="icon32" id="icon-tools"><br></div>
		<h2><?php _e('Reaktiv Audit Report') ?></h2>

		<p><?php _e('Either copy + paste the info below or click the download button') ?></p>

		<form action="<?php echo esc_url( admin_url( 'tools.php?page=reaktiv-audit' ) ); ?>" method="post" dir="ltr">

			<p>
				<input type="hidden" name="reaktiv-action" value="process-report">
				<input type="submit" value="<?php _e('Download Audit File') ?>" class="button button-primary" id="reaktiv-audit-download" name="reaktiv-audit-download">
				<input type="button" value="<?php _e('Highlight Data') ?>" class="button button-secondary" id="reaktiv-highlight" name="reaktiv-highlight">
			</p>

			<textarea readonly="readonly" id="reaktiv-audit-textarea" name="reaktiv-audit-textarea">
### Begin System Info ###

Multisite:                <?php echo is_multisite() ? 'Yes' . "\n" : 'No' . "\n" ?>

SITE_URL:                 <?php echo site_url() . "\n"; ?>
HOME_URL:                 <?php echo home_url() . "\n"; ?>

WordPress Version:        <?php echo get_bloginfo( 'version' ) . "\n"; ?>
Permalink Structure:      <?php echo get_option( 'permalink_structure' ) . "\n"; ?>
Active Theme:             <?php echo $theme . "\n"; ?>

Registered Post Types:    <?php echo implode( ', ', get_post_types( '', 'names' ) ) . "\n"; ?>
Registered Post Stati:    <?php echo implode( ', ', get_post_stati() ) . "\n\n"; ?>

<?php echo $browser ; ?>

jQuery Version:           MYJQUERYVERSION
PHP Version:              <?php echo PHP_VERSION . "\n"; ?>
MySQL Version:            <?php echo mysql_get_server_info() . "\n"; ?>
Web Server Info:          <?php echo $_SERVER['SERVER_SOFTWARE'] . "\n"; ?>

PHP Safe Mode:            <?php echo ini_get( 'safe_mode' ) ? "Yes" : "No\n"; ?>
PHP Memory Limit:         <?php echo ini_get( 'memory_limit' ) . "\n"; ?>
PHP Upload Max Size:      <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Post Max Size:        <?php echo ini_get( 'post_max_size' ) . "\n"; ?>
PHP Upload Max Filesize:  <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Time Limit:           <?php echo ini_get( 'max_execution_time' ) . "\n"; ?>
PHP Max Input Vars:       <?php echo ini_get( 'max_input_vars' ) . "\n"; ?>

WP_DEBUG:                 <?php echo defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' . "\n" : 'Disabled' . "\n" : 'Not set' . "\n" ?>

WP Table Prefix:          <?php echo "Length: ". strlen( $wpdb->prefix ); echo " Status:"; if ( strlen( $wpdb->prefix )>16 ) {echo " ERROR: Too Long";} else {echo " Acceptable";} echo "\n"; ?>

Show On Front:            <?php echo get_option( 'show_on_front' ) . "\n" ?>
Page On Front:            <?php $id = get_option( 'page_on_front' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>
Page For Posts:           <?php $id = get_option( 'page_for_posts' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>


Session:                  <?php echo isset( $_SESSION ) ? 'Enabled' : 'Disabled'; ?><?php echo "\n"; ?>
Session Name:             <?php echo esc_html( ini_get( 'session.name' ) ); ?><?php echo "\n"; ?>
Cookie Path:              <?php echo esc_html( ini_get( 'session.cookie_path' ) ); ?><?php echo "\n"; ?>
Save Path:                <?php echo esc_html( ini_get( 'session.save_path' ) ); ?><?php echo "\n"; ?>
Use Cookies:              <?php echo ini_get( 'session.use_cookies' ) ? 'On' : 'Off'; ?><?php echo "\n"; ?>
Use Only Cookies:         <?php echo ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off'; ?><?php echo "\n"; ?>

WordPress Memory Limit:   <?php echo ( $this->num_convt( WP_MEMORY_LIMIT )/( 1024 ) )."MB"; ?><?php echo "\n"; ?>
DISPLAY ERRORS:           <?php echo ( ini_get( 'display_errors' ) ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A'; ?><?php echo "\n"; ?>
FSOCKOPEN:                <?php echo ( function_exists( 'fsockopen' ) ) ? __( 'Your server supports fsockopen.', 'edd' ) : __( 'Your server does not support fsockopen.', 'edd' ); ?><?php echo "\n"; ?>
cURL:                     <?php echo ( function_exists( 'curl_init' ) ) ? __( 'Your server supports cURL.', 'edd' ) : __( 'Your server does not support cURL.', 'edd' ); ?><?php echo "\n"; ?>
SOAP Client:              <?php echo ( class_exists( 'SoapClient' ) ) ? __( 'Your server has the SOAP Client enabled.', 'edd' ) : __( 'Your server does not have the SOAP Client enabled.', 'edd' ); ?><?php echo "\n"; ?>
SUHOSIN:                  <?php echo ( extension_loaded( 'suhosin' ) ) ? __( 'Your server has SUHOSIN installed.', 'edd' ) : __( 'Your server does not have SUHOSIN installed.', 'edd' ); ?><?php echo "\n"; ?>

<?php
$plugins	= get_plugins();
$pg_count	= count( $plugins );
echo 'TOTAL PLUGINS:		  '.$pg_count."\n\n";
// MU plugins
$mu_plugins = get_mu_plugins();

	if ( $mu_plugins ) :
	$mu_count	= count( $mu_plugins );

	echo 'MU PLUGINS: ('.$mu_count.')'."\n\n";

	foreach ( $mu_plugins as $mu_path => $mu_plugin ) {

		echo $mu_plugin['Name'] . ': ' . $mu_plugin['Version'] ."\n";
	}
	endif;
// standard plugins - active
echo "\n";

$active		= get_option( 'active_plugins', array() );
$ac_count	= count( $active );
$ic_count	= $pg_count - $ac_count;

echo 'ACTIVE PLUGINS: ('.$ac_count.')'."\n\n";

foreach ( $plugins as $plugin_path => $plugin ) {
	// If the plugin isn't active, don't show it.
	if ( ! in_array( $plugin_path, $active ) )
		continue;

	echo $plugin['Name'] . ': ' . $plugin['Version'] ."\n";
}
// standard plugins - inactive
echo "\n";
echo 'INACTIVE PLUGINS: ('.$ic_count.')'."\n\n";

foreach ( $plugins as $plugin_path => $plugin ) {
	// If the plugin isn't active, show it here.
	if ( in_array( $plugin_path, $active ) )
		continue;

	echo $plugin['Name'] . ': ' . $plugin['Version'] ."\n";
}

// if multisite, grab network as well
if ( is_multisite() ) :

$net_plugins	= wp_get_active_network_plugins();
$net_active		= get_site_option( 'active_sitewide_plugins', array() );

echo "\n";
echo 'NETWORK ACTIVE PLUGINS: ('.count($net_plugins).')'."\n\n";

foreach ( $net_plugins as $plugin_path ) {
	$plugin_base = plugin_basename( $plugin_path );

	// If the plugin isn't active, don't show it.
	if ( ! array_key_exists( $plugin_base, $net_active ) )
		continue;

	$plugin = get_plugin_data( $plugin_path );

	echo $plugin['Name'] . ' :' . $plugin['Version'] ."\n";
}

endif;

?>

### End System Info ###</textarea>

		</form>
		</div>

	<?php }


	/**
	 * generate audit text file
	 *
	 * @access public
	 * @return void
	 */
	public function audit_download() {

		if ( !isset( $_POST['reaktiv-action'] ) )
			return;

		if ( $_POST['reaktiv-action'] !== 'process-report' )
			return;

		// build out filename

		$name	= sanitize_title_with_dashes( get_bloginfo( 'name' ), '', 'save' );
		$date	= date( 'm-d-Y', time() );

		$file	= $name.'-'.$date.'-audit-info.txt';

		nocache_headers();

		header( "Content-type: text/plain" );
		header( 'Content-Disposition: attachment; filename="'.$file.'"' );

		echo wp_strip_all_tags( $_POST['reaktiv-audit-textarea'] );
		die();
	}

/// end class
}

// Instantiate our class
$Reaktiv_Audit_Report = Reaktiv_Audit_Report::getInstance();