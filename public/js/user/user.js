document.addEventListener('DOMContentLoaded', function() {

    const deletePhotoButton = document.getElementById('delete-photo');
    if (deletePhotoButton) {
        deletePhotoButton.addEventListener('click', function(e) {
            e.preventDefault();

            const uid = this.getAttribute('attr-uid');
            const formData = new FormData();
            formData.append('uid', uid);

            fetch(JS_PREFIX_URL + 'user/ajaxdeletephoto', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                if (data == '1') {
                    const photoElements = document.querySelectorAll('.user-photo-normal, .user-photo-thumb');
                    photoElements.forEach(element => {
                        element.style.transition = 'opacity 0.5s ease';
                        element.style.opacity = '0';
                        setTimeout(() => element.style.display = 'none', 500);
                    });
                }

                const userPhotoElement = document.querySelector('.user-photo');
                if (userPhotoElement) {
                    userPhotoElement.style.transition = 'opacity 0.5s ease';
                    userPhotoElement.style.opacity = '0';
                    setTimeout(() => userPhotoElement.style.display = 'none', 500);
                }

                deletePhotoButton.classList.add('hidden');
                message('Photo supprimée.', 'alert-success');
            })
            .catch(error => {
                message('La suppression a échoué.', 'alert-danger');
            });

            return false;
        });
    }

});
