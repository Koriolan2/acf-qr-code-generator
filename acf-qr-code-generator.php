<?php
/**
 * Plugin Name: ACF QR Code Generator
 * Description: Генерація та збереження QR-коду як ACF поле.
 * Version: 2.2
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

    // Налаштування змінних для розміру, кольору та формату QR-коду
    $qr_size = '350х350';  // Розмір QR-коду
    $qr_color = '044e98';  // Колір QR-коду (чорний)
    $qr_format = 'svg';    // Формат файлу (SVG)

    // Отримуємо останній сегмент URL (слуг) для імені файлу та підпису
    $parsed_url = wp_parse_url($qr_url);
    $path_segments = explode('/', trim($parsed_url['path'], '/'));
    $slug = end($path_segments);  // Остання частина URL

    // Розбиваємо слуг на частини для формування ПІБ лікаря
    $name_parts = explode('-', $slug);
    $doctor_name = implode(' ', $name_parts);  // Прізвище Ім'я По батькові

    // Формуємо назву файлу на основі слуга та додаємо суфікс '-qr'
    $filename = $slug . '-qr.' . $qr_format;

    // Генерація URL для запиту до API
    $api_url = sprintf(
        'https://api.qrserver.com/v1/create-qr-code/?size=%s&color=%s&data=%s&format=%s',
        urlencode($qr_size),
        urlencode($qr_color),
        urlencode($qr_url),
        urlencode($qr_format)
    );

    // Виконання запиту до API для отримання QR-коду
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Помилка при отриманні QR-коду через API.'));
    }

    // Отримуємо тіло відповіді (QR-код)
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        wp_send_json_error(array('message' => 'Порожня відповідь від API.'));
    }

    // Додаємо підпис та опис до SVG-файлу
    if ($qr_format === 'svg') {
        $svg_title = '<title>QR-код для ' . esc_html($doctor_name) . '</title>';
        $svg_desc = '<desc>QR-код для лікаря ' . esc_html($doctor_name) . '</desc>';

        // Вставляємо підпис і опис у початок SVG-контенту
        $body = preg_replace('/(<svg[^>]*>)/', '$1' . $svg_title . $svg_desc, $body, 1);
    }

    // Зберігаємо QR-код у тимчасовий файл
    $upload_dir = wp_upload_dir();
    $filepath = $upload_dir['path'] . '/' . $filename;

    // Записуємо QR-код у файл
    $result = file_put_contents($filepath, $body);
    if (!$result) {
        wp_send_json_error(array('message' => 'Не вдалося зберегти QR-код.'));
    }

    // Створюємо медіа-об'єкт для збереження зображення в медіабібліотеці
    $filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($doctor_name . ' QR-код'),
        'post_content'   => 'QR-код для лікаря: ' . $doctor_name,
        'post_status'    => 'inherit',
    );

    // Вставляємо файл у медіабібліотеку
    $attach_id = wp_insert_attachment($attachment, $filepath);

    // Генеруємо метадані для зображення
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Додаємо альтернативний текст для зображення
    $alt_text = 'QR-код для лікаря ' . esc_html($doctor_name);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);  // Зберігаємо alt текст

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
