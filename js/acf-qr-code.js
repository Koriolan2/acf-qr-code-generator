document.addEventListener('DOMContentLoaded', function () {
    const qrButton = document.getElementById('generate_qr_code');

    if (qrButton) {
        qrButton.addEventListener('click', function () {
            const url = document.getElementById('acf_qr_url').value;
            if (!url) {
                alert('Будь ласка, введіть URL-адресу.');
                return;
            }

            const data = {
                action: 'generate_qr_code',
                qr_url: url,
                post_id: qrButton.dataset.postId
            };

            jQuery.post(acf_qr_code_ajax.ajaxurl, data, function (response) {
                if (response.success) {
                    const qrCodeImageContainer = document.getElementById('qr_code_image');
                    if (!qrCodeImageContainer) {
                        const newQrCodeImageContainer = document.createElement('div');
                        newQrCodeImageContainer.id = 'qr_code_image';
                        newQrCodeImageContainer.classList.add('qr-code-image-preview');
                        qrButton.parentElement.parentElement.appendChild(newQrCodeImageContainer);
                    }

                    document.getElementById('qr_code_image').innerHTML = '<img src="' + response.data.image_url + '" alt="QR Code" style="max-width:100%;" />';

                    if (!document.getElementById('delete_qr_code')) {
                        const deleteButtonHtml = '<p><button type="button" id="delete_qr_code" class="button button-secondary" data-post-id="' + qrButton.dataset.postId + '">Видалити QR-код</button></p>';
                        jQuery('#qr_code_image').after(deleteButtonHtml);

                        bindDeleteButton();
                    }
                } else {
                    alert('Помилка при генерації QR-коду: ' + response.data.message);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                alert('Сталася помилка при генерації QR-коду.');
            });
        });
    }

    function bindDeleteButton() {
        const deleteButton = document.getElementById('delete_qr_code');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                const data = {
                    action: 'delete_qr_code',
                    post_id: deleteButton.dataset.postId
                };

                jQuery.post(acf_qr_code_ajax.ajaxurl, data, function (response) {
                    if (response.success) {
                        alert('QR-код успішно видалено.');
                        document.getElementById('qr_code_image').remove();
                        deleteButton.remove();
                    } else {
                        alert('Помилка при видаленні QR-коду: ' + response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    alert('Сталася помилка при видаленні QR-коду.');
                });
            });
        }
    }

    bindDeleteButton();
});
