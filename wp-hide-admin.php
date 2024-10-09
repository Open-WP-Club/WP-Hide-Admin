<?php
/*
Plugin Name: WP Hide Admin
Description: Hides the WordPress admin area and provides custom login URL.
Version: 1.2
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

class WP_Hide_Admin
{
  private $options;

  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_plugin_page'));
    add_action('admin_init', array($this, 'page_init'));
    add_action('init', array($this, 'hide_admin'));
    add_action('wp_loaded', array($this, 'custom_login_url'));
    add_action('admin_post_export_ip_log', array($this, 'export_ip_log'));
    add_action('admin_post_clear_ip_log', array($this, 'clear_ip_log'));
  }

  public function add_plugin_page()
  {
    add_options_page(
      'WP Hide Admin Settings',
      'WP Hide Admin',
      'manage_options',
      'wp-hide-admin',
      array($this, 'create_admin_page')
    );
  }

  public function create_admin_page()
  {
    $this->options = get_option('wp_hide_admin_options');
?>
    <div class="wrap">
      <h1>WP Hide Admin Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('wp_hide_admin_option_group');
        do_settings_sections('wp-hide-admin-setting-admin');
        submit_button();
        ?>
      </form>
      <?php if (isset($this->options['enable_ip_log']) && $this->options['enable_ip_log']): ?>
        <h2>IP Log</h2>
        <?php $this->display_ip_log(); ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
          <input type="hidden" name="action" value="export_ip_log">
          <?php submit_button('Export IP Log', 'secondary'); ?>
        </form>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
          <input type="hidden" name="action" value="clear_ip_log">
          <?php submit_button('Clear IP Log', 'secondary'); ?>
        </form>
      <?php endif; ?>
    </div>
<?php
  }

  public function page_init()
  {
    register_setting(
      'wp_hide_admin_option_group',
      'wp_hide_admin_options',
      array($this, 'sanitize')
    );

    add_settings_section(
      'wp_hide_admin_setting_section',
      'Settings',
      array($this, 'print_section_info'),
      'wp-hide-admin-setting-admin'
    );

    add_settings_field(
      'login_url',
      'Custom Login URL',
      array($this, 'login_url_callback'),
      'wp-hide-admin-setting-admin',
      'wp_hide_admin_setting_section'
    );

    add_settings_field(
      'redirect_url',
      'Redirect URL for old wp-admin',
      array($this, 'redirect_url_callback'),
      'wp-hide-admin-setting-admin',
      'wp_hide_admin_setting_section'
    );

    add_settings_field(
      'enable_ip_log',
      'Enable IP Logging',
      array($this, 'enable_ip_log_callback'),
      'wp-hide-admin-setting-admin',
      'wp_hide_admin_setting_section'
    );
  }

  public function sanitize($input)
  {
    $new_input = array();
    if (isset($input['login_url']))
      $new_input['login_url'] = sanitize_text_field($input['login_url']);
    if (isset($input['redirect_url']))
      $new_input['redirect_url'] = sanitize_text_field($input['redirect_url']);
    $new_input['enable_ip_log'] = isset($input['enable_ip_log']) ? 1 : 0;
    return $new_input;
  }

  public function print_section_info()
  {
    print 'Enter your settings below:';
  }

  public function login_url_callback()
  {
    printf(
      '<input type="text" id="login_url" name="wp_hide_admin_options[login_url]" value="%s" />',
      isset($this->options['login_url']) ? esc_attr($this->options['login_url']) : ''
    );
  }

  public function redirect_url_callback()
  {
    printf(
      '<input type="text" id="redirect_url" name="wp_hide_admin_options[redirect_url]" value="%s" />',
      isset($this->options['redirect_url']) ? esc_attr($this->options['redirect_url']) : ''
    );
  }

  public function enable_ip_log_callback()
  {
    printf(
      '<input type="checkbox" id="enable_ip_log" name="wp_hide_admin_options[enable_ip_log]" value="1" %s />',
      (isset($this->options['enable_ip_log']) && $this->options['enable_ip_log']) ? 'checked' : ''
    );
  }

  public function hide_admin()
  {
    $this->options = get_option('wp_hide_admin_options');

    // Don't block access for logged-in administrators
    if (is_user_logged_in() && current_user_can('manage_options')) {
      return;
    }

    if (isset($this->options['login_url']) && !empty($this->options['login_url'])) {
      $this->block_wp_admin();
    }
  }

  public function block_wp_admin()
  {
    $current_url = $_SERVER['REQUEST_URI'];

    // Block access to wp-login.php and wp-admin for non-admins
    if ($GLOBALS['pagenow'] === 'wp-login.php' || strpos($current_url, '/wp-admin') === 0) {
      $redirect_url = home_url('/');
      if (isset($this->options['redirect_url']) && !empty($this->options['redirect_url'])) {
        $redirect_url = $this->options['redirect_url'];
      }
      if (isset($this->options['enable_ip_log']) && $this->options['enable_ip_log']) {
        $this->log_attempt();
      }
      wp_redirect($redirect_url);
      exit;
    }
  }

  public function custom_login_url()
  {
    $this->options = get_option('wp_hide_admin_options');
    if (isset($this->options['login_url']) && !empty($this->options['login_url'])) {
      $custom_login_url = home_url($this->options['login_url']);
      if ($_SERVER['REQUEST_URI'] == '/' . $this->options['login_url']) {
        require_once(ABSPATH . 'wp-login.php');
        exit;
      }
    }
  }

  private function log_attempt()
  {
    $ip_log = get_option('wp_hide_admin_ip_log', array());
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($ip_log[$ip])) {
      $ip_log[$ip]++;
    } else {
      $ip_log[$ip] = 1;
    }
    update_option('wp_hide_admin_ip_log', $ip_log);
  }

  public function display_ip_log()
  {
    $ip_log = get_option('wp_hide_admin_ip_log', array());
    if (!empty($ip_log)) {
      echo '<table class="wp-list-table widefat fixed striped">';
      echo '<thead><tr><th>IP Address</th><th>Attempts</th></tr></thead>';
      echo '<tbody>';
      foreach ($ip_log as $ip => $attempts) {
        echo "<tr><td>$ip</td><td>$attempts</td></tr>";
      }
      echo '</tbody></table>';
    } else {
      echo '<p>No attempts logged yet.</p>';
    }
  }

  public function export_ip_log()
  {
    $ip_log = get_option('wp_hide_admin_ip_log', array());
    $csv = "IP Address,Attempts\n";
    foreach ($ip_log as $ip => $attempts) {
      $csv .= "$ip,$attempts\n";
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wp_hide_admin_ip_log.csv"');
    echo $csv;
    exit;
  }

  public function clear_ip_log()
  {
    delete_option('wp_hide_admin_ip_log');
    wp_redirect(admin_url('options-general.php?page=wp-hide-admin'));
    exit;
  }
}

$wp_hide_admin = new WP_Hide_Admin();
