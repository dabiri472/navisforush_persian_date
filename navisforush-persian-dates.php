<?php
/**
 * Plugin Name: NavisForush Persian Dates
 * Plugin URI: https://github.com/dabiri472/NavisForush
 * Description: افزودن تاریخ شمسی به REST API برای اپلیکیشن نویس‌فروش
 * Version: 1.0.0
 * Author: ALI
 * Author URI: https://github.com/dabiri472
 * Text Domain: navisforush
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // خروج مستقیم امن
}

/**
 * ثبت فیلدهای شمسی در REST API
 */
add_action('rest_api_init', function() {
    // برای پست‌ها
    register_rest_field('post', 'navisforush_persian_date', [
        'get_callback' => 'navisforush_get_persian_date',
        'schema' => [
            'type' => 'string',
            'description' => 'تاریخ شمسی (جلالی) پست',
            'readonly' => true,
            'context' => ['view', 'edit']
        ]
    ]);
    
    // برای کامنت‌ها
    register_rest_field('comment', 'navisforush_persian_date', [
        'get_callback' => 'navisforush_get_persian_date',
        'schema' => [
            'type' => 'string',
            'description' => 'تاریخ شمسی (جلالی) کامنت',
            'readonly' => true,
            'context' => ['view', 'edit']
        ]
    ]);
    
    // برای رسانه‌ها
    register_rest_field('attachment', 'navisforush_persian_date', [
        'get_callback' => 'navisforush_get_persian_date',
        'schema' => [
            'type' => 'string',
            'description' => 'تاریخ شمسی (جلالی) آپلود فایل',
            'readonly' => true,
            'context' => ['view', 'edit']
        ]
    ]);
    
    // برای کاربران
    register_rest_field('user', 'navisforush_persian_registered', [
        'get_callback' => function($object) {
            $date = $object['registered_date'] ?? '';
            if (!$date) return '';
            
            $timestamp = strtotime($date);
            if ($timestamp === false) return '';
            
            return navisforush_convert_to_persian($timestamp);
        },
        'schema' => [
            'type' => 'string',
            'description' => 'تاریخ شمسی ثبت‌نام کاربر',
            'readonly' => true,
            'context' => ['view', 'edit']
        ]
    ]);
});

/**
 * تابع اصلی دریافت تاریخ شمسی
 */
function navisforush_get_persian_date($object) {
    // دریافت تاریخ میلادی
    $date = '';
    if (is_array($object)) {
        $date = $object['date'] ?? $object['date_gmt'] ?? '';
    } elseif (is_object($object)) {
        $date = $object->date ?? $object->date_gmt ?? '';
    }
    
    if (empty($date)) {
        return '';
    }
    
    // تبدیل به timestamp
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    return navisforush_convert_to_persian($timestamp);
}

/**
 * تبدیل timestamp به تاریخ شمسی
 * فرمت: 1404/08/18 08:30
 */
function navisforush_convert_to_persian($timestamp) {
    // استفاده از wp_date برای پشتیبانی از منطقه زمانی
    try {
        $timezone = new DateTimeZone(get_option('timezone_string') ?: 'Asia/Tehran');
        
        // دریافت اجزای تاریخ میلادی
        $datetime = new DateTime('@' . $timestamp);
        $datetime->setTimezone($timezone);
        
        $gy = (int) $datetime->format('Y');
        $gm = (int) $datetime->format('m');
        $gd = (int) $datetime->format('d');
        $time = $datetime->format('H:i');
        
        // تبدیل به شمسی
        list($jy, $jm, $jd) = navisforush_gregorian_to_jalali($gy, $gm, $gd);
        
        // فرمت نهایی: 1404/08/18 08:30
        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
        
    } catch (Exception $e) {
        error_log('NavisForush Persian Date Error: ' . $e->getMessage());
        return '';
    }
}

/**
 * الگوریتم تبدیل میلادی به شمسی (جلالی)
 * منبع: کد استاندارد تقویم شمسی
 */
function navisforush_gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_n = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_n[$gm - 1];
    
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    
    return [$jy, $jm, $jd];
}

/**
 * افزودن منوی تنظیمات
 */
add_action('admin_menu', function() {
    add_options_page(
        'تنظیمات نَویس‌فروش',
        'نَویس‌فروش',
        'manage_options',
        'navisforush-settings',
        'navisforush_settings_page'
    );
});

/**
 * صفحه تنظیمات افزونه
 */
function navisforush_settings_page() {
    ?>
    <div class="wrap">
        <h1>⚙️ تنظیمات نَویس‌فروش</h1>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>✅ افزونه فعال است!</h2>
            <p>
                تاریخ شمسی به REST API اضافه شد. اکنون اپلیکیشن «نَویس‌فروش» می‌تواند تاریخ‌های شمسی را نمایش دهد.
            </p>
            
            <h3>🔍 تست کردن:</h3>
            <p>برای تست کردن، URL زیر را در مرورگر باز کنید:</p>
            <code style="display: block; padding: 10px; background: #f5f5f5; direction: ltr; text-align: left;">
                <?php echo esc_url(rest_url('wp/v2/posts?per_page=1')); ?>
            </code>
            
            <p>در خروجی JSON باید فیلد <code>navisforush_persian_date</code> را ببینید.</p>
            
            <h3>📱 اطلاعات اپلیکیشن:</h3>
            <ul>
                <li><strong>نام:</strong> نَویس‌فروش (NavisForush)</li>
                <li><strong>نسخه افزونه:</strong> 1.0.0</li>
                <li><strong>توسعه‌دهنده:</strong> ALI</li>
                <li><strong>GitHub:</strong> <a href="https://github.com/dabiri472/WordPressFresh" target="_blank">WordPressFresh</a></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * پیام فعال‌سازی
 */
register_activation_hook(__FILE__, function() {
    add_option('navisforush_activated', true);
});

add_action('admin_notices', function() {
    if (get_option('navisforush_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ افزونه نَویس‌فروش فعال شد!</strong></p>
            <p>تاریخ شمسی به REST API اضافه شد. برای تنظیمات به <a href="<?php echo admin_url('options-general.php?page=navisforush-settings'); ?>">این صفحه</a> بروید.</p>
        </div>
        <?php
        delete_option('navisforush_activated');
    }
});
