<?php
/**
 * Plugin Name: ACF QR Code Generator
 * Description: Генерація та збереження QR-коду як ACF поле.
 * Version: 2.0
 * Author: Yuriy Kozmin aka Yuriy Knysh
 * Text Domain: acf-qr-code-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

// Підключення скриптів та стилів для адмінки
add_action('admin_enqueue_scripts', 'acf_qr_code_generator_admin_scripts');
function acf_qr_code_generator_admin_scripts() {
    wp_enqueue_script('acf-qr-code-script', plugin_dir_url(__FILE__) . 'js/acf-qr-code.js', array('jquery'), null, true);
    wp_localize_script('acf-qr-code-script', 'acf_qr_code_ajax', array('ajaxurl' => admin_url('admin-ajax.php')));
}

// Реєстрація нового поля ACF типу "QR-код"
add_action('acf/include_field_types', 'register_acf_qr_code_field');
function register_acf_qr_code_field() {
    include_once plugin_dir_path(__FILE__) . 'fields/class-acf-field-qr-code.php';
}

// Обробка AJAX для генерації та видалення QR-коду
add_action('wp_ajax_generate_qr_code', 'acf_generate_qr_code');
add_action('wp_ajax_delete_qr_code', 'acf_delete_qr_code');

function acf_generate_qr_code() {
    $post_id = intval($_POST['post_id']);
    $qr_url = esc_url($_POST['qr_url']);

    if (!$post_id || !$qr_url) {
        wp_send_json_error(array('message' => 'Неправильний ID поста або URL.'));
    }

    // Генерація QR-коду через сторонній API
    $api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_url);
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Помилка при отриманні QR-коду через API.'));
    }

    // Отримуємо тіло відповіді (зображення QR-коду)
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        wp_send_json_error(array('message' => 'Порожня відповідь від API.'));
    }

    // Зберігаємо зображення QR-коду у тимчасовий файл
    $upload_dir = wp_upload_dir();
    $filename = 'qr-code-' . $post_id . '.png';
    $filepath = $upload_dir['path'] . '/' . $filename;

    // Записуємо зображення QR-коду в файл
    $result = file_put_contents($filepath, $body);
    if (!$result) {
        wp_send_json_error(array('message' => 'Не вдалося зберегти QR-код.'));
    }

    // Створюємо медіа-об'єкт для збереження зображення в медіабібліотеці
    $filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    // Вставляємо файл в медіабібліотеку
    $attach_id = wp_insert_attachment($attachment, $filepath);

    // Генеруємо метадані для зображення
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Зберігаємо ID прикріпленого зображення як мета-дані
    update_post_meta($post_id, '_worker_qr_image_id', $attach_id);

    // Повертаємо URL зображення
    $image_url = wp_get_attachment_url($attach_id);
    if (!$image_url) {
        wp_send_json_error(array('message' => 'Помилка при генерації QR-коду.'));
    }

    wp_send_json_success(array('image_url' => $image_url));
}


function acf_delete_qr_code() {
    $post_id = intval($_POST['post_id']);

    if (!$post_id) {
        wp_send_json_error(array('message' => 'Неправильний ID поста.'));
    }

    // Видалення мета-даних
    delete_post_meta($post_id, '_worker_qr_image_id');
    
    wp_send_json_success();
}
