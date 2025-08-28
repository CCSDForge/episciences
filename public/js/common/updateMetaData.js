async function updateMetaData(button, docId) {
    const recordLoading = document.getElementById('record-loading');
    recordLoading.innerHTML = getLoader();
    recordLoading.style.display = 'block';

    // Remove all event listeners from the button
    const newButton = button.cloneNode(true);
    button.parentNode.replaceChild(newButton, button);

    try {
        const formData = new URLSearchParams();
        formData.append('docid', docId);

        const response = await fetch(JS_PREFIX_URL + 'paper/updaterecorddata', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const result = await response.text();

        try {
            const obj_result = JSON.parse(result);
            alert(obj_result.message);

            if (!('error' in obj_result) && obj_result.affectedRows !== 0) {
                location.reload();
            }

        } catch (error) {
            console.log(error);
        }

    } catch (error) {
        console.error('Fetch error:', error);
        alert('An error occurred while updating metadata');
    } finally {
        recordLoading.style.display = 'none';
    }
}
