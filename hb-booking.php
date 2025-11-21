<?php
/**
 * Plugin Name: HB Booking
 * Plugin URI: https://hadesboard.com/hb-booking
 * Description: A modern booking system with calendar integration for WordPress
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Mohamad Gandomi
 * Author URI: https://hadesboard.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hb-booking
 * Domain Path: /languages
 */

namespace HB\Booking;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HB_BOOKING_VERSION', '1.0.1');
define('HB_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HB_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HB_BOOKING_PLUGIN_FILE', __FILE__);

// Require Composer autoloader
require_once HB_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class
 * Handles plugin initialization and orchestrates all components
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private function __construct()
    {
        $this->initHooks();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        register_activation_hook(HB_BOOKING_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(HB_BOOKING_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'loadTextDomain']);
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        $installer = new Core\Installer();
        $installer->install();

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        // Initialize core components
        Core\Database::getInstance();
        Core\Assets::getInstance();

        // Initialize admin area
        if (is_admin()) {
            Admin\BookingAdmin::getInstance();
        }

        // Initialize frontend
        Frontend\BookingForm::getInstance();
        Frontend\CustomerCalendar::getInstance();

        // Initialize REST API
        Api\BookingApi::getInstance();

        // Initialize services
        Services\EmailService::getInstance();
        Services\CalendarService::getInstance();
    }

    /**
     * Load plugin text domain
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'hb-booking',
            false,
            dirname(plugin_basename(HB_BOOKING_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

// Initialize plugin
Plugin::getInstance();
