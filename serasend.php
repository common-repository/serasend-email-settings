<?php
/*
Plugin Name: Serasend Email Settings
Plugin URI: https://serasend.com
Description: Completely takes control of your server's email handling and simplifies it. Mail is no longer your problem.
Version: 1.0
Author: Seraflux
License: AGPL
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Author URI: https://seraflux.com
Text Domain: serasend
*/
if(!defined('ABSPATH')) {
	exit;
}

function serasend_admin_page_settings_init() {
	$items = [
		'serasend_user_login' => [
			'label' => 'Username'
        ],
		'serasend_user_password' => [
			'label' => 'License Key'
		]
	];
	
    add_settings_section(
        'serasend_settings_section',
        'Mail Settings',
        '',
        'serasend-settings'
    );
	
	foreach($items as $key=>$item) {
		add_settings_field(
			$key,
			$item['label'],
			$key.'_field',
			'serasend-settings',
			'serasend_settings_section'
		);
		register_setting(
			'serasend_settings_group',
			$key
		);
		
		
	}
}
add_action('admin_init', 'serasend_admin_page_settings_init');

function serasend_menu_item() {
    add_options_page(
        'Serasend Mail',
        'Serasend Mail',
        'manage_options',
        'serasend-settings',
        'serasend_settings_page',
    );
}
add_action('admin_menu', 'serasend_menu_item');

function serasend_login($username, $password) {
    $url = "https://api.serasend.com/api/user/login/license";
    $data = array(
        'email' => $username,
        'license' => $password
    );
    $json_data = wp_json_encode($data);
    $headers = array(
        'Content-Type' => 'application/json',
        'Content-Length' => strlen($json_data)
    );
    $args = array(
        'body' => $json_data,
        'headers' => $headers,
        'method' => 'POST',
        'timeout' => 15 // Adjust timeout as needed
    );
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return false;
    } else {
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body, true);
        if($body && $body['success']) {
            return true;
        }
        echo '<div class="error"><p style="font-weight:bold;">Invalid credentials specified.<br>Possible Cause:<br> - You made an error in your username.<br>- Your license key is invalid or already in use by another WordPress installation</p></div>';
        return false;
    }
    return false;
}

// Callback function to output settings page
function serasend_settings_page() {
    ?>
    <div class="wrap">
        <h1>Serasend</h1>
        <?php
        $is_valid = false;
        $username = get_option('serasend_user_login');
        $password = get_option('serasend_user_password');
        $last_login_check_key = 'serasend_last_login_check';
        $last_login_check = null;
        if($username && $password) {
            $last_login_check = get_option($last_login_check_key);
            update_option($last_login_check_key, time());
            if(!$last_login_check || time() > $last_login_check+600) { // If older than 10 minutes, login again
                update_option('serasend_last_login_check_status', false);
                if(serasend_login($username, $password)) {
                    $is_valid = true;
                    update_option($last_login_check_key, time());
                    update_option('serasend_last_login_check_status', true);
                } else {
                    update_option($last_login_check_key, 0);
                }
            } else {
                $is_valid = get_option('serasend_last_login_check_status');
            }
        }
        ?>
        <form method="post" action="options.php">
            <?php
                wp_nonce_field('serasend_mail_settings_nonce', 'serasend_mail_settings_nonce');
                settings_fields('serasend_settings_group');
                do_settings_sections('serasend-settings');
            ?>
            <?php submit_button(); ?>
            <h3>
                Status:
                <span class="text-success">
                    <?php
                        echo
                            $is_valid
                            ?
                                esc_html('Connected', 'serasend')
                            :
                                esc_html('Disconnected', 'serasend');
                    ?>
                </span>
                <br>
                <span class="text-sm">
                    Last Checked:
                    <?php 
                        if(!$last_login_check) {
                            echo esc_html('A few seconds ago');
                        } else {
                            // WordPress forced us to:
                            // - Escape the dynamic string
                            // - use GMT instead of Local Date
                            echo esc_html(
                                (string) gmdate("Y-m-d H:i", $last_login_check)
                            );
                            echo '&nbsp;';
                            echo esc_html('UTC');
                        }
                    ?>
                </span>
            </h3>
            <a target="_blank" href="https://app.serasend.com">Login to your Serasend Dashboard</a>
        </form>
    </div>
    <?php
}

function serasend_user_password_field() {
    $password = get_option('serasend_user_password');
    ?>
    <input type="text" name="serasend_user_password" value="<?php echo esc_attr($password); ?>" />
    <?php
}
function serasend_user_login_field() {
    $username = get_option('serasend_user_login');
    ?>
    <input type="text" name="serasend_user_login" value="<?php echo esc_attr($username); ?>" />
    <?php
}

add_action('admin_init', 'serasend_save_settings');
function serasend_save_settings() { // Reset the check when the details are updated
    if (
		isset($_POST['option_page'])
		&& $_POST['option_page'] == 'serasend_settings_group'
		&& wp_verify_nonce(
				sanitize_text_field(
					wp_unslash($_POST['serasend_mail_settings_nonce'])
				),
				'serasend_mail_settings_nonce'
			)
		) {
        if (isset($_POST['serasend_user_login']) || isset($_POST['serasend_user_password'])) {
            update_option('serasend_last_login_check', 0);
        }
    }
}

function serasend_phpmailer_setup($phpmailer) {
    $username = get_option('serasend_user_login');
    $password = get_option('serasend_user_password');
    
	$phpmailer->isSMTP();
	$phpmailer->Host = 'smtp.serasend.com';
	$phpmailer->SMTPAuth = true;
	$phpmailer->Username = $username;
	$phpmailer->Password = $password;
	$phpmailer->SMTPSecure = 'tls';
	$phpmailer->Port = 587;
}
add_filter('phpmailer_init', 'serasend_phpmailer_setup', PHP_INT_MIN, 1);