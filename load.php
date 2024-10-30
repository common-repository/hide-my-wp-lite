<?php

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, array(&$this, 'hmwpl_on_activate_callback'));
register_deactivation_hook(__FILE__, array(&$this, 'hmwpl_on_deactivate_callback'));

$can_deactive = false;
if (isset($_COOKIE['hmwp_can_deactivate']) && preg_replace("/[^a-zA-Z]/", "", substr(NONCE_SALT, 0, 8)) == preg_replace("/[^a-zA-Z]/", "", $_COOKIE['hmwp_can_deactivate']))
    $can_deactive = true;

//may also need to change mute-sceamer
$this->short_prefix = preg_replace("/[^a-zA-Z]/", "", substr(NONCE_SALT, 0, 6)) . '_';

//Fix a WP problem caused by filters order for deactivation
if (isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['plugin']) && $_GET['plugin'] == self::main_file && is_admin() && $can_deactive) {
    update_option(self::slug . '_undo', get_option(self::slug));
    delete_option(self::slug);
}

require_once dirname( __FILE__ ) . '/lib/class.helper.php';
$this->h = new PP_Helper(self::slug, self::ver);
$this->h->check_versions('5.0', '3.4');


//$this->opt('db_ver');

$sub_installation = trim(str_replace(home_url(), '', site_url()), ' /');

if ($sub_installation && substr($sub_installation, 0, 4) != 'http')
    $this->sub_folder = $sub_installation . '/';

$this->is_subdir_mu = false;
if (is_multisite())
    $this->is_subdir_mu = true;
if ((defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) || (defined('VHOST') && VHOST == 'yes'))
    $this->is_subdir_mu = false;

if (is_multisite() && !$this->sub_folder && $this->is_subdir_mu)
    $this->sub_folder = ltrim(parse_url(trim(get_blog_option(BLOG_ID_CURRENT_SITE, 'home'), '/') . '/', PHP_URL_PATH), '/');


if (is_multisite() && !$this->blog_path && $this->is_subdir_mu) {
    global $current_blog;
    $this->blog_path = str_replace($this->sub_folder, '', $current_blog->path); //has /
}

if (is_admin()) {
    require_once dirname( __FILE__ ) . '/lib/class.settings-api.php';
    add_action('init', array(&$this, 'hmwpl_register_settings'), 5);
}


if (is_multisite())
    $this->options = get_blog_option(BLOG_ID_CURRENT_SITE, self::slug);
else
    $this->options = get_option(self::slug);

if (is_admin() && $can_deactive)
    $this->hmwpl_load_this_plugin_first();

$block_ip = false;

if (defined('W3TC') && trim($this->opt('new_content_path'),' /') && trim($this->opt('new_content_path'), '/ ')!='wp-content')
    if ($this->h->str_contains($_SERVER['REQUEST_URI'], trim($this->opt('new_content_path'),' /'). '/cache/minify/'))
        $_SERVER['REQUEST_URI']= str_replace('inc', 'wp-content', $_SERVER['REQUEST_URI']);

add_filter('pp_settings_api_filter', array(&$this, 'hmwpl_pp_settings_api_filter'), 100, 1);
add_action('pp_settings_api_reset', array(&$this, 'hmwpl_pp_settings_api_reset'), 100, 1);
add_action('init', array(&$this, 'hmwpl_init'), 1);
add_action('wp', array(&$this, 'hmwpl_wp'));
add_action('generate_rewrite_rules', array(&$this, 'hmwpl_add_rewrite_rules'));
add_filter('the_content', array(&$this, 'hmwpl_post_filter'));
add_action('admin_notices', array(&$this, 'hmwpl_admin_notices'));
//Enable custom 404 page
add_filter('404_template', array(&$this, 'custom_404_page'), 10, 1);

add_filter('posts_request', array(&$this, 'hmwpl_disable_main_wp_query'), 110, 2 );
add_action('wp', array(&$this, 'hmwpl_global_assets_filter'));

if (isset($_GET['die_message']) && is_admin())
    add_action('admin_init', array(&$this, 'hmwpl_die_message'), 1000);

