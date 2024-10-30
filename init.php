<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wp_rewrite, $wp, $wp_roles, $wp_query, $current_user, $wp_version;
load_plugin_textdomain(self::slug, FALSE, self::dir . '/lang/');


//todo:RewriteRule ^([_0-9a-zA-Z-]+/)?panel/(.*) $1wp-admin/$2 [QSA,L] you also need to change wp-includes/ms-default-contsant line 69



/*
if (!is_admin()) {
echo 'ffff blog_path: '.  $this->blog_path .' sub_folder:'. $this->sub_folder ;
echo "\n".'testcontent:'. get_option('test');
} */

if ($this->opt('login_query'))
    $login_query = $this->opt('login_query');
else
    $login_query = 'hide_my_wp';

if ($this->opt('admin_key'))
    $this->trust_key = '?' . $this->short_prefix . $login_query . '=' . $this->opt('admin_key');
else
    $this->trust_key = '';

$is_trusted = false;
$is_scanmywp = false;

if (current_user_can(self::slug . '_trusted') || (isset($_GET[$login_query]) && $_GET[$login_query] == $this->opt('admin_key')))
    $is_trusted = true;

$new_admin_path = (trim($this->opt('new_admin_path'), ' /')) ? trim($this->opt('new_admin_path'), ' /') : 'wp-admin';

if (trim($this->opt('new_admin_path'), ' /') && trim($this->opt('new_admin_path'), ' /') != 'wp-admin') {
    $_SERVER['REQUEST_URI'] = $this->hmwpl_replace_admin_url($_SERVER['REQUEST_URI']);
    add_filter('admin_url', array(&$this, 'hmwpl_replace_admin_url'), 100, 3);
    add_filter('network_admin_url', array(&$this, 'hmwpl_replace_admin_url'), 100, 2);
}

if (current_user_can('activate_plugins'))
    setcookie("hmwp_can_deactivate", preg_replace("/[^a-zA-Z]/", "", substr(NONCE_SALT, 0, 8)), time() + 3600, null, null, null, true);
elseif (isset($_COOKIE['hmwp_can_deactivate']))
    setcookie("hmwp_can_deactivate","", time() - 3600, null, null, null, true);

if (defined( 'W3TC' )) {
    if (class_exists('W3TC\Dispatcher')) {
        $config = W3TC\Dispatcher::config();
        if ($config->get_boolean('minify.enabled')){
            if ($config->get_boolean('minify.auto')) {

                add_filter('w3tc_minify_before', array(&$this, 'hmwpl_w3tc_minify_before'), 1000, 1);
                add_filter('w3tc_minify_processed', array(&$this, 'hmwpl_w3tc_minify_after'), 1000, 1);
            }

            if (!$config->get_boolean( 'minify.rewrite' )) {
                if (isset($_REQUEST['w_minify'])) {
                    $_REQUEST['w3tc_minify'] = $_REQUEST['w_minify'];
                    unset($_REQUEST['w_minify']);
                }
                $this->replace_old[] = '?w3tc_minify=';
                $this->replace_new[] = '?w_minify=';
            }
        }
    }
}


add_action( 'wp_enqueue_scripts', array(&$this, 'hmwpl_styles_scripts') , 0);

//Remove W3 Total Cache Comments for untrusteds
if (defined('W3TC'))
    if (!$is_trusted)
        add_filter('w3tc_can_print_comment', function() { return false; });

//Not work in multisite at all!
if (basename($_SERVER['PHP_SELF']) == 'options-permalink.php' && isset($_POST['permalink_structure'])) {    
    update_option(self::slug, $this->options);

}

if (!isset($_POST['wp_customize'])){
    $wp->add_query_var('style_internal_wrapper');
    $wp->add_query_var('script_internal_wrapper');    
}

if (is_multisite()){
    $recent_message_last= get_blog_option(SITE_ID_CURRENT_SITE,'pp_important_messages_last');
}else{
    $recent_message_last= get_option('pp_important_messages_last');
}




//echo '<pre>';
//print_r($wp_rewrite);
//echo '</pre>';

//These 3 should be after page base so get_permalink in block access should work correctly


if ($this->opt('hide_wp_admin') && !$is_trusted) {

    if ($this->h->str_contains(($_SERVER['PHP_SELF']), '/wp-admin/') || is_admin() && trim($this->opt('new_admin_path'), ' /') != 'wp-admin' && !$this->h->str_contains($_SERVER['REQUEST_URI'], $this->opt('new_admin_path'))) {
        if (!$this->h->ends_with($_SERVER['PHP_SELF'], '/admin-ajax.php') && !$this->h->ends_with($_SERVER['PHP_SELF'], '/tevolution-ajax.php') ) {
            $this->hmwpl_block_access();
        }
    }
}

if ($this->opt('hide_wp_login') && !$is_trusted) {
    if ($this->h->ends_with($_SERVER['PHP_SELF'], '/wp-login.php') || $this->h->ends_with($_SERVER['PHP_SELF'], '/wp-login.php/') || $this->h->ends_with($_SERVER['PHP_SELF'], '/wp-signup.php')) {

        if (!trim($this->opt('new_login_path'), '/ ') || !$this->h->str_contains($_SERVER['REQUEST_URI'], '/'.$this->opt('new_login_path')))
            $this->hmwpl_block_access();
    }
}

if (defined('WP_CACHE') && !$is_trusted){
    global $wp_super_cache_comments;
    $wp_super_cache_comments = 0;
}

//We only need replaces in this line. htaccess related works don't work here. They need flush and generate_rewrite_rules filter
//do not hide anything for scan my wp server. 

if(!$is_scanmywp) {
    $this->hmwpl_add_rewrite_rules($wp_rewrite);
}


//New style path code
if ($this->opt('new_style_name') && $this->opt('new_style_name') != 'style.css' && $this->hmwpl_is_permalink() && !isset($_POST['wp_customize'])) {

    $rel_style_path = $this->sub_folder . trim(str_replace(site_url(), '', get_stylesheet_directory_uri() . '/style.css'), '/');
    
    //style should be in theme directory.
    $new_style_path = trim($this->opt('new_theme_path'), ' /') . '/' . trim($this->opt('new_style_name'), '/ ');
    $new_style_path = str_replace('.', '\.', $new_style_path);    

    if (is_multisite()) {

        $new_style_path = '/' . trim($this->opt('new_theme_path'), '/ ') . '/' . get_stylesheet() . '/' . trim($this->opt('new_style_name'), '/ ');

        $rel_theme_path_with_theme = trim(str_replace(site_url(), '', get_stylesheet_directory_uri()), '/');
        $rel_style_path = $this->blog_path . $rel_theme_path_with_theme . '/style.css'; //without theme

        $wp->add_query_var('template_wrapper');

        //Fix a little issue with Multisite partial order
        $this->partial_replace_old[] = '/' . get_stylesheet() . '/style.css';
        $this->partial_replace_new[] = '/' . get_stylesheet() . '/' . str_replace('\.', '.', trim($this->opt('new_style_name'), '/ '));
    } else {
        $this->partial_replace_old[] = '/' . trim($this->opt('new_theme_path'), ' /') . '/style.css';
        $this->partial_replace_new[] = '/' . str_replace('\.', '.', $new_style_path);        
    }

    $wp->add_query_var('style_wrapper');

    if (is_child_theme())
        $wp->add_query_var('parent_wrapper');

    //This line doesn't work in multisite
    $wp_rewrite->add_rule($new_style_path, 'index.php?style_wrapper=true' . str_replace('?', '&', $this->trust_key), 'top');

   // $wp_rewrite->add_rule(trim($this->opt('new_theme_path'), ' /') . '/' . trim('inline\.css', '/ '), 'index.php?style_internal_wrapper=true' . str_replace('?', '&', $this->trust_key), 'top');
    $this->partial_replace_old[] = $rel_style_path;
    $this->partial_replace_new[] = str_replace('\.', '.', $new_style_path);
    
}

?>