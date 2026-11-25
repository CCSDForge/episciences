function initTemplates() {
    const templates = document.getElementById('templates');

    if (!templates) {
        return;
    }

    templates.addEventListener('submit', function (e) {
        const form = e.target.closest('form.delete-template-form');

        if (!form) {
            return;
        }

        e.preventDefault();

        bootbox.setDefaults({ locale: locale });
        bootbox.confirm(translate('Êtes-vous sûr ?'), function (result) {
            if (result) {
                form.submit();
            }
        });
    });
}

// The script may be loaded after the document has been parsed
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTemplates);
} else {
    initTemplates();
}
