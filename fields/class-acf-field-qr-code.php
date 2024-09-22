<?php

if (!defined('ABSPATH')) {
    exit;
}

class ACF_Field_QR_Code extends acf_field {

    // Конструктор поля
    public function __construct($settings) {
        $this->name = 'qr_code'; // Унікальне ім'я поля
        $this->label = __('QR Code', 'acf-qr-code-generator'); // Назва поля в адмінці
        $this->category = 'basic'; // Категорія
        $this->defaults = array(); // Стандартні налаштування

        parent::__construct();
    }

    // Виведення поля в редакторі
    public function render_field($field) {
        // Отримання значення
        $value = esc_url($field['value']);
        $qr_code_image_id = get_post_meta(get_the_ID(), '_worker_qr_image_id', true);
        $qr_code_image_url = $qr_code_image_id ? wp_get_attachment_url($qr_code_image_id) : '';

        ?>
        <div class="acf-qr-code-field">
            <label for="<?php echo esc_attr($field['id']); ?>">URL для генерації QR-коду</label>
            <input type="url" id="acf_qr_url" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($value); ?>" class="full-width" />
            <p>
                <button type="button" id="generate_qr_code" class="button button-primary" data-post-id="<?php echo get_the_ID(); ?>">Згенерувати QR-код</button>
            </p>

            <?php if ($qr_code_image_url): ?>
                <div id="qr_code_image" class="qr-code-image-preview">
                    <img src="<?php echo esc_url($qr_code_image_url); ?>" alt="QR Code" style="max-width:100%;" />
                </div>
                <p>
                    <button type="button" id="delete_qr_code" class="button button-secondary" data-post-id="<?php echo get_the_ID(); ?>">Видалити QR-код</button>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    // Оновлення значення поля
    public function update_value($value, $post_id, $field) {
        return esc_url($value);
    }
}

// Реєстрація поля
new ACF_Field_QR_Code(array(
    'version' => '2.0',
    'url'     => plugin_dir_url(__FILE__),
    'path'    => plugin_dir_path(__FILE__)
));