//compatibility with social login
if ($this->opt('disable_directory_listing')) {
    defined('WORDPRESS_SOCIAL_LOGIN_PLUGIN_URL')
    || define('WORDPRESS_SOCIAL_LOGIN_PLUGIN_URL', plugins_url() . '/wordpress-social-login/');
    defined('WORDPRESS_SOCIAL_LOGIN_HYBRIDAUTH_ENDPOINT_URL')
    || define('WORDPRESS_SOCIAL_LOGIN_HYBRIDAUTH_ENDPOINT_URL', WORDPRESS_SOCIAL_LOGIN_PLUGIN_URL . '/hybridauth/index.php');
}

if (is_multisite())
    add_action('network_admin_notices', array(&$this, 'hmwpl_admin_notices'));

if ($this->opt('login_query'))
    $login_query = $this->opt('login_query');
else
    $login_query = 'hide_my_wp';


if (!$can_deactive && $this->opt('hide_wp_admin') && $this->h->ends_with($_SERVER['PHP_SELF'], 'customize.php') && (!isset($_GET[$login_query]) || $_GET[$login_query] != $this->opt('admin_key')))
    $this->hmwpl_block_access();

if ($this->opt('replace_mode') == 'quick' && !is_admin() && !isset($_GET['die_message'])) {
//root
    add_filter('plugins_url', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('bloginfo', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('stylesheet_directory_uri', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('template_directory_uri', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('script_loader_src', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('style_loader_src', array(&$this, 'hmwpl_partial_filter'), 1000, 1);

    add_filter('stylesheet_uri', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('includes_url', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
    add_filter('bloginfo_url', array(&$this, 'hmwpl_partial_filter'), 1000, 1);

    if (!$this->hmwpl_is_permalink()) {
        add_filter('author_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('post_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('page_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('attachment_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('post_type_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('get_pagenum_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);

        add_filter('category_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('tag_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);

        add_filter('feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('category_feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('tag_feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('taxonomy_feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('author_feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);
        add_filter('the_feed_link', array(&$this, 'hmwpl_partial_filter'), 1000, 1);

    }
}


if ($this->opt('hide_wp_login')) {
    add_action('site_url', array(&$this, 'hmwpl_add_login_key_to_action_from'), 101, 4);
    remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
    add_filter('login_url', array(&$this, 'hmwpl_add_key_login_to_url'), 101, 2);
    add_filter('logout_url', array(&$this, 'hmwpl_add_key_login_to_url'), 101, 2);
    add_filter('lostpassword_url', array(&$this, 'hmwpl_add_key_login_to_url'), 101, 2);
    add_filter('register', array(&$this, 'hmwpl_add_key_login_to_url'), 101, 2);

//since 4.5
    add_filter('comment_moderation_text', array(&$this, 'hmwpl_add_key_login_to_messages'), 101, 2);
    add_filter('comment_notification_text', array(&$this, 'hmwpl_add_key_login_to_messages'), 101, 2);

    add_filter('wp_logout', array(&$this, 'hmwpl_correct_logout_redirect'), 101);

    add_filter('wp_redirect', array(&$this, 'hmwpl_add_key_login_to_url'), 101, 2);
}

add_action('after_setup_theme', array(&$this, 'hmwpl_ob_starter'), -100001);
//add_action('shutdown',  array(&$this, 'do_shutdown'), 110);

//Fix WP Fastest cache
// if  (defined('WPFC_WP_PLUGIN_DIR') && !is_admin())
//    add_action('after_setup_theme',array(&$this, 'ob_starter') , 100001);

// Fix wp-rocket_cache problem!
//if (WP_CACHE && defined('WP_ROCKET_VERSION'))
//  add_filter('rocket_buffer',  array(&$this, 'global_html_filter'), -10000);

// Fix hyper_cache problem!
if (WP_CACHE && function_exists('hyper_cache_sanitize_uri'))
    add_filter('cache_buffer', array(&$this, 'hmwpl_global_html_filter'), -100);



add_action('admin_enqueue_scripts', array($this, 'hmwpl_admin_css_js'));
// add_action( 'wp_enqueue_scripts', array( $this, 'css_js' ) );

if (function_exists('bp_is_current_component'))
    add_action('bp_uri', array($this, 'hmwpl_bp_uri'));





?>