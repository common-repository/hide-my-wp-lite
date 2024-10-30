<?php
/*
Plugin Name: Hide My WP Lite
Plugin URI: https://hide-my-wp.wpwave.com/
Description: An excellent security plugin to hide your WordPress installation packed with some of the coolest and most unique features in the community.
Author: ExpressTech
Author URI: https://expresstech.io
Plugin URI: https://wpwave.com
Version: 1.0.1
Text Domain: hide_my_wp_lite
Domain Path: /lang
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Network: True
*/


/**
 *   ++ Credits ++
 *   Copyright 2019 Vikas Singhal
 *   Copyright 2017 Hassan Jahangiri
 *   Some code from dxplugin base by mpeshev, plugin base v2 by Brad Vincent, weDevs Settings API by Tareq Hasan, rootstheme by Ben Word, Minify by Stephen Clay
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HMW_TITLE', 'Hide My WP Lite');
define('HMW_VERSION', '1.0');
define('HMW_SLUG', 'hide_my_wp'); //use _
define('HMW_PATH', dirname(__FILE__));
define('HMW_DIR', basename(HMW_PATH));
define('HMW_URL', plugins_url() . '/' . HMW_DIR);
define('HMW_FILE', plugin_basename(__FILE__));
define('HMW_UPGRADE_TO_PRO_TEXT','Upgrade to PRO');

if (is_ssl()) {
    define('HMW_WP_CONTENT_URL', str_replace('http:', 'https:', WP_CONTENT_URL));
    define('HMW_WP_PLUGIN_URL', str_replace('http:', 'https:', WP_PLUGIN_URL));
} else {
    define('HMW_WP_CONTENT_URL', WP_CONTENT_URL);
    define('HMW_WP_PLUGIN_URL', WP_PLUGIN_URL);
}

class HideMyWP
{
    const title = HMW_TITLE;
    const ver = HMW_VERSION;
    const slug = HMW_SLUG;
    const path = HMW_PATH;
    const dir = HMW_DIR;
    const url = HMW_URL;
    const main_file = HMW_FILE;

    private $s;
    private $sub_folder;
    private $is_subdir_mu;
    private $blog_path;

    private $trust_key;
    private $short_prefix;


    private $post_replace_old = array();
    private $post_replace_new = array();

    private $post_preg_replace_new = array();
    private $post_preg_replace_old = array();

    private $partial_replace_old = array();
    private $partial_replace_new = array();

    private $top_replace_old = array();
    private $top_replace_new = array();

    private $partial_preg_replace_new = array();
    private $partial_preg_replace_old = array();

    private $replace_old = array();
    private $replace_new = array();

    private $preg_replace_old = array();
    private $preg_replace_new = array();

    private $admin_replace_old = array();
    private $admin_replace_new = array();

    private $auto_replace_urls = array(); //strings with ==
    private $auto_config_internal_css;
    private $auto_config_internal_js;

    private $none_replaced_buffer = '';

    /**
     * HideMyWP::__construct()
     *
     * @return
     */
    function __construct()
    {
        //Let's start, Bismillah and Aum!
        require_once('load.php');
    }

    /**
     * HideMyWP::hmwpl_bp_uri()
     * Fix buddypress pages URL when page_base is enabled
     *
     * @return
     */
    function hmwpl_bp_uri($uri)
    {
        if (trim($this->opt('page_base'), ' /'))
            return str_replace(trim($this->opt('page_base'), ' /') . '/', '', $uri);
        else
            return $uri;
    }

    function hmwpl_access_cookie()
    {
        return preg_replace("/[^a-zA-Z]/", "", substr(SECURE_AUTH_SALT, 2, 8));
    }

    /**
     * HideMyWP::hmwpl_replace_admin_url()
     * Filter to replace old and new admin URL
     *
     * @return
     */
    function hmwpl_replace_admin_url($url, $path = '', $scheme = 'admin')
    {
        if (trim($this->opt('new_admin_path'), '/ ') && trim($this->opt('new_admin_path'), '/ ') != 'wp-admin')
            $url = str_replace('wp-admin/', trim($this->opt('new_admin_path'), '/ ') . '/', $url);
        return $url;
    }

    function hmwpl_netMatch($network, $ip)
    {
        $network = trim($network);
        $orig_network = $network;
        $ip = trim($ip);
        if ($ip == $network) {
            //echo "used network ($network) for ($ip)\n";
            return TRUE;
        }
        $network = str_replace(' ', '', $network);
        if (strpos($network, '*') !== FALSE) {
            if (strpos($network, '/') !== FALSE) {
                $asParts = explode('/', $network);
                $network = @ $asParts[0];
            }
            $nCount = substr_count($network, '*');
            $network = str_replace('*', '0', $network);
            if ($nCount == 1) {
                $network .= '/24';
            } else if ($nCount == 2) {
                $network .= '/16';
            } else if ($nCount == 3) {
                $network .= '/8';
            } else if ($nCount > 3) {
                return TRUE; // if *.*.*.*, then all, so matched
            }
        }

        // echo "from original network($orig_network), used network ($network) for ($ip)\n";

        $d = strpos($network, '-');
        if ($d === FALSE) {
            $ip_arr = explode('/', $network);
            if (!preg_match("@\d*\.\d*\.\d*\.\d*@", $ip_arr[0], $matches)) {
                $ip_arr[0] .= ".0";    // Alternate form 194.1.4/24
            }
            $network_long = ip2long($ip_arr[0]);
            if (isset($ip_arr[1])) {
                $x = ip2long($ip_arr[1]);
                $mask = long2ip($x) == $ip_arr[1] ? $x : (0xffffffff << (32 - $ip_arr[1]));
                $ip_long = ip2long($ip);
                return ($ip_long & $mask) == ($network_long & $mask);
            }
        } else {
            $from = trim(ip2long(substr($network, 0, $d)));
            $to = trim(ip2long(substr($network, $d + 1)));
            $ip = ip2long($ip);
            return ($ip >= $from and $ip <= $to);
        }
    }

    /**
     * HideMyWP::hmwpl_admin_notices()
     * Displays necessary information in admin panel
     *
     * @return
     */
    function hmwpl_admin_notices()
    {
        global $current_user;

        $options_file = (is_multisite()) ? 'network/settings.php' : 'admin.php';
        $page_url = admin_url(add_query_arg('page', self::slug, $options_file));
        $show_access_message = true;

        //Update hmw_all_plugins list whenever a theme or plugin activate
        if ((isset($_GET['page']) && ($_GET['page'] == self::slug)) || isset($_GET['deactivate']) || isset($_GET['activate']) || isset($_GET['activated']) || isset($_GET['activate-multi'])) {
            update_option('hmw_all_plugins', array_keys(get_plugins()));

            $blog_id = get_current_blog_id();
            if (!is_multisite())
                delete_option('hmwp_internal_assets');
            else
                delete_blog_option($blog_id, 'hmwp_internal_assets');
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && function_exists('bulletproof_security_load_plugin_textdomain')) {
            echo __('<div class="error"><p>You use BulletProof security plugin. To make it work correctly you need to configure Hide My WP manually. <a target="_blank" href="' . add_query_arg(array('die_message' => 'single')) . '" class="button">' . __('Manual Configuration', self::slug) . '</a>. (If you already did that ignore this message).', self::slug) . '</p></div>';
            $show_access_message = false;
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && isset($_GET['new_admin_action']) && $_GET['new_admin_action'] == 'configured') {

            if (is_multisite()) {
                $opts = (array)get_blog_option(BLOG_ID_CURRENT_SITE, self::slug);
                $opts['new_admin_path'] = get_option('hmwp_temp_admin_path');
                update_blog_option(BLOG_ID_CURRENT_SITE, self::slug, $opts);
            } else {
                $opts = (array)get_option(self::slug);
                $opts['new_admin_path'] = get_option('hmwp_temp_admin_path');
                update_option(self::slug, $opts);
            }
            delete_option('hmwp_temp_admin_path');
            wp_redirect(add_query_arg('new_admin_action', 'redirect_to_new', $page_url));
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && isset($_GET['new_admin_action']) && $_GET['new_admin_action'] == 'redirect_to_new') {
            //wp_logout();
            wp_redirect(wp_login_url('', true)); //true means force auth
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && isset($_GET['new_admin_action']) && $_GET['new_admin_action'] == "abort") {
            ///update_option('hmwp_temp_admin_path', $this->opt('new_admin_path'));
            delete_option('hmwp_temp_admin_path');
            wp_redirect(add_query_arg('new_admin_action', 'aborted_msg', $page_url));
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && isset($_GET['new_admin_action']) && $_GET['new_admin_action'] == "aborted_msg") {
            echo '<div class="error"><p>Change of admin path is cancelled!</p></div>';
        }


        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        elseif (trim($this->opt('new_admin_path'), '/ '))
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');
        else
            $new_admin_path = 'wp-admin';

        //echo 'sss '.$new_admin_path;
        /* $admin_rule = '';
         if ($new_admin_path && $new_admin_path != 'wp-admin')
             $admin_rule = 'RewriteRule ^' . $new_admin_path . '/(.*) /' . $this->sub_folder . 'wp-admin/$1'.$this->trust_key.' [QSA,L]' . "\n";*/


        //   if (is_multisite() && $this->is_subdir_mu)
        //       $admin_rule = 'RewriteRule ^([_0-9a-zA-Z-]+/)?' . $new_admin_path . '/(.*) /' . $this->sub_folder . 'wp-admin/$1 [QSA,L]' . "\n";


        //$multi_site_rule = '';
        // if (true || is_multisite())
        //   $multi_site_rule = "1) If you enabled multi-site or manually configured your server (Nginx, IIS) you MUST RE-CONFIGURE it now. If HMWP works automatically just go to next step.";
//echo $current_cookie . ' sss '.$new_admin_path;

        if ($this->hmwpl_admin_current_cookie() != $new_admin_path && is_super_admin()) {
            if (!isset($_GET['new_admin_action']) && !isset($_GET['die_message'])) {
                $page_url = str_replace($this->hmwpl_admin_current_cookie(), 'wp-admin', $page_url);

                if ($new_admin_path == 'wp-admin')
                    wp_redirect(add_query_arg(array('die_message' => 'revert_admin'), $page_url));
                else {
                    $rand_token = uniqid('token', true);
                    update_option('hmwp_reset_token', $rand_token);
                    wp_redirect(add_query_arg(array('die_message' => 'new_admin'), $page_url));
                }

            }

            //echo '<style>#adminmenumain,#wpadminbar{display:none;}</style>';
            // exit();
        }
        //Good place to flush! We really need this.
        if (is_super_admin() && !function_exists('bulletproof_security_load_plugin_textdomain') && !$this->opt('customized_htaccess'))
            flush_rewrite_rules(true);

        if (is_multisite() && is_network_admin()) {
            global $wpdb;
            $sites = $wpdb->get_results("SELECT blog_id, domain FROM {$wpdb->blogs} WHERE archived = '0' AND spam = '0' AND deleted = '0' ORDER BY blog_id");

            //Loop through them
            foreach ($sites as $site) {
                global $wp_rewrite;
                //switch_to_blog($site->blog_id);
                delete_blog_option($site->blog_id, 'rewrite_rules');
                //$wp_rewrite->init();
                //$wp_rewrite->flush_rules();
            }

        }

        $home_path = get_home_path();
        if ((!file_exists($home_path . '.htaccess') && is_writable($home_path)) || is_writable($home_path . '.htaccess'))
            $writable = true;
        else
            $writable = false;

        if (isset($_GET['page']) && $_GET['page'] == self::slug && !$this->hmwpl_is_permalink()) {
            if (!is_multisite())
                echo '<div class="error"><p>' . __('Your <a href="options-permalink.php">permalink structure</a> is off. In order to get all features of this plugin please enable it.', self::slug) . '</p></div>';
            else
                echo '<div class="error"><p>' . __('Please enable WP permalink structure (Settings -> Permalink ) in your sites.', self::slug) . '</p></div>';
            $show_access_message = false;
        }

        if (isset($_GET['page']) && $_GET['page'] == self::slug && (isset($_GET['settings-updated']) || isset($_GET['settings-imported'])) && is_multisite()) {
            echo '<div class="error"><p>' . __('You have enabled Multisite. It\'s require to (re)configure Hide My WP after changing settings or activating new plugin or theme. <br><br><a target="_blank" href="' . add_query_arg(array('die_message' => 'multisite')) . '" class="button">' . __('Multisite Configuration', self::slug) . '</a>', self::slug) . '</p></div>';
            $show_access_message = false;
        }

        $nginx = false;
        if (isset($_GET['page']) && $_GET['page'] == self::slug && (stristr($_SERVER['SERVER_SOFTWARE'], 'nginx') || stristr($_SERVER['SERVER_SOFTWARE'], 'wpengine'))) {
            echo '<div class="error"><p>' . __('You use Nginx web server. It\'s require to (re)configure Hide My WP  after changing settings or activating new plugin or theme. <br><br><a target="_blank" href="' . add_query_arg(array('die_message' => 'nginx')) . '" class="button">' . __('Nginx Configuration', self::slug) . '</a>', self::slug) . '</p></div>';
            $show_access_message = false;
            $nginx = true;
        }

        $win = false;
        if (isset($_GET['page']) && $_GET['page'] == self::slug && stristr($_SERVER['SERVER_SOFTWARE'], 'iis') || stristr($_SERVER['SERVER_SOFTWARE'], 'Windows')) {
            echo '<div class="error"><p>' . __('You use Windows (IIS) web server. It\'s require to (re)configure Hide My WP after changing settings or activating new plugin or theme. <br><br><a target="_blank" href="' . add_query_arg(array('die_message' => 'iis')) . '" class="button">' . __('IIS Configuration', self::slug) . '</a>', self::slug) . '</p></div>';
            $show_access_message = false;
            $win = true;
        }


        if (isset($_GET['page']) && $_GET['page'] == self::slug && isset($_GET['undo_config']) && $_GET['undo_config'])
            echo '<div class="updated fade"><p>' . __('Previous settings have been restored!', self::slug) . '</p></div>';

        if (isset($_GET['page']) && $_GET['page'] == self::slug && !$writable && !$nginx && !$win && !function_exists('bulletproof_security_load_plugin_textdomain')) {
            echo '<div class="error"><p>' . __('It seems there is no writable htaccess file in your WP directory. If you use Apache (and not Nginx or IIS) please change permission of .htaccess file.', self::slug) . '</p></div>';
            $show_access_message = false;
        }

        if (basename($_SERVER['PHP_SELF']) == 'options-permalink.php' && $this->hmwpl_is_permalink() && isset( $_POST['permalink_structure'] ))
            echo '<div class="updated"><p>' . sprintf(__('We are refreshing this page in order to implement changes. %s', self::slug), '<a href="options-permalink.php">Manual Refresh</a>') . '<script type="text/JavaScript"><!--  setTimeout("window.location = \'options-permalink.php\';", 5000);   --></script></p> </div>';

        if (isset($_GET['page']) && $_GET['page'] == self::slug && (isset($_GET['settings-updated']) || isset($_GET['settings-imported'])) && $show_access_message && !$this->hmwpl_access_test())
            echo '<div class="error"><p>' . __('HMWP guesses it broke your site. If it didn\'t ignore this messsage otherwise read <a href="http://codecanyon.net/item/hide-my-wp-no-one-can-know-you-use-wordpress/4177158/faqs/18136" target="_blank"><strong>this FAQ</strong></a> or revert settings to default.', self::slug) . '</p></div>';

        if (!defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY') || !defined('LOGGED_IN_KEY') || !defined('NONCE_KEY') || !defined('AUTH_SALT') || !defined('SECURE_AUTH_SALT') || !defined('LOGGED_IN_SALT') || !defined('LOGGED_IN_SALT') || !defined('NONCE_SALT') || NONCE_SALT == 'put your unique phrase here' || !NONCE_SALT || AUTH_KEY == 'put your unique phrase here' || !AUTH_KEY || NONCE_KEY == 'put your unique phrase here') {
            echo '<div class="error"><p>' . __('Hide My WP Security Check: Your site is at risk. WP installed wrongly: one or more of security keys are invalid. <a href="https://codex.wordpress.org/Editing_wp-config.php#Security_Keys" target="_blank"><strong>Read here</strong></a> for details.', self::slug) . '</p></div>';
        }
        if (isset($_GET['page']) && $_GET['page'] == self::slug && (isset($_GET['settings-updated']) || isset($_GET['settings-imported'])) && $show_access_message && !$this->hmwpl_access_test())
            echo '<div class="error"><p>' . __('HMWP guesses it broke your site. If it didn\'t ignore this messsage otherwise read <a href="http://codecanyon.net/item/hide-my-wp-no-one-can-know-you-use-wordpress/4177158/faqs/18136" target="_blank"><strong>this FAQ</strong></a> or revert settings to default.', self::slug) . '</p></div>';


        if (isset($_GET['page']) && $_GET['page'] == self::slug && (isset($_GET['settings-updated']) || isset($_GET['settings-imported'])) && (WP_CACHE || function_exists('hyper_cache_sanitize_uri') || class_exists('WpFastestCache') || defined('QUICK_CACHE_ENABLE') || defined('CACHIFY_FILE') || defined('WP_ROCKET_VERSION')))
            echo '<div class="updated"><p>' . __('It seems you use a caching plugin alongside Hide My WP. Good, just please make sure to flush it to see changes! (consider browser cache, too!)', self::slug) . '</p></div>';
    }

    function hmwpl_access_test()
    {
        $response = wp_remote_get($this->hmwpl_partial_filter(get_stylesheet_uri()));

        if (200 !== wp_remote_retrieve_response_code($response)
            AND 'OK' !== wp_remote_retrieve_response_message($response)
            AND is_wp_error($response)
        )
            return false;

        return true;
    }    


    function hmwpl_styles_scripts()
    {
        $site = '';
        if (is_multisite() && !$this->is_subdir_mu)
            $site = '&sn=' . $this->blog_path;

        if ($this->hmwpl_add_auto_internal('css') && $this->hmwpl_is_permalink()) {
            $page = $this->hmwpl_hash($_SERVER['REQUEST_URI']);
            //todo:
            if ($this->opt('auto_internal') == 1 || $this->opt('auto_internal') == 3)
                $page = $this->hmwpl_hash($_SERVER['REQUEST_URI']);

            wp_enqueue_style('auto_css', network_home_url('/_auto.css') . '?_req=' . $page . $site, array(), false);
        }

        if ($this->hmwpl_add_auto_internal('js') && $this->hmwpl_is_permalink()) {
            //can not use $site here because woocommerce need req
            $page = urlencode(base64_encode($_SERVER['REQUEST_URI']));

            //require for woocommerce endpoint issue
            if ($this->opt('auto_internal') >= 2)
                $page = urlencode(base64_encode($_SERVER['REQUEST_URI']));

            wp_enqueue_script('auto_js', network_home_url('/_auto.js') . '?_req=' . $page . $site, array(), false);
        }
    }


    function hmwpl_wc_endpoint($req)
    {
        if ($this->h->str_contains($req, '_auto.') && isset($_REQUEST['_req']))
            return add_query_arg('wc-ajax', '%%endpoint%%', remove_query_arg(array('remove_item', 'add-to-cart', 'added-to-cart'), base64_decode(urldecode($_REQUEST['_req']))));
        return $req;
    }

    function hmwpl_hash($key)
    {
        return hash('crc32', preg_replace("/[^a-zA-Z]/", "", substr(NONCE_KEY, 2, 6)) . $key);
    }

    function hmwpl_encrypt($str, $key)
    {
        //$key = "abc123 as long as you want bla bla bla";
        $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $char = substr($str, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) + ord($keychar));
            $result .= $char;
        }
        return urlencode(base64_encode($result));
    }


    function hmwpl_decrypt($str, $key)
    {
        $str = base64_decode(urldecode($str));
        $result = '';
        //$key = "must be same key as in encrypt";
        for ($i = 0; $i < strlen($str); $i++) {
            $char = substr($str, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) - ord($keychar));
            $result .= $char;
        }
        return $result;
    }

    /**
     * HideMyWP::wp()
     *
     * Disable WP components when permalink is enabled
     * @return
     */
    function hmwpl_wp()
    {
        global $wp_query;

        //echo '<pre>'; print_r($wp_query); echo '</pre>';

        if ((is_feed() || is_comment_feed()) && !isset($_GET['feed']) && !$this->opt('feed_enable'))
            $this->hmwpl_block_access();                
        if (isset($_SERVER['HTTP_USER_AGENT']) && !is_404() && !is_home() && (stristr($_SERVER['HTTP_USER_AGENT'], 'BuiltWith') || stristr($_SERVER['HTTP_USER_AGENT'], '2ip.ru')))
            wp_redirect(home_url());        
    }

    function hmwpl_die_message()
    {
        //already checked to be super admin
        if (!isset($_GET['die_message']))
            return;

        $options_file = (is_multisite()) ? 'network/settings.php' : 'admin.php';
        $page_url = admin_url(add_query_arg('page', self::slug, $options_file));

        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        elseif (trim($this->opt('new_admin_path'), '/ '))
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');
        else
            $new_admin_path = 'wp-admin';

        $page_url = str_replace($this->hmwpl_admin_current_cookie(), 'wp-admin', $page_url);
        $title = '';
        switch ($_GET['die_message']) {
            case 'nginx':
                $title = "Nginx Configuration";
                $_GET['nginx_config'] = 1;
                $content = $this->hmwpl_nginx_config();
                break;
            case 'single':
                $title = "Manual Configuration";
                $_GET['single_config'] = 1;
                $content = $this->hmwpl_single_config();
                break;
            case 'multisite':
                $title = "Multisite Configuration";
                $_GET['multisite_config'] = 1;
                $content = $this->hmwpl_multisite_config();
                break;
            case 'iis':
                $title = "IIS Configuration";
                $_GET['iis_config'] = 1;
                $content = $this->hmwpl_iis_config();
                break;
            case 'new_admin':
                $title = "Custom Admin Path";               
                $content = sprintf(__('<div class="error"><p>Do not click back or close this tab.<br> Follow these steps <strong>IMMEDIATELY</strong> to enable new admin path or <a href="' . add_query_arg(array('new_admin_action' => 'abort'), $page_url) . '">Cancel</a> and try later. (<a target="_blank" href="http://support.wpwave.com/videos/change-wp-admin-to-myadmin" >' . __('Video Tutorial', self::slug) . '</a>) <br><strong>1) Re-configure server: (if require)</strong> <br> If you don\'t have a writable htaccess or enabled multi-site choose appropriate setup otherwise, HMWP updates your htaccess automatically and you can go to next step<br/><a target="_blank" href="' . add_query_arg(array('die_message' => 'single'), $page_url) . '" class="button">' . __('Manual Configuration', self::slug) . '</a> <a target="_blank" href="' . add_query_arg(array('die_message' => 'multisite'), $page_url) . '" class="button">' . __('Multisite Configuration (Apache)', self::slug) . '</a> <a target="_blank" href="' . add_query_arg(array('die_message' => 'nginx'), $page_url) . '" class="button">' . __('Nginx Configuration', self::slug) . '</a> <a target="_blank" href="' . add_query_arg(array('die_message' => 'iis'), $page_url) . '" class="button">' . __('IIS Configuration', self::slug) . '</a>
                 <br><br><strong> 2) <span style="color: #ee0000">Edit /wp-config.php  </span></strong><br>  Open wp-config.php using FTP and add following line somewhere before require_once(...) (if it already exist replace it with new code): <br><i><code>define("ADMIN_COOKIE_PATH",  "%1$s");</code></i><br><br>%4$s<a class="button " href="%3$s">Cancel and Use Current Admin Path</a>  <a class="button" target="_blank" href="%2$s">I Did it! (Login to New Dashboard)</a> </p><p style="color:green"><strong>If you get locked out of your WordPress site, use this link to instantly uninstall HMWP plugin - UPGRADE TO PRO <strong></p></div>', self::slug), preg_replace('|https?://[^/]+|i', '', get_option('siteurl') . '/') . $new_admin_path, add_query_arg(array('new_admin_action' => 'configured'), $page_url), add_query_arg(array('new_admin_action' => 'abort'), $page_url), '');
                break;
            case 'revert_admin':
                $title = "Reset Default Admin Path";
                $content = sprintf(__('<div class="error">Do not click back or close this tab. <br>Follow these steps <strong>IMMEDIATELY</strong> to enable new admin path or <a href="' . add_query_arg(array('new_admin_action' => 'abort'), $page_url) . '">Cancel</a> and try later.<p><strong><span style="color: #ee0000">Edit /wp-config.php: </span></strong><br>  Open wp-config.php using FTP and <span style="color: #ee0000"><strong>DELETE or comment (//)</strong></span> line which starts with following code: <br><code><i>define("ADMIN_COOKIE_PATH",  "...</i></code><br><br> <a class="button" href="%3$s">Cancel and Use Current Admin Path</a> <a class="button" href="%2$s" target="_blank">I Did it! (Login to Default Admin)</a></p></div>', self::slug), '', add_query_arg(array('new_admin_action' => 'configured'), $page_url), add_query_arg(array('new_admin_action' => 'abort'), $page_url));
                break;
        }
        wp_die('<h3>' . $title . '</h3>' . $content);
    }

    /**
     * HideMyWP::hmwpl_admin_css_js()
     *
     * Adds admin.js to options page
     * @return
     */
    function hmwpl_admin_css_js()
    {

        if (isset($_GET['page']) && $_GET['page'] == self::slug) {
            wp_enqueue_script('jquery');
            wp_register_script(self::slug . '_admin_js', self::url . '/js/admin.js', array('jquery'), self::ver, false);
            wp_enqueue_script(self::slug . '_admin_js');
            $translation_array = array(
                'delete_img' => HMW_URL . '/img/delete.png'
            );
            wp_localize_script( self::slug . '_admin_js', 'admin_obj', $translation_array );
        }

        wp_register_style( self::slug.'_admin_css', self::url. '/css/admin.css', array(), self::ver, 'all' );
        wp_enqueue_style( self::slug.'_admin_css' );
    }

    /**
     * HideMyWP::hmwpl_pp_settings_api_reset()
     * Filter after reseting Options
     * @return
     */
    function hmwpl_pp_settings_api_reset()
    {
        delete_option('hmw_all_plugins');
        delete_option('pp_important_messages');
        delete_option('pp_important_messages_last');
        delete_option('trust_network_rules');
        delete_option('hmwp_internal_assets');

        update_option('hmwp_temp_admin_path', 'wp-admin');
        flush_rewrite_rules();

    }

    /**
     * HideMyWP::hmwpl_pp_settings_api_filter()
     * Filter after updateing Options
     * @param mixed $post
     * @return
     */
    function hmwpl_pp_settings_api_filter($post)
    {
        global $wp_rewrite;


        update_option(self::slug . '_undo', get_option(self::slug));

        if ((isset($post[self::slug]['admin_key']) && $this->opt('admin_key') != $post[self::slug]['admin_key']) || (isset($post[self::slug]['login_query']) && $this->opt('login_query') != $post[self::slug]['login_query'])) {
            $body = "Hi-\nThis is %s plugin. Here is your new WordPress login address:\nURL: %s\n\nBest Regards,\n%s";

            if (isset($post[self::slug]['login_query']) && $post[self::slug]['login_query'])
                $login_query = $post[self::slug]['login_query'];
            else
                $login_query = 'hide_my_wp';

            $new_url = site_url('wp-login.php');
            if ($this->h->str_contains($new_url, 'wp-login.php'))
                $new_url = add_query_arg($login_query, $post[self::slug]['admin_key'], $new_url);

            $body = sprintf(__($body, self::slug), self::title, $new_url, self::title);
            $subject = sprintf(__('[%s] Your New WP Login!', self::slug), self::title);
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        if (!trim($this->opt('new_admin_path'), ' /') || trim($this->opt('new_admin_path'), ' /') == 'wp-admin')
            $current_admin_path = 'wp-admin';
        else
            $current_admin_path = trim($this->opt('new_admin_path'), ' /');

        if (isset($post['import_field']) && $post['import_field']) {
            $import_field = stripslashes($post['import_field']);
            $import_field = json_decode($import_field, true);
            $new_admin_path_input = (isset($import_field['new_admin_path']) && trim($import_field['new_admin_path'], '/ ')) ? $import_field['new_admin_path'] : 'wp-admin';
        } else {
            $new_admin_path_input = (isset($post[self::slug]['new_admin_path'])) ? $post[self::slug]['new_admin_path'] : '';
        }

        if (!trim($new_admin_path_input, ' /') || trim($new_admin_path_input, ' /') == 'wp-admin')
            $new_admin_path = 'wp-admin';
        else
            $new_admin_path = trim($new_admin_path_input, ' /');

        if ($new_admin_path != $current_admin_path) {
            //save temp value and return everything back whether it was enter by user or import fields
            if (isset($post['import_field']) && $post['import_field'])
                $post['import_field'] = str_replace('\"new_admin_path\":\"' . $new_admin_path . '\"', '\"new_admin_path\":\"' . $current_admin_path . '\"');
            else
                $post[self::slug]['new_admin_path'] = $current_admin_path;

            update_option('hmwp_temp_admin_path', $new_admin_path);
        }



        if (isset ($post[self::slug]['li']) && (strlen($post[self::slug]['li']) > 34 || strlen($post[self::slug]['li']) < 42))
            delete_option('pp_important_messages');

        flush_rewrite_rules();
        
        return $post;
    }

    /**
     * HideMyWP::hmwpl_add_login_key_to_action_from()
     * Add admin key to links in wp-login.php
     * @param string $url
     * @param string $path
     * @param string $scheme
     * @param int $blog_id
     * @return
     */
    function hmwpl_add_login_key_to_action_from($url, $path, $scheme, $blog_id)
    {
        if ($this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';

        if (trim($this->opt('new_login_path'), ' /') && trim($this->opt('new_login_path'), ' /') != 'wp-login.php')
            return str_replace('wp-login.php', trim($this->opt('new_login_path'), ' /'), $url);

        if ($url && $this->h->str_contains($url, 'wp-login.php'))
            if ($scheme == 'login' || $scheme == 'login_post')
                return add_query_arg($login_query, $this->opt('admin_key'), $url);


        return $url;
    }

    /**
     * HideMyWP::hmwpl_add_key_login_to_url()
     * Add admin key to wp-login url
     * @param mixed $url
     * @param string $redirect
     * @return
     */
    function hmwpl_add_key_login_to_url($url, $redirect = '0')
    {
        if ($this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';

        if ($this->opt('admin_key'))
            $admin_key = $this->opt('admin_key');
        else
            $admin_key = '1234';

        if (trim($this->opt('new_login_path'), ' /') && trim($this->opt('new_login_path'), ' /') != 'wp-login.php')
            return str_replace('wp-login.php', trim($this->opt('new_login_path'), ' /'), $url);

        if ($url && $this->h->str_contains($url, 'wp-login.php') && !$this->h->str_contains($url, $login_query) && !$this->h->str_contains($url, $admin_key) && !$this->h->str_contains($url, 'ref_url'))
            return add_query_arg($login_query, $this->opt('admin_key'), $url);

        return $url;
    }


    function hmwpl_add_key_login_to_messages($msg)
    {
        if ($this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';

        if ($this->opt('admin_key'))
            $admin_key = $this->opt('admin_key');
        else
            $admin_key = '1234';

        if ($msg && $this->h->str_contains($msg, '/comment.php?') && !$this->h->str_contains($msg, $login_query . '=' . $admin_key))
            return str_replace('/comment.php?', '/comment.php?' . $login_query . '=' . $admin_key, $msg);

        return $msg;
    }


    function hmwpl_correct_logout_redirect()
    {
        $url = $_SERVER['PHP_SELF'];

        if ($this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';


        if ($this->h->ends_with($url, 'wp-login.php') && isset($_REQUEST['action']) && $_REQUEST['action'] == 'logout') {
            if (!$this->h->str_contains($_SERVER['REQUEST_URI'], '/' . $this->opt('new_login_path'))) {
                $redirect_to = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : 'wp-login.php?loggedout=true&' . $login_query . '=' . $this->opt('admin_key');
                wp_redirect($redirect_to);
                exit();
            }
        }
    }

    /**
     * HideMyWP::ob_starter()
     *
     * @return
     */
    function hmwpl_ob_starter()
    {
        ob_start(array(&$this, "hmwpl_global_html_filter"));
        //echo ob_get_level();
        if (class_exists('WooCommerce'))
            ob_start(); //Fix some WooCommerce themes bug
    }

    /*function do_shutdown(){
        $final = ob_get_level().'sdsds';

        // We'll need to get the number of ob levels we're in, so that we can iterate over each, collecting that buffer's output into the final output.
        $levels = ob_get_level();

        for ($i = 0; $i < $levels; $i++) {
            $final .= ob_get_clean();
        }

        echo $this->hmwpl_global_html_filter($final);
    }*/    
    
    /**
     * HideMyWP::hmwpl_is_permalink()
     * Is permalink enabled?
     * @return
     */
    function hmwpl_is_permalink()
    {
        global $wp_rewrite;
        if (!isset($wp_rewrite) || !is_object($wp_rewrite) || !$wp_rewrite->using_permalinks())
            return false;
        return true;
    }

    /**
     * HideMyWP::hmwpl_block_access()
     *
     * @return
     */
    function hmwpl_block_access()
    {
        global $wp_query, $current_user;
        include_once(ABSPATH . '/wp-includes/pluggable.php');


        if (function_exists('is_user_logged_in') && is_user_logged_in())
            $visitor = $current_user->user_login;
        else
            $visitor = $_SERVER["REMOTE_ADDR"];

        $url = esc_url('http' . (empty($_SERVER['HTTPS']) ? '' : 's') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        // $wp_query->set('page_id', 2);
        // $wp_query->query($wp_query->query_vars);

        if ($this->opt('spy_notifier')) {
            $body = "Hi-\nThis is %s plugin. We guess someone is researching about your WordPress site.\n\nHere is some more details:\nVisitor: %s\nURL: %s\nUser Agent: %s\n\nBest Regards,\n%s";
            $body = sprintf(__($body, self::slug), self::title, $visitor, $url, $_SERVER['HTTP_USER_AGENT'], self::title);
            $subject = sprintf(__('[%s] Someone is mousing!', self::slug), self::title);
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        status_header(404);
        nocache_headers();

        $headers = array('X-Pingback' => get_bloginfo('pingback_url'));
        $headers['Content-Type'] = get_option('html_type') . '; charset=' . get_option('blog_charset');
        foreach ((array)$headers as $name => $field_value)
            @header("{$name}: {$field_value}");

        //if ( isset( $headers['Last-Modified'] ) && empty( $headers['Last-Modified'] ) && function_exists( 'header_remove' ) )
        //	@header_remove( 'Last-Modified' );


        //wp-login.php wp-admin and direct .php access can not be implemented using 'wp' hook block_access can't work correctly with init hook so we use wp_remote_get to fix the problem
        if ($this->h->str_contains($_SERVER['PHP_SELF'], '/wp-admin/') || $this->h->ends_with($_SERVER['PHP_SELF'], '.php')) {

            if ($this->opt('custom_404') && $this->opt('custom_404_page')) {
                wp_redirect(add_query_arg(array('by_user' => $visitor, 'ref_url' => urldecode($_SERVER["REQUEST_URI"])), home_url('?' . $this->opt('page_query') . '=' . $this->opt('custom_404_page'))));
            } else {
                $response = @wp_remote_get(home_url('/nothing_404_404' . $this->trust_key));

                if (!is_wp_error($response))
                    echo $response['body'];
                else
                    wp_redirect(home_url('/404_Not_Found'));
            }

        } else {
            if (get_404_template())
                require_once(get_404_template());
            else
                require_once(get_single_template());
        }

        die();
    }
    

    /**
     * HideMyWP::hmwpl_partial_filter()
     * Filter partial HTML
     * @param mixed $content
     * @return
     */
    function hmwpl_partial_filter($content)
    {

        if ($this->top_replace_old)
            $content = str_replace($this->top_replace_old, $this->top_replace_new, $content);

        if ($this->partial_replace_old)
            $content = str_replace($this->partial_replace_old, $this->partial_replace_new, $content);

        if ($this->partial_preg_replace_old)
            $content = preg_replace($this->partial_preg_replace_old, $this->partial_preg_replace_new, $content);

        return $content;
    }

    /**
     * HideMyWP::hmwpl_post_filter()
     * Filter post HTML
     * @param mixed $content
     * @return
     */
    function hmwpl_post_filter($content)
    {
        if ($this->post_replace_old)
            $content = str_replace($this->post_replace_old, $this->post_replace_new, $content);

        if ($this->post_preg_replace_old)
            $content = preg_replace($this->post_preg_replace_old, $this->post_preg_replace_new, $content);

        return $content;
    }

    function hmwpl_replace_field($type = 'replace_in_html')
    {
        $output = '<div class="field_wrapper ' . $type . '">';
        
        $output .= '<style>.first_field,.second_field, .action_field{float:left;}
.action_field{padding:10px;}
.hmwp_field_row{ margin:10px 3px;}
.hmwp_action{margin: 4px !important;}
</style>';

        $output .= '<a href="javascript:void(0);" class="button hmwp_action htmwp_add_button " title="Add Rule">
                               <img src="' . self::url . '/img/add.png" width="12" />
                               Add
                          </a>';
        $output .= '</div>';

        if ($type == 'replace_in_html') {
            $output .= "<br/><span class='description'>Do not use this to change URLs<br>Use<code>[bslash]</code> for '\'<br>Base on OSes multiple lines queries may work or not so please check.'</span>";
        } else {
            $output .= "<br/><span class='description'>Use this only to change URLs. <br>Relative path base on WP directory. e.g. wp-content/plugins/woocommerce/assets/css/woocommerce.css Replace ec.css<br>You can also replace some kind of custom paths<br>Add '/' at the end of the first path to change all files at the folder.  </span>";
        }

        return $output;
    }

    function hmwpl_disable_main_wp_query($sql, WP_Query $wpQuery)
    {
        if ($wpQuery->is_main_query() && (isset($_GET['style_internal_wrapper']) || isset($_GET['script_internal_wrapper']) || isset($_GET['style_wrapper']) || isset($_GET['get_wrapper']) || isset($_GET['parent_wrapper']) || isset($_GET['template_wrapper']))) {
            /* prevent SELECT FOUND_ROWS() query*/
            $wpQuery->query_vars['no_found_rows'] = true;

            /* prevent post term and meta cache update queries */
            $wpQuery->query_vars['cache_results'] = false;

            return false;
        }
        return $sql;
    }
    
    /**
     * @since 1.0.1
     * HideMyWP::custom_404_page()
     * 
     *
     * @param mixed $templates
     * @return
     */
    function custom_404_page($templates)
    {
        global $current_user;
        $visitor = esc_attr((is_user_logged_in()) ? $current_user->user_login : $_SERVER["REMOTE_ADDR"]);

        if (is_multisite())
            $permalink = get_blog_permalink(BLOG_ID_CURRENT_SITE, $this->opt('custom_404_page'));
        else
            $permalink = get_permalink($this->opt('custom_404_page'));
        //$permalink = home_url('?'.$this->opt('page_query').'='.$this->opt('custom_404_page'));
        if ($this->opt('custom_404') && $this->opt('custom_404_page'))
            wp_redirect(add_query_arg(array('by_user' => $visitor, 'ref_url' => urldecode($_SERVER["REQUEST_URI"])), $permalink));
        else
            return $templates;

        die();

    }

    /**
     * HideMyWP::hmwpl_global_html_filter()
     * Filter output HTML
     * @param mixed $buffer
     * @return
     */
    function hmwpl_global_html_filter($buffer)
    {

        $this->none_replaced_buffer = $buffer;

        //not replace for crons
        if (!$this->hmwpl_is_html($buffer) && isset($_GET['die_message']) || isset($_GET['style_internal_wrapper']) || isset($_GET['script_internal_wrapper']) || isset($_GET['style_wrapper']) || isset($_GET['get_wrapper']) || isset($_GET['parent_wrapper']) || isset($_GET['template_wrapper']) || isset($_GET['doing_wp_cron']))
            return $buffer;


        if (is_admin() && $this->admin_replace_old) {
            $buffer = str_replace($this->admin_replace_old, $this->admin_replace_new, $buffer);
            return $buffer;
        }        

        //first minify rocket then change other URLS
        if (function_exists('rocket_minify_process'))
            $buffer = rocket_minify_process($buffer);        

        if ($this->top_replace_old)
            $buffer = str_replace($this->top_replace_old, $this->top_replace_new, $buffer);
        

        //good but problem to find exclude ie and src= styles
        if ($this->opt('auto_internal')) {

            //!isset($_GET['doing_wp_cron']) && !style_internal_wrapper
            $blog_id = get_current_blog_id();
            if (!is_multisite())
                $old = get_option('hmwp_internal_assets');
            else
                $old = get_blog_option($blog_id, 'hmwp_internal_assets');
            if ($old)
                $new = $old;
            else
                $new = array('css' => '', 'js' => '');
        }

        if ($this->opt('auto_internal') == 1 || $this->opt('auto_internal') == 3) {

            preg_match_all("@<style(.*?)>(.*?)</style>@is",//conflict if it have inline ie tags
                $buffer,
                $matches,
                PREG_PATTERN_ORDER);

            $new_css = '';
            if (is_array($matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    if (!$matches[1][$i] || (!stristr("print'", $matches[1][$i]) && !stristr('print"', $matches[1][$i])))
                        $new_css .= $matches[2][$i] . "\n";
                }
            }

            //or is_home is_archive is_single is_author is_feed
            if ($new_css)
                $new['css'] = $new_css;

            $this->preg_replace_old[] = "@<style(.*?)>(.*?)</style>@is"; //prints will be removed but not remain|conflict if had inline ie tags
            $this->preg_replace_new[] = " ";
        }

        if ($this->opt('auto_internal') >= 2) {

            preg_match_all("@<script(.*?)>(.*?)</script>([\s]*\<\!\[)?@is",  //still not <!--<![
//conflict with ie conditional tag will be added
                $buffer,
                $matches,
                PREG_PATTERN_ORDER);

            $new_js = '';
            if (is_array($matches)) {
                for ($i = 0; $i < count($matches[1]); $i++) { //should be tested
                    if (!$matches[1][$i] || (!stristr("src=", $matches[1][$i]) && !stristr('<![', $matches[3][$i])))
                        $new_js .= $matches[2][$i] . "\n";
                }
            }

            //or is_home is_archive is_single is_author is_feed
            if ($new_js)
                $new['js'] = $new_js;

            $this->preg_replace_old[] = "@(<s2cript[^>]*>)(.*?)(</script>)@is";
            $this->preg_replace_new[] = "$1$3"; //src will remain the same
        }

        if (isset($new) && $new && $new != $old) {

            if (!is_multisite())
                update_option('hmwp_internal_assets', $new);
            else
                update_blog_option($blog_id, 'hmwp_internal_assets', $new);
        }        


        if ($this->replace_old)
            $buffer = str_replace($this->replace_old, $this->replace_new, $buffer);

        if ($this->preg_replace_old)
            $buffer = preg_replace($this->preg_replace_old, $this->preg_replace_new, $buffer);

        return $buffer;

    }    

    /**
     * HideMyWP::hmwpl_global_assets_filter()
     * Generate new style from main file
     * @return
     */
    function hmwpl_global_assets_filter()
    {
        global $wp_query;

        if ($this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';
        
        //$this->h->ends_with($_SERVER["REQUEST_URI"], 'main.css') ||   <- For multisite
        if (isset($wp_query->query_vars['style_wrapper']) && $wp_query->query_vars['style_wrapper'] && $this->hmwpl_is_permalink()) {            

            if (is_multisite() && isset($wp_query->query_vars['template_wrapper']))
                $css_file = str_replace(get_stylesheet(), $wp_query->query_vars['template_wrapper'], get_stylesheet_directory()) . '/style.css';
            else
                $css_file = get_stylesheet_directory() . '/style.css';

            status_header(200);
            //$expires = 60*60*24; // 1 day
            
            $css = file_get_contents($css_file);            

            // if (is_child_theme())
            //     $css = str_replace('/thematic/', '/parent/', $css);

            echo $css;

            //  if(extension_loaded('zlib'))
            //     ob_end_flush();

            exit;
        }

        if ((isset($wp_query->query_vars['parent_wrapper']) && $wp_query->query_vars['parent_wrapper'] && $this->hmwpl_is_permalink())) {            

            if (is_multisite() && isset($wp_query->query_vars['template_wrapper']))
                $css_file = str_replace(get_template(), $wp_query->query_vars['template_wrapper'], get_template_directory()) . '/style.css';
            else
                $css_file = get_template_directory() . '/style.css';


            status_header(200);
            //$expires = 60*60*24; // 1 day
            $expires = 60 * 60 * 24 * 3; //3 day
            header("Pragma: public");
            header("Cache-Control: maxage=" . $expires);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
            header('Content-type: text/css; charset=UTF-8');

            $css = file_get_contents($css_file);            

            // if (is_child_theme())
            //     $css = str_replace('/thematic/', '/parent/', $css);

            echo $css;

            //  if(extension_loaded('zlib'))
            //     ob_end_flush();

            exit;
        }


        if ((isset($wp_query->query_vars['style_internal_wrapper']) && $wp_query->query_vars['style_internal_wrapper'] && $this->hmwpl_is_permalink())) {

            status_header(200);
            //$expires = 60*60*24; // 1 day
            $expires = 60 * 60 * 24 * 10; //10 days
            header("Pragma: public");
            header("Cache-Control: maxage=" . $expires);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
            header('Content-type: text/css; charset=UTF-8');

            $blog_id = get_current_blog_id();
            if (!is_multisite())
                $old = get_option('hmwp_internal_assets');
            else
                $old = get_blog_option($blog_id, 'hmwp_internal_assets');

            if (is_array($old))
                echo $old['css'] . "\n\n\n";

            echo $this->auto_config_internal_css;

            //  if(extension_loaded('zlib'))
            //     ob_end_flush();
            exit;
        }


        if ((isset($wp_query->query_vars['script_internal_wrapper']) && $wp_query->query_vars['script_internal_wrapper'] && $this->hmwpl_is_permalink())) {            

            status_header(200);
            //$expires = 60*60*24; // 1 day
            $expires = 60 * 60 * 24 * 10; //10 day
            header("Pragma: public");
            header("Cache-Control: maxage=" . $expires);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
            header('Content-type: application/javascript; charset=UTF-8');

            $blog_id = get_current_blog_id();
            if (!is_multisite())
                $old = get_option('hmwp_internal_assets');
            else
                $old = get_blog_option($blog_id, 'hmwp_internal_assets');

            if (is_array($old))
                echo $old['js'] . "\n\n\n";

            //todo: avoid duplicate with auto_internal
            echo $this->auto_config_internal_js;            

            //  if(extension_loaded('zlib'))
            //     ob_end_flush();
            exit;
        }


        if ((isset($wp_query->query_vars['get_wrapper']) && $wp_query->query_vars['get_wrapper'] && $this->hmwpl_is_permalink() && isset($_GET['_case']) && isset($_GET['_addr']))) {
//RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*) /wp39/index.php?get_wrapper=1&_case=$1&_addr=$2&AK_hide_my_wp=1234 [QSA,L]
            

            $host = $url = '';
            $data = array();
            $cache = true;

            switch ($_GET['_case']) {

                case 'ws0':
                    $host = 'https://s0.wp.com/'; //add /
                    $cache = false;
                    break;
                case 'ws0i':
                    $host = 'https://s0.wp.com/i/'; //add /
                    $cache = true;
                    break;
                case 'stats':
                    $host = 'http://stats.wp.com/';
                    $cache = false;
                    break;
                case 'ws0js':
                    $host = 'http://s0.wp.com/wp-content/js/';
                    if ($_GET['_addr'] == 'devicepx-jetpack.js')
                        $_GET['_addr'] = 'devicepx.js';
                    $cache = true;
                    break;
                default:
                    $host = 'Invalid';
                    $cache = false;
                    break;
            }


            $url = $host . $_GET['_addr'];

            //echo $url;
            $cache_name = hash('crc32', $_GET['_case'] . $_GET['_addr']);

            if ($cache)
                $data = get_transient($cache_name);

            if (!$data) {
                $data = @wp_remote_get($url, 'reject_unsafe_urls=true&sslverify=true&user-agent=Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0&limit_response_size=' . 3 * 1024 * 1024); //3mb
                if ($cache && is_array($data))
                    set_transient($cache_name, $data, 10 * DAY_IN_SECONDS);
            }

            //print_r($data);
            if (!is_wp_error($data) && $data['body']) {

                // print_r($data['headers']->getAll());

                status_header($data['response']['code']);

                //$expires = 60 * 60 * 24 * 10; //10 days
                if ($data['headers']->offsetGet('content-type'))
                    header('Content-Type: ' . $data['headers']->offsetGet('content-type'));

                if (is_array($data['headers']->offsetGet('cache-control'))) {
                    foreach ($data['headers']->offsetGet('cache-control') as $control)
                        header("Cache-Control: " . $control);
                } else {
                    header("Cache-Control: " . $data['headers']->offsetGet('cache-control'));
                }

                if ($data['headers']->offsetGet('pragma'))
                    header('Pragma: ' . $data['headers']->offsetGet('pragma'));

                if ($data['headers']->offsetGet('expires'))
                    header('Expires: ' . $data['headers']->offsetGet('expires'));

                echo $data['body'];
            } else {
                status_header('400');
                echo 'Wrapping Error';
            }
            exit;
        }

    }
    
    /**
     * HideMyWP::hmwpl_init()
     *
     * @return
     */
    function hmwpl_init()
    {
        require_once('init.php');
    }    
    

    function hmwpl_admin_current_cookie()
    {
        if (SITECOOKIEPATH)
            $current_cookie = str_replace(SITECOOKIEPATH, '', ADMIN_COOKIE_PATH);

        //For non-sudomain and with pathes mu:
        if (!$current_cookie)
            $current_cookie = 'wp-admin';

        return $current_cookie;
    }

    /**
     * HideMyWP::hmwpl_add_rewrite_rules()
     *
     * @param mixed $wp_rewrite
     * @return
     */
    function hmwpl_add_rewrite_rules($wp_rewrite)
    {
        global $wp_rewrite, $wp;

        if (is_multisite()) {
            global $current_blog;
            $sitewide_plugins = array_keys((array)get_site_option('active_sitewide_plugins', array()));
            $active_plugins = array_merge((array)get_blog_option(BLOG_ID_CURRENT_SITE, 'active_plugins'), $sitewide_plugins);
        } else {
            $active_plugins = get_option('active_plugins');
        }

        if ($this->opt('new_include_path') && $this->hmwpl_is_permalink()) {
            $rel_include_path = $this->sub_folder . trim(WPINC);
            $new_include_path = trim($this->opt('new_include_path'), '/ ');

            $new_non_wp_rules[$new_include_path . '/(.*)'] = $rel_include_path . '/$1' . $this->trust_key;

            if (is_multisite()) {
                $rel_include_path = $this->blog_path . str_replace($this->sub_folder, '', $rel_include_path);
                if ($this->is_subdir_mu)
                    $new_include_path = '/' . $new_include_path;
            }

            $this->partial_replace_old[] = $rel_include_path;
            $this->partial_replace_new[] = $new_include_path;
        }

        $rel_admin_path = $this->sub_folder . 'wp-admin';
        $new_admin_path = trim($this->opt('new_admin_path'), '/ ');


        if ($new_admin_path && $new_admin_path != 'wp-admin' && $this->hmwpl_is_permalink()) {

            /*  if (trim(get_option('hmwp_temp_admin_path'), ' /'))
                  $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
              else
                  $new_admin_path = trim($this->opt('new_admin_path'), '/ ');*/

            $new_non_wp_rules[$new_admin_path . '/(.*)'] = $rel_admin_path . '/$1' . $this->trust_key;

            if (is_multisite()) {
                if ($this->is_subdir_mu)
                    $new_admin_path = '/' . $new_admin_path;
                $rel_admin_path = $this->blog_path . str_replace($this->sub_folder, '', $rel_admin_path);
            }
            //Add / to fix stylesheet and other 'wp-admin'
            //will break all Replace URLs to wp-admin plus all urls of it
            $this->admin_replace_old[] = $rel_admin_path . '/';
            $this->admin_replace_new[] = $new_admin_path . '/';


            //Fix config code for HMWP nginx / multisite, etc
            if (isset($_GET['page']) && $_GET['page'] == self::slug) {
                $this->admin_replace_old[] = $new_admin_path . '/$';
                $this->admin_replace_new[] = $rel_admin_path . '/$';

                $this->admin_replace_old[] = $new_admin_path . '/admin-ajax.php [QSA';
                $this->admin_replace_new[] = 'wp-admin/admin-ajax.php' . $this->trust_key . ' [QSA';

                $this->admin_replace_old[] = $new_admin_path . '/(!network';
                $this->admin_replace_new[] = 'wp-admin/(!network';

                $this->admin_replace_old[] = $new_admin_path . '/admin-ajax.php last;';
                $this->admin_replace_new[] = 'wp-admin/admin-ajax.php' . $this->trust_key . ' last;';
            }

        }


        if ($this->opt('new_upload_path') && $this->hmwpl_is_permalink()) {
            $upload_path = wp_upload_dir();

            if (is_ssl())
                $upload_path['baseurl'] = str_replace('http:', 'https:', $upload_path['baseurl']);

            if (is_multisite() && $current_blog->blog_id != BLOG_ID_CURRENT_SITE) {

                $upload_path_array = explode('/', $upload_path['baseurl']);
                array_pop($upload_path_array);
                array_pop($upload_path_array);
                $upload_path['baseurl'] = implode('/', $upload_path_array);

            }

            $rel_upload_path = $this->sub_folder . trim(str_replace(site_url(), '', $upload_path['baseurl']), '/');;
            $new_upload_path = trim($this->opt('new_upload_path'), '/ ');
            $new_non_wp_rules[$new_upload_path . '/(.*)'] = $rel_upload_path . '/$1' . $this->trust_key;

            if (is_multisite()) {
                $rel_upload_path = str_replace($this->sub_folder, '', $rel_upload_path);
                if ($this->is_subdir_mu)
                    $new_upload_path = str_replace($this->blog_path, '/', home_url($new_upload_path));
            }


            $this->replace_old[] = home_url($rel_upload_path);  //Fix external images problem

            if (is_multisite())
                $this->replace_new[] = $new_upload_path; //already added home_url!
            else
                $this->replace_new[] = home_url($new_upload_path);
            
        }


        if ($this->opt('new_login_path') && $this->opt('new_login_path') != 'wp-login.php' && $this->hmwpl_is_permalink()) {

            $rel_login_path = $this->sub_folder . '/wp-login.php';

            $new_login_path = trim($this->opt('new_login_path'), '/ ');
            $new_non_wp_rules[$new_login_path] = $rel_login_path . $this->trust_key;

            if (is_multisite()) {
                if ($this->is_subdir_mu)
                    $new_login_path = '/' . $new_login_path;
                $rel_login_path = $this->blog_path . str_replace($this->sub_folder, '', $new_login_path);
            }

//            $this->partial_replace_old[]=$rel_plugin_path;
//            $this->partial_replace_new[]=$new_plugin_path;


        }


        if ($this->opt('new_plugin_path') && $this->hmwpl_is_permalink()) {
            $rel_plugin_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_PLUGIN_URL), '/');

            $new_plugin_path = trim($this->opt('new_plugin_path'), '/ ');
            $new_non_wp_rules[$new_plugin_path . '/(.*)'] = $rel_plugin_path . '/$1' . $this->trust_key;

            if (is_multisite()) {
                if ($this->is_subdir_mu)
                    $new_plugin_path = '/' . $new_plugin_path;
                $rel_plugin_path = $this->blog_path . str_replace($this->sub_folder, '', $rel_plugin_path);
            }

            $this->partial_replace_old[] = $rel_plugin_path;
            $this->partial_replace_new[] = $new_plugin_path;
            
        }


        if ($this->hmwpl_add_auto_internal('css') && !isset($_POST['wp_customize']) && $this->hmwpl_is_permalink()) {
            $auto_path = '_auto\.css';

            //not multisite
            if ($this->sub_folder)
                $new_non_wp_rules[$auto_path] = add_query_arg('style_internal_wrapper', '1', $this->sub_folder) . str_replace('?', '&', $this->trust_key);
            else
                $new_non_wp_rules[$auto_path] = '/index.php?style_internal_wrapper=1' . str_replace('?', '&', $this->trust_key);

        }

        if ($this->hmwpl_add_auto_internal('js') && !isset($_POST['wp_customize']) && $this->hmwpl_is_permalink()) {
            $auto_path = '_auto\.js';

            //not multisite
            if ($this->sub_folder)
                $new_non_wp_rules[$auto_path] = add_query_arg('script_internal_wrapper', '1', $this->sub_folder) . str_replace('?', '&', $this->trust_key);
            else
                $new_non_wp_rules[$auto_path] = '/index.php?script_internal_wrapper=1' . str_replace('?', '&', $this->trust_key);

        }        


        if ($this->opt('new_theme_path') && $this->hmwpl_is_permalink() && !isset($_POST['wp_customize'])) {
            $rel_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');

            $new_theme_path = trim($this->opt('new_theme_path'), '/ ');
            $new_non_wp_rules[$new_theme_path . '/(.*)'] = $rel_theme_path . '/$1' . $this->trust_key;

            if (is_multisite()) {
                if ($this->is_subdir_mu)
                    $new_theme_path = '/' . $new_theme_path;
                $rel_theme_path_with_theme = trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
                $rel_theme_path = $this->blog_path . str_replace('/' . get_stylesheet(), '', $rel_theme_path_with_theme); //without theme
            }

            $this->partial_replace_old[] = $rel_theme_path;
            $this->partial_replace_new[] = $new_theme_path;            

            if (is_child_theme()) {
                //remove the end folder so we can replace it with parent theme
                $path_array = explode('/', $new_theme_path);
                array_pop($path_array);
                $path_string = implode('/', $path_array);

                if ($path_string)
                    $path_string = $path_string . '/';

                $parent_theme_new_path = $path_string . get_template();
                $rel_parent_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_template_directory_uri()), '/');
                $parent_theme_new_path_with_main = $new_theme_path . '_main';

                if ($this->sub_folder)
                    $new_non_wp_rules[$parent_theme_new_path_with_main . '/style\.css'] = add_query_arg('parent_wrapper', '1', $this->sub_folder) . str_replace('?', '&', $this->trust_key);
                else
                    $new_non_wp_rules[$parent_theme_new_path_with_main . '/style\.css'] = '/index.php?parent_wrapper=1' . str_replace('?', '&', $this->trust_key);


                $new_non_wp_rules[$parent_theme_new_path . '/(.*)'] = $rel_parent_theme_path . '/$1' . $this->trust_key;
                $new_non_wp_rules[$parent_theme_new_path_with_main . '/(.*)'] = $rel_parent_theme_path . '/$1' . $this->trust_key;

                if (!is_multisite()) {
                    $this->partial_replace_old[] = $rel_parent_theme_path;
                    $this->partial_replace_new[] = $parent_theme_new_path_with_main;
                }
                
            }
        }        

        if (trim($this->opt('new_content_path'), ' /') && trim($this->opt('new_content_path'), '/ ') != 'wp-content' && $this->hmwpl_is_permalink()) {
            $new_content_path = trim($this->opt('new_content_path'), ' /');
            $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');

            $new_non_wp_rules[$new_content_path . '/(.*)'] = $rel_content_path . '/$1' . $this->trust_key;

            $this->replace_old[] = str_replace('/', '\/', $rel_content_path);
            $this->replace_new[] = str_replace('/', '\/', $new_content_path);
        }                        

        if ($this->opt('disable_directory_listing') && $this->hmwpl_is_permalink()) {
            $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');
            $rel_include_path = $this->sub_folder . trim(WPINC);

            $new_non_wp_rules['(((' . $rel_content_path . '|' . $rel_include_path . ')/([A-Za-z0-9\-\_\/]*))|(wp-admin/(!network\/?)([A-Za-z0-9\-\_\/]+)))(\.txt|/)$'] = 'nothing_404_404' . $this->trust_key;
        }
        
        add_filter('mod_rewrite_rules', array(&$this, 'hmwpl_mod_rewrite_rules'), 10, 1);


        if (isset($new_non_wp_rules) && $this->hmwpl_is_permalink())
            $wp_rewrite->non_wp_rules = array_merge($wp_rewrite->non_wp_rules, $new_non_wp_rules);

        return $wp_rewrite;

    }

    /**
     * HideMyWP::hmwpl_mod_rewrite_rules()
     * Fix WP generated rules
     * @param mixed $key
     * @return
     */
    function hmwpl_mod_rewrite_rules($rules)
    {
        $home_root = parse_url(home_url());

        if (isset($home_root['path']))
            $home_root = trailingslashit($home_root['path']);
        else
            $home_root = '/';        
        
        $rules = $this->hmwpl_cleanup_config($rules);

        return $rules;
    }

    function hmwpl_tinymce_emoji($plugins)
    {
        if (is_array($plugins)) {
            return array_diff($plugins, array('wpemoji'));
        } else {
            return array();
        }
    }

    /**
     * HideMyWP::hmwpl_on_activate_callback()
     *
     * @return
     */
    function hmwpl_on_activate_callback()
    {
        flush_rewrite_rules();
    }


    function hmwpl_add_auto_internal($type = 'css')
    {
        if ($type == 'css')
            return ($this->opt('new_theme_path') && ($this->opt('auto_internal') == 1 || $this->opt('auto_internal') == 3 || $this->auto_config_internal_css));
        elseif ($type == 'js')
            return ($this->opt('new_theme_path') && ($this->opt('auto_internal') >= 2 || $this->auto_config_internal_js));        
    }


    function hmwpl_add_get_wrapper()
    {
        return false;
    }

    /**
     * Register deactivation hook
     * HideMyWP::hmwpl_on_deactivate_callback()
     *
     * @return
     */
    function hmwpl_on_deactivate_callback()
    {
        delete_option(self::slug);
        delete_option('hmwp_temp_admin_path');
        delete_option('trust_network_rules');
        delete_option('hmwp_internal_assets');
        flush_rewrite_rules();
    }

    /**
     * HideMyWP::opt()
     * Get options value
     * @param mixed $key
     * @return
     */
    function opt($key)
    {
        if (isset($this->options[$key]))
            return $this->options[$key];
        return false;
    }


    function set_opt($key, $value)
    {
        if (is_multisite()) {
            $opts = get_blog_option(BLOG_ID_CURRENT_SITE, self::slug);
            $opts[$key] = $value;
            update_blog_option(BLOG_ID_CURRENT_SITE, self::slug, $opts);
        } else {
            $opts = get_option(self::slug);
            $opts[$key] = $value;
            update_option(self::slug, $opts);
        }
    }

    function hmwpl_update_attr($query)
    {
        $query['li'] = $this->opt('li');
        return $query;
    }

    function hmwpl_undo_config()
    {
        $html = '<a href="' . add_query_arg(array('undo_config' => true)) . '" class="button">' . __('Undo Previous Settings', self::slug) . '</a>';
        $html .= sprintf('<br><span class="description"> %s</span>', "Click above to restore previous saved settings!");

        if (isset($_GET['undo_config']) && $_GET['undo_config'] && !isset($_GET['undo'])) {

            $previous = get_option(self::slug . '_undo');

            if (!$previous['new_admin_path'])
                $previous['new_admin_path'] = 'wp-admin';

            update_option('hmwp_temp_admin_path', $previous['new_admin_path']);

            $previous['new_admin_path'] = trim($this->opt('new_admin_path'), ' /');

            update_option(self::slug, $previous);

            wp_redirect(add_query_arg(array('undo_config' => true, 'undo' => 'done')));
        }
        return $html;
    }

    function hmwpl_cleanup_config($config)
    {
        $config = str_replace('//', '/', $config);
        if (defined('WP_SITEURL'))
            $config = str_replace(WP_SITEURL, '', $config);

        if (defined('WP_HOME'))
            $config = str_replace(WP_HOME, '', $config);

        return str_replace(array(site_url(), home_url()), '', $config);
    }

    function hmwpl_nginx_config()
    {
        $new_theme_path = trim($this->opt('new_theme_path'), '/ ');
        $new_plugin_path = trim($this->opt('new_plugin_path'), '/ ');
        $new_upload_path = trim($this->opt('new_upload_path'), '/ ');
        $new_include_path = trim($this->opt('new_include_path'), '/ ');        
        $new_content_path = trim($this->opt('new_content_path'), '/ ');

        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        else
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');

        $rel_login_path = $this->sub_folder . '/wp-login.php';
        $new_login_path = str_replace('.', '\.', trim($this->opt('new_login_path'), '/ '));

        if (is_multisite()) {
            if ($this->is_subdir_mu)
                $new_login_path = '/' . $new_login_path;
            $rel_login_path = $this->blog_path . str_replace($this->sub_folder, '', $new_login_path);
        }       

        $upload_path = wp_upload_dir();

        //not required for nginx
        $sub_install = '';

        if (is_ssl())
            $upload_path['baseurl'] = str_replace('http:', 'https:', $upload_path['baseurl']);

        $rel_upload_path = $this->sub_folder . trim(str_replace(site_url(), '', $upload_path['baseurl']), '/');
        $rel_include_path = $this->sub_folder . trim(WPINC);
        $rel_plugin_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_PLUGIN_URL), '/');
        $rel_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_comments_post = $this->sub_folder . 'wp-comments-post.php';
        $rel_admin_ajax = $this->sub_folder . 'wp-admin/admin-ajax.php';


        $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');
        $rel_theme_path_no_template = str_replace('/' . get_stylesheet(), '', $rel_theme_path);

        $style_path_reg = '';        


        $hide_other_file_rule = $this->sub_folder . 'readme\.html|' . $this->sub_folder . 'license\.txt|' . $rel_content_path . '/debug\.log' . $style_path_reg . '|' . $rel_include_path . '/$';

        $disable_directoy_listing = '(((' . $rel_content_path . '|' . $rel_include_path . ')/([A-Za-z0-9\-\_\/]*))|(wp-admin/(!network\/?)([A-Za-z0-9\-\_\/]+)))(\.txt|/)$';

        if ($this->opt('login_query') && $this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';
        
        $antispam = '';        


        $output = '';        
        
        if (is_multisite()) {
            $sitewide_plugins = array_keys((array)get_site_option('active_sitewide_plugins', array()));
            $active_plugins = array_merge((array)get_blog_option(BLOG_ID_CURRENT_SITE, 'active_plugins'), $sitewide_plugins);
        } else {
            $active_plugins = get_option('active_plugins');
        }

        $pre_plugin_path = '';

        if (is_child_theme()) {
            //remove the end folder of so we can replace it with parent theme
            $path_array = explode('/', $new_theme_path);
            array_pop($path_array);
            $path_string = implode('/', $path_array);

            if ($path_string)
                $path_string = $path_string . '/';

            $parent_theme_new_path = $path_string . get_template();
            $rel_parent_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_template_directory_uri()), '/');
            $output .= 'rewrite ^/' . $parent_theme_new_path . '/(.*) /' . $rel_parent_theme_path . '/$1' . $this->trust_key . ' last;' . "\n";
            $parent_theme_new_path_with_main = $new_theme_path . '_main';

            $output .= 'rewrite ^/' . $parent_theme_new_path_with_main . '/style\.css' . ' /?parent_wrapper=1' . str_replace('?', '&', $this->trust_key) . ' last;' . "\n";

            $output .= 'rewrite ^/' . $parent_theme_new_path_with_main . '/(.*) /' . $rel_parent_theme_path . '/$1' . $this->trust_key . ' last;' . "\n";
        }


        if ($new_admin_path && $new_admin_path != 'wp-admin')
            $output .= 'rewrite ^/' . $new_admin_path . '/(.*) /' . $this->sub_folder . 'wp-admin/$1' . $this->trust_key . ' last;' . "\n";

        if ($new_login_path && $new_login_path != 'wp-login.php')
            $output .= 'rewrite ^/' . $new_login_path . ' /' . $this->sub_folder . $rel_login_path . $this->trust_key . ' last;' . "\n";

        if ($new_include_path)
            $output .= 'rewrite ^/' . $new_include_path . '/(.*) /' . $rel_include_path . '/$1' . $this->trust_key . ' last;' . "\n";

        if ($new_upload_path)
            $output .= 'rewrite ^/' . $new_upload_path . '/(.*) /' . $rel_upload_path . '/$1' . $this->trust_key . ' last;' . "\n";

        if ($new_plugin_path && $pre_plugin_path)
            $output .= $pre_plugin_path;

        if ($new_plugin_path)
            $output .= 'rewrite ^/' . $new_plugin_path . '/(.*) /' . $rel_plugin_path . '/$1' . $this->trust_key . ' last;' . "\n";        

        if ($this->hmwpl_add_auto_internal('css'))
            $output .= 'rewrite ^/_auto\.css' . ' /?style_internal_wrapper=1' . str_replace('?', '&', $this->trust_key) . ' last;' . "\n";

        if ($this->hmwpl_add_get_wrapper())
            $output .= 'rewrite ^/_get/([A-Za-z0-9-_\.]+)/(.*)' . ' /?get_wrapper=1&_case=$1&_addr=$2' . str_replace('?', '&', $this->trust_key) . ' last;' . "\n";
        //RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*) /wp39/index.php?get_wrapper=1&_case=$1&_addr=$2&AK_hide_my_wp=1234 [QSA,L]
        

        if ($new_theme_path)
            $output .= 'rewrite ^/' . $new_theme_path . '/(.*) /' . $rel_theme_path . '/$1' . $this->trust_key . ' last;' . "\n";                

        if ($new_content_path)
            $output .= 'rewrite ^/' . $new_content_path . '/(.*) /' . $rel_content_path . '/$1' . $this->trust_key . ' last;' . "\n";
        

        if ($this->opt('disable_directory_listing'))
            $output .= 'rewrite ^/' . $disable_directoy_listing . ' /nothing_404_404' . $this->trust_key . ' last;' . "\n";        


        if ($output)
            //$output='if (!-e $request_filename) {'. "\n" .  $output . "     break;\n}";
            $output = "# BEGIN Hide My WP\n\n" . $output . "\n# END Hide My WP";
        else
            $output = __('Nothing to add for current settings.', self::slug);

        $output = $this->hmwpl_cleanup_config($output);


        $html = '';
        $desc = __('Add to Nginx config file to get all features of the plugin. <br>', self::slug);

        if (isset($_GET['nginx_config']) && $_GET['nginx_config']) {

            $html = sprintf('%s ', $desc);
            $html .= sprintf('<span class="description">
        <ol style="color:#ff9900">
        <li>Nginx vhosts config file usually located in /etc/nginx/sites-available/YOURSITE or /etc/nginx/sites-available/default or /etc/nginx/nginx.conf  </li>
        <li>Add below lines right before <code>try_files $uri $uri/ /index.php?q=$uri&$args;</code> (it should not have #).  </li>
        <li>Restart Nginx to see changes</li>
        <li>You may need to re-configure the server whenever you change settings or activate a new theme or plugin.</li>
        <li>If you use sub-directory for WP block you have to add that directory before all of below pathes (e.g. rewrite ^/wordpress/lib/(.*) /wordpress/wp-includes/$1 or rewrite ^/wordpress/(.*)\.php(.*) /wordpress/nothing_404_404)</li></ol></span><textarea readonly="readonly" onclick="" rows="5" cols="55" class="regular-text %1$s" id="%2$s" name="%2$s" style="%4$s">%3$s</textarea>', 'nginx_config_class', 'nginx_config', esc_textarea($output), 'width:95% !important;height:400px !important');


        } else {
            $html = '<a target="_blank" href="' . add_query_arg(array('die_message' => 'nginx')) . '" class="button">' . __('Nginx Configuration', self::slug) . '</a>';
            $html .= sprintf('<br><span class="description"> %s</span>', $desc);
        }


        return $html;
        //rewrite ^/assets/css/(.*)$ /wp-content/themes/roots/assets/css/$1 last;


    }

    function hmwpl_iis_config()
    {
        $new_theme_path = trim($this->opt('new_theme_path'), '/ ');
        $new_plugin_path = trim($this->opt('new_plugin_path'), '/ ');
        $new_upload_path = trim($this->opt('new_upload_path'), '/ ');
        $new_include_path = trim($this->opt('new_include_path'), '/ ');        
        $new_content_path = trim($this->opt('new_content_path'), '/ ');

        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        else
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');

        $rel_login_path = $this->sub_folder . '/wp-login.php';
        $new_login_path = str_replace('.', '\.', trim($this->opt('new_login_path'), '/ '));

        if (is_multisite()) {
            if ($this->is_subdir_mu)
                $new_login_path = '/' . $new_login_path;
            $rel_login_path = $this->blog_path . str_replace($this->sub_folder, '', $new_login_path);
        }        

        $upload_path = wp_upload_dir();

        //not required for nginx
        $sub_install = '';

        $page_query = ($this->opt('page_query')) ? $this->opt('page_query') : 'page_id';

        $iis_not_found = 'index.php?' . $page_query . '=999999999';

        if (is_ssl())
            $upload_path['baseurl'] = str_replace('http:', 'https:', $upload_path['baseurl']);

        $rel_upload_path = $this->sub_folder . trim(str_replace(site_url(), '', $upload_path['baseurl']), '/');
        $rel_include_path = $this->sub_folder . trim(WPINC);
        $rel_plugin_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_PLUGIN_URL), '/');
        $rel_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_comments_post = $this->sub_folder . 'wp-comments-post.php';
        $rel_admin_ajax = $this->sub_folder . 'wp-admin/admin-ajax.php';


        $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');
        $rel_theme_path_no_template = str_replace('/' . get_stylesheet(), '', $rel_theme_path);

        $style_path_reg = '';

        $hide_other_file_rule = $this->sub_folder . 'readme\.html|' . $this->sub_folder . 'license\.txt|' . $rel_content_path . '/debug\.log' . $style_path_reg . '|' . $rel_include_path . '/$';

        //Customized for iis! removed 2\ and replaced ? and removed /
        $disable_directoy_listing = '(((' . $rel_content_path . '|' . $rel_include_path . ')([A-Za-z0-9-_\/]*))|(wp-admin/(?!network\/)([A-Za-z0-9-_\/]+)))(\.txt|/)$';

        if ($this->opt('login_query') && $this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';
        
        $antispam = '';        


        $output = '';

        if (is_multisite()) {
            $sitewide_plugins = array_keys((array)get_site_option('active_sitewide_plugins', array()));
            $active_plugins = array_merge((array)get_blog_option(BLOG_ID_CURRENT_SITE, 'active_plugins'), $sitewide_plugins);
        } else {
            $active_plugins = get_option('active_plugins');
        }

        $pre_plugin_path = '';

        if (is_child_theme()) {
            //remove the end folder of so we can replace it with parent theme
            $path_array = explode('/', $new_theme_path);
            array_pop($path_array);
            $path_string = implode('/', $path_array);

            if ($path_string)
                $path_string = $path_string . '/';

            $parent_theme_new_path = $path_string . get_template();
            $rel_parent_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_template_directory_uri()), '/');

            $output .= '<rule name="HMWP Theme' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $parent_theme_new_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_parent_theme_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


            $parent_theme_new_path_with_main = $new_theme_path . '_main';

            $output .= '<rule name="HMWP Theme' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $parent_theme_new_path_with_main . '/style\.css"  />' . "\n\t" . '<action type="Rewrite" url="' . '/index.php?parent_wrapper=1' . str_replace('?', '&amp;', $this->trust_key) . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";

            $output .= '<rule name="HMWP Theme' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $parent_theme_new_path_with_main . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_parent_theme_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


        }


        if ($new_admin_path && $new_admin_path != 'wp-admin')
            $output .= '<rule name="HMWP Admin' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_admin_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $this->sub_folder . 'wp-admin/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


        if ($new_login_path && $new_login_path != 'wp-login.php')
            $output .= '<rule name="HMWP Login' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_login_path . '"  />' . "\n\t" . '<action type="Rewrite" url="' . $this->sub_folder . trim($rel_login_path, '/') . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


        if ($new_include_path)
            $output .= '<rule name="HMWP Include' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_include_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_include_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";

        if ($new_upload_path)
            $output .= '<rule name="HMWP Upload' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_upload_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_upload_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


        if ($new_plugin_path && $pre_plugin_path)
            $output .= $pre_plugin_path;

        if ($new_plugin_path)
            $output .= '<rule name="HMWP Plugin_Dir' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_plugin_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_plugin_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";        

        if ($this->hmwpl_add_auto_internal('css'))
            $output .= '<rule name="HMWP Int Style' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^_auto\.css' . '"  />' . "\n\t" . '<action type="Rewrite" url="' . '/index.php?style_internal_wrapper=1' . str_replace('?', '&amp;', $this->trust_key) . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";

        if ($this->hmwpl_add_auto_internal('js'))
            $output .= '<rule name="HMWP Int Script' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^_auto\.js' . '"  />' . "\n\t" . '<action type="Rewrite" url="' . '/index.php?script_internal_wrapper=1' . str_replace('?', '&amp;', $this->trust_key) . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";


        if ($this->hmwpl_add_get_wrapper())
            $output .= '<rule name="HMWP Get' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^_get/([A-Za-z0-9-_\.]+)/(.*)' . '"  />' . "\n\t" . '<action type="Rewrite" url="' . '/index.php?get_wrapper=1&_case={R:1}&_addr={R:1}' . str_replace('?', '&amp;', $this->trust_key) . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";        

        if ($new_theme_path)
            $output .= '<rule name="HMWP Theme' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_theme_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_theme_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";
                

        if ($new_content_path)
            $output .= '<rule name="HMWP Content' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $new_content_path . '/(.*)"  />' . "\n\t" . '<action type="Rewrite" url="' . $rel_content_path . '/{R:1}' . $this->trust_key . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";
        

        if ($this->opt('disable_directory_listing'))
            $output .= '<rule name="HMWP Dir_List' . rand(0, 9999) . '" stopProcessing="true">' . "\n\t" . '<match url="^' . $disable_directoy_listing . '"  />' . "\n\t" . '<action type="Rewrite" url="' . $iis_not_found . '"  appendQueryString="true" />' . "\n" . '</rule>' . "\n";
        

        if ($output)
            //$output='if (!-e $request_filename) {'. "\n" .  $output . "     break;\n}";
            $output = "# BEGIN Hide My WP\n\n" . $output . "\n# END Hide My WP";
        else
            $output = __('Nothing to add for current settings.', self::slug);

        $output = $this->hmwpl_cleanup_config($output);

        $html = '';
        $desc = __('Add to web.config to get all features of the plugin<br>', self::slug);

        if (isset($_GET['iis_config']) && $_GET['iis_config']) {

            $html = sprintf('%s ', $desc);
            $html .= sprintf('<span class="description">
        <ol style="color:#ff9900">
        <li>Web.config file is located in WP root directory</li>
        <li>Add it to right before <strong>&lt;rule name="wordpress" patternSyntax="Wildcard"&gt; </strong></li>
        <li>You may need to re-configure the server whenever you change settings or activate a new theme or plugin.</li>
        </ol></span><textarea readonly="readonly" onclick="" rows="5" cols="55" class="regular-text %1$s" id="%2$s" name="%2$s" style="%4$s">%3$s</textarea>', 'iis_config_class', 'iis_config', esc_textarea($output), 'width:95% !important;height:400px !important');


        } else {
            $html = '<a target="_blank" href="' . add_query_arg(array('die_message' => 'iis')) . '" class="button">' . __('Windows Configuration (IIS)', self::slug) . '</a>';
            $html .= sprintf('<br><span class="description"> %s</span>', $desc);
        }
        return $html;

    }

    function hmwpl_single_config()
    {
        $slashed_home = trailingslashit(get_option('home'));
        $base = parse_url($slashed_home, PHP_URL_PATH);

        if (!$this->sub_folder && $base && $base != '/')
            $sub_install = trim($base, ' /') . '/';
        else
            $sub_install = '';

        $new_theme_path = trim($this->opt('new_theme_path'), '/ ');
        $new_plugin_path = trim($this->opt('new_plugin_path'), '/ ');
        $new_upload_path = trim($this->opt('new_upload_path'), '/ ');
        $new_include_path = trim($this->opt('new_include_path'), '/ ');        
        $new_content_path = trim($this->opt('new_content_path'), '/ ');

        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        else
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');

        $rel_login_path = $this->sub_folder . '/wp-login.php';
        $new_login_path = str_replace('.', '\.', trim($this->opt('new_login_path'), '/ '));

        if (is_multisite()) {
            if ($this->is_subdir_mu)
                $new_login_path = '/' . $new_login_path;
            $rel_login_path = $this->blog_path . str_replace($this->sub_folder, '', $new_login_path);
        }        

        $upload_path = wp_upload_dir();

        if (is_ssl())
            $upload_path['baseurl'] = str_replace('http:', 'https:', $upload_path['baseurl']);

        $rel_upload_path = $sub_install . trim(str_replace(site_url(), '', $upload_path['baseurl']), '/');

        $rel_plugin_path = $sub_install . trim(str_replace(site_url(), '', HMW_WP_PLUGIN_URL), '/');
        $rel_theme_path = $sub_install . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_comments_post = $sub_install . 'wp-comments-post.php';
        $rel_admin_ajax = $sub_install . 'wp-admin/admin-ajax.php';
        $rel_include_path2 = $sub_install . trim(WPINC); //To use in second part


        //Only use it if you want subfoler in first part
        $rel_include_path = $this->sub_folder . trim(WPINC);
        $rel_theme_path_with_subfolder = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');
        $rel_theme_path_no_template = str_replace('/' . get_stylesheet(), '', $rel_theme_path);


        $style_path_reg = '';        

        //|'.$rel_plugin_path.'/index\.php|'.$rel_theme_path_no_template.'/index\.php'
        $hide_other_file_rule = $this->sub_folder . 'readme\.html|' . $this->sub_folder . 'license\.txt|' . $rel_content_path . '/debug\.log' . $style_path_reg . '|' . $rel_include_path . '/$';

        $disable_directoy_listing = '(((' . $rel_content_path . '|' . $rel_include_path . ')/([A-Za-z0-9\-\_\/]*))|(wp-admin/(!network\/?)([A-Za-z0-9\-\_\/]+)))(\.txt|/)$';

        if ($this->opt('login_query') && $this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';
        

        $output = '';        

        $active_plugins = get_option('active_plugins');

        $pre_plugin_path = '';
        
        if (is_child_theme()) {
            //remove the end folder of so we can replace it with parent theme
            $path_array = explode('/', $new_theme_path);
            array_pop($path_array);
            $path_string = implode('/', $path_array);

            if ($path_string)
                $path_string = $path_string . '/';

            $parent_theme_new_path = $path_string . get_template();
            $rel_parent_theme_path = $sub_install . trim(str_replace(site_url(), '', get_template_directory_uri()), '/');
            $output .= 'RewriteRule ^' . $parent_theme_new_path . '/(.*) /' . $rel_parent_theme_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";
            $parent_theme_new_path_with_main = $new_theme_path . '_main';

            if ($sub_install)
                $output .= 'RewriteRule ^' . $parent_theme_new_path_with_main . '/style\.css' . ' /' . add_query_arg('parent_wrapper', '1', $sub_install) . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";
            else
                $output .= 'RewriteRule ^' . $parent_theme_new_path_with_main . '/style\.css' . ' /index.php?parent_wrapper=1' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";


            $output .= 'RewriteRule ^' . $parent_theme_new_path_with_main . '/(.*) /' . $rel_parent_theme_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";
        }

        if ($new_admin_path && $new_admin_path != 'wp-admin')
            $output .= 'RewriteRule ^' . $new_admin_path . '/(.*) /' . $sub_install . 'wp-admin/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_login_path && $new_login_path != 'wp-login.php')
            $output .= 'RewriteRule ^' . $new_login_path . ' /' . $sub_install . $rel_login_path . $this->trust_key . ' [QSA,L]' . "\n";


        if ($new_include_path)
            $output .= 'RewriteRule ^' . $new_include_path . '/(.*) /' . $rel_include_path2 . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_upload_path)
            $output .= 'RewriteRule ^' . $new_upload_path . '/(.*) /' . $rel_upload_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_plugin_path && $pre_plugin_path)
            $output .= $pre_plugin_path;

        if ($new_plugin_path)
            $output .= 'RewriteRule ^' . $new_plugin_path . '/(.*) /' . $rel_plugin_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";        


        if ($this->hmwpl_add_auto_internal('css'))
            if ($sub_install)
                $output .= 'RewriteRule ^_auto\.css' . ' /' . add_query_arg('style_internal_wrapper', '1', $sub_install) . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";
            else
                $output .= 'RewriteRule ^_auto\.css' . ' /index.php?style_internal_wrapper=1' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";

        if ($this->hmwpl_add_auto_internal('js'))
            if ($sub_install)
                $output .= 'RewriteRule ^_auto\.js' . ' /' . add_query_arg('script_internal_wrapper', '1', $sub_install) . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";
            else
                $output .= 'RewriteRule ^_auto\.js' . ' /index.php?script_internal_wrapper=1' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";

        //RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*) /wp39/index.php?get_wrapper=1&_case=$1&_addr=$2&AK_hide_my_wp=1234 [QSA,L]
        if ($this->hmwpl_add_get_wrapper())
            if ($sub_install)
                $output .= 'RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*)' . ' /' . add_query_arg('get_wrapper', '1', $sub_install) . '&_case=$1&_addr=$2' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";
            else
                $output .= 'RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*)' . ' /index.php?get_wrapper=1&_case=$1&_addr=$2' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";        


        if ($new_theme_path)
            $output .= 'RewriteRule ^' . $new_theme_path . '/(.*) /' . $rel_theme_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";
                

        if ($new_content_path)
            $output .= 'RewriteRule ^' . $new_content_path . '/(.*) /' . $rel_content_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";
        

        if ($this->opt('disable_directory_listing'))
            $output .= 'RewriteRule ^' . $disable_directoy_listing . ' /' . $sub_install . 'nothing_404_404' . $this->trust_key . ' [QSA,L]' . "\n";        

        if (!$output)
            $output = __('Nothing to add for current settings!', self::slug);
        else
            $output = "# BEGIN Hide My WP\n\n" . $output . "\n# END Hide My WP";

        $output = $this->hmwpl_cleanup_config($output);

        $html = '';
        $desc = __('In rare cases you need to configure it manually.<br>', self::slug);

        if (isset($_GET['single_config']) && $_GET['single_config']) {
            $html = sprintf(' %s ', $desc);
            $html .= sprintf('<span class="description">
        <ol style="color:#ff9900">
             <li> If you use <strong>BulletProof Security</strong> plugin first secure htaccess file using it  and then add below lines to your htaccess file using FTP. </li>
            <li> You may need to re-configure server whenever you change settings or activate a new theme or plugin. </li>
            <li>Add these lines right before: <strong>RewriteCond %{REQUEST_FILENAME} !-f</strong>. Next you may want to change htaccess permission to read-only (e.g. 666)</li>
        </ol></span><textarea readonly="readonly" onclick="" rows="5" cols="55" class="regular-text %1$s" id="%2$s" name="%2$s" style="%4$s">%3$s</textarea>', 'single_config_class', 'single_config', esc_textarea($output), 'width:95% !important;height:400px !important');


        } else {
            $html = '<a target="_blank" href="' . add_query_arg(array('die_message' => 'single')) . '" class="button">' . __('Manual Configuration', self::slug) . '</a>';
            $html .= sprintf('<br><span class="description"> %s</span>', $desc);
        }
        return $html;
        //rewrite ^/assets/css/(.*)$ /wp-content/themes/roots/assets/css/$1 last;


    }


    function hmwpl_multisite_config()
    {
        $slashed_home = trailingslashit(get_option('home'));
        $base = parse_url($slashed_home, PHP_URL_PATH);

        $new_theme_path = trim($this->opt('new_theme_path'), '/ ');
        $new_plugin_path = trim($this->opt('new_plugin_path'), '/ ');
        $new_upload_path = trim($this->opt('new_upload_path'), '/ ');
        $new_include_path = trim($this->opt('new_include_path'), '/ ');        
        $new_content_path = trim($this->opt('new_content_path'), '/ ');

        if (trim(get_option('hmwp_temp_admin_path'), ' /'))
            $new_admin_path = trim(get_option('hmwp_temp_admin_path'), ' /');
        else
            $new_admin_path = trim($this->opt('new_admin_path'), '/ ');

        $rel_login_path = $this->sub_folder . '/wp-login.php';
        $new_login_path = str_replace('.', '\.', trim($this->opt('new_login_path'), '/ '));

        if (is_multisite()) {
            if ($this->is_subdir_mu)
                $new_login_path = '/' . $new_login_path;
            $rel_login_path = $this->blog_path . str_replace($this->sub_folder, '', $new_login_path);
        }        

        $upload_path = wp_upload_dir();

        if (is_ssl())
            $upload_path['baseurl'] = str_replace('http:', 'https:', $upload_path['baseurl']);

        $rel_upload_path = $this->sub_folder . trim(str_replace(site_url(), '', $upload_path['baseurl']), '/');
        $rel_include_path = $this->sub_folder . trim(WPINC);
        $rel_plugin_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_PLUGIN_URL), '/');
        $rel_theme_path = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_comments_post = $this->sub_folder . 'wp-comments-post.php';
        $rel_admin_ajax = $this->sub_folder . 'wp-admin/admin-ajax.php';


        $rel_content_path = $this->sub_folder . trim(str_replace(site_url(), '', HMW_WP_CONTENT_URL), '/');
        $rel_theme_path_no_template = str_replace('/' . get_stylesheet(), '', $rel_theme_path);


        $style_path_reg = '';        

        if (!$this->sub_folder && $base && $base != '/')
            $sub_install = trim($base, ' /') . '/';
        else
            $sub_install = '';


        if ($this->is_subdir_mu)
            $hide_other_file_rule = 'readme\.html|' . 'license\.txt|' . str_replace($this->sub_folder, '', $rel_content_path) . '/debug\.log' . str_replace($this->sub_folder, '', $style_path_reg) . '|' . str_replace($this->sub_folder, '', $rel_include_path) . '/$';
        else
            $hide_other_file_rule = $this->sub_folder . 'readme\.html|' . $this->sub_folder . 'license\.txt|' . $rel_content_path . '/debug\.log' . $style_path_reg . '|' . $rel_include_path . '/$';

        $disable_directoy_listing = '(((' . $rel_content_path . '|' . $rel_include_path . ')/([A-Za-z0-9\-\_\/]*))|(wp-admin/(!network\/?)([A-Za-z0-9\-\_\/]+)))(\.txt|/)$';

        if ($this->opt('login_query') && $this->opt('login_query'))
            $login_query = $this->opt('login_query');
        else
            $login_query = 'hide_my_wp';

        $output = '';               

        if (is_multisite()) {
            $sitewide_plugins = array_keys((array)get_site_option('active_sitewide_plugins', array()));
            $active_plugins = array_merge((array)get_blog_option(BLOG_ID_CURRENT_SITE, 'active_plugins'), $sitewide_plugins);
        } else {
            $active_plugins = get_option('active_plugins');
        }

       
        $pre_plugin_path = '';
        
        if ($new_admin_path && $new_admin_path != 'wp-admin')
            $output .= 'RewriteRule ^' . $new_admin_path . '/(.*) /' . $this->sub_folder . 'wp-admin/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_login_path && $new_login_path != 'wp-login.php')
            $output .= 'RewriteRule ^' . $new_login_path . ' /' . $this->sub_folder . $rel_login_path . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_include_path)
            $output .= 'RewriteRule ^' . $new_include_path . '/(.*) /' . $rel_include_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_upload_path)
            $output .= 'RewriteRule ^' . $new_upload_path . '/(.*) /' . $rel_upload_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";

        if ($new_plugin_path && $pre_plugin_path)
            $output .= $pre_plugin_path;

        if ($new_plugin_path)
            $output .= 'RewriteRule ^' . $new_plugin_path . '/(.*) /' . $rel_plugin_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";        


        if ($this->hmwpl_add_auto_internal('css'))
            $output .= 'RewriteRule ^_auto\.css' . ' /' . $this->sub_folder . 'index.php?style_internal_wrapper=true' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";

        if ($this->hmwpl_add_auto_internal('js'))
            $output .= 'RewriteRule ^_auto\.js' . ' /' . $this->sub_folder . 'index.php?script_internal_wrapper=true' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";

        if ($this->hmwpl_add_get_wrapper())
            $output .= 'RewriteRule ^_get/([A-Za-z0-9-_\.]+)/(.*)' . ' /' . $this->sub_folder . 'index.php?get_wrapper=true&_case=$1&_addr=$2' . str_replace('?', '&', $this->trust_key) . ' [QSA,L]' . "\n";        

        if ($new_theme_path)
            $output .= 'RewriteRule ^' . $new_theme_path . '/(.*) /' . str_replace('/' . get_stylesheet(), '', $rel_theme_path) . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";        


        if ($new_content_path)
            $output .= 'RewriteRule ^' . $new_content_path . '/(.*) /' . $rel_content_path . '/$1' . $this->trust_key . ' [QSA,L]' . "\n";
        

        if ($this->opt('disable_directory_listing'))
            if ($this->is_subdir_mu)
                $output .= 'RewriteRule ^' . $disable_directoy_listing . ' /' . $this->sub_folder . 'nothing_404_404' . $this->trust_key . ' [QSA,L]' . "\n";
            else
                $output .= 'RewriteRule ^' . $disable_directoy_listing . ' /nothing_404_404' . $this->trust_key . ' [QSA,L]' . "\n";
        

        if (!$output)
            $output = __('Nothing to add for current settings!', self::slug);
        else
            $output = "# BEGIN Hide My WP\n\n" . $output . "\n# END Hide My WP";

        $output = $this->hmwpl_cleanup_config($output);

        $html = '';
        $desc = __('Add following lines to your .htaccess file to get all features of the plugin.<br>', self::slug);
        if (isset($_GET['multisite_config']) && $_GET['multisite_config']) {

            $html = sprintf('%s ', $desc);
            $html .= sprintf('<span class="description">
            <ol style="color:#ff9900">
            <li>Add below lines right before <strong>RewriteCond %{REQUEST_FILENAME} !-f [OR]</strong> </li>
            <li>You may need to re-configure the server whenever you change settings or activate a new plugin.</li> </ol></span>.
        <textarea readonly="readonly" onclick="" rows="5" cols="55" class="regular-text %1$s" id="%2$s" name="%2$s" style="%4$s">%3$s</textarea>', 'multisite_config_class', 'multisite_config', esc_textarea($output), 'width:95% !important;height:400px !important');


        } else {
            $html = '<a target="_blank" href="' . add_query_arg(array('die_message' => 'multisite')) . '" class="button">' . __('Multi-site Configuration', self::slug) . '</a>';
            $html .= sprintf('<br><span class="description"> %s</span>', $desc);
        }
        return $html;
        //rewrite ^/assets/css/(.*)$ /wp-content/themes/roots/assets/css/$1 last;
    }


    /**
     * Register settings page
     *
     */
    /**
     * HideMyWP::hmwpl_register_settings()
     *
     * @return
     */
    function hmwpl_register_settings()
    {
        require_once('admin-settings.php');
    }

    function hmwpl_load_this_plugin_first()
    {
        // ensure path to this file is via main wp plugin path
        $wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . "/$2", __FILE__);
        $this_plugin = plugin_basename(trim($wp_path_to_this_file));
        if (is_multisite()) {
            global $current_blog;
            $active_plugins = array_keys(get_site_option('active_sitewide_plugins', array()));
            $codes = array_values(get_site_option('active_sitewide_plugins', array()));
        } else {
            $active_plugins = get_option('active_plugins', array());
        }

        $this_plugin_key = array_search($this_plugin, $active_plugins);

        if (in_array($this_plugin, $active_plugins) && $active_plugins[0] != $this_plugin) {
            array_splice($active_plugins, $this_plugin_key, 1);
            array_unshift($active_plugins, $this_plugin);
            if (is_multisite()) {
                $this_plugin_code = $codes[$this_plugin_key];
                array_splice($codes, $this_plugin_key, 1);
                array_unshift($codes, $this_plugin_code);

                update_site_option('active_sitewide_plugins', array_combine($active_plugins, $codes));
            } else {
                update_option('active_plugins', $active_plugins);
            }

        }

    }

    /* Got from W3TC */
    function hmwpl_is_html($content)
    { // is_html or json or xml

        if (strlen($content) > 1000) {
            $content = substr($content, 0, 1000);
        }

        $content = ltrim($content, "\x00\x09\x0A\x0D\x20\xBB\xBF\xEF");

        return stripos($content, '{[') !== false || stripos($content, '{"') !== false || stripos($content, '<?xml') !== false || stripos($content, '<html') !== false || stripos($content, '<!DOCTYPE') !== false;
    }

    function hmwpl_w3tc_minify_before($buffer)
    {
        return $this->none_replaced_buffer;
    }

    //only works for auto mode not manual
    function hmwpl_w3tc_minify_after($buffer)
    {
        return $this->hmwpl_global_html_filter($buffer);
    }
}

$HideMyWP = new HideMyWP();
?>