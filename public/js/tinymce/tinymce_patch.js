function __initMCE(selectorName, context, options) {
    let f = __initEditor(selectorName, context, options);
    __initAction(f);

    $(document).on(
        'click',
        'input[type="submit"], button[type="submit"]',
        function (event) {
            $(this)
                .closest('form')
                .find('.glyphicon-ok')
                .parent()
                .trigger('click');
        }
    );
}

function __initEditor(selectorName, context, options) {
// see https://www.tiny.cloud/docs-4x/configure/url-handling/#domainabsoluteurls
    const domainAbsoluteURLsOptions = {
        convert_urls: false,
        relative_urls: false,
        remove_script_host: false,
        document_base_url: window.location.origin,
    };

    const licenceKey = {license_key: 'gpl'}; //https://www.tiny.cloud/license-key/
    //To correct the printing of extra lines
    const newLineOptions = {
        newline_behavior: 'linebreak', //inserting a <br> instead of <p>
        remove_trailing_brs: true      //removing extra <br> at the end of a block
    };
    const defaultOptions = $.extend({}, domainAbsoluteURLsOptions, newLineOptions);
    const baseTinyMceOptions = {
        theme: 'silver',
        plugins: 'link image code fullscreen table',
        toolbar1:
            'bold italic underline | forecolor backcolor | styleselect | undo redo | ' +
            'alignleft aligncenter alignright alignjustify | bullist numlist | link image | fullscreen',
        menubar: false,
        height: 200,
        resize: true,
    };

    let languageOptions = {};

    if (navigator.language === 'fr') {
        languageOptions = {
            language_url: '/js/tinymce/langs/fr_FR.js',
            language: 'fr_FR',
        };
    }

    let finalOptions;

    if (options === undefined) {
        // No options provided → we start with the defaults + baseTinyMceOptions
        finalOptions = $.extend({}, defaultOptions, baseTinyMceOptions);
    } else {
        // Options provided → defaults are applied on top (to guarantee certain settings)
        finalOptions = $.extend({}, options, defaultOptions);
    }

    // Add language + license
    finalOptions = $.extend({}, finalOptions, languageOptions, licenceKey);

    // Init TinyMCE
    if (context) {
        $(selectorName, $(context)).tinymce(finalOptions);
    } else {
        finalOptions = $.extend({}, finalOptions, {selector: selectorName});
        tinymce.init(finalOptions);
    }

    // Height adjustment
    tinyMCE.DOM.setStyle(tinyMCE.DOM.get('content'), 'height', '500px');

    return $(selectorName).closest('.form-group');
}

function __pasteContentMCE() {
    tiny = tinyMCE.activeEditor;

    tiny.getElement().value = tiny.getContent({ format: 'html' });
}

function __destroyActiveMCE() {
    tiny = tinyMCE.activeEditor;
    $(tiny.getElement()).tinymce().remove();
}

function __removeAttrOnclick(e) {
    let fct = $(e).attr('onclick');
    $(e).removeAttr('onclick');
    return fct;
}

function __initAction(context) {
    $('.glyphicon-plus', context)
        .parent()
        .each(function (i) {
            let fct = __removeAttrOnclick(this);

            $(this).click(function (event) {
                __pasteContentMCE();
                eval(fct);
                $(this)
                    .closest('.textarea-group')
                    .parent()
                    .find('.textarea-group:not(:last-child)')
                    .each(function (i) {
                        __initModifications(this);
                    });
            });
        });

    $(context)
        .find('.textarea-group:not(:last-child)')
        .each(function (i) {
            __initModifications(this);
        });

    function __initModifications(context) {
        let textarea = $(context).find('textarea');
        $('.glyphicon-pencil', context)
            .parent()
            .each(function (i) {
                let fct = __removeAttrOnclick(this);
                $(this).click(function (event) {
                    tinyMCE.activeEditor.insertContent(textarea[0].value);
                    eval(fct);
                    $(
                        '.glyphicon-ok',
                        $(context).parent().find('.textarea-group:last')
                    )
                        .parent()
                        .each(function (i) {
                            let fct = __removeAttrOnclick(this);
                            $(this).click(function (event) {
                                __pasteContentMCE();
                                eval(fct);
                                if (!$.isEmptyObject(tinyMCE.activeEditor)) {
                                    tinyMCE.activeEditor.insertContent('');
                                }
                            });
                        });
                });
            });

        if (!$.isEmptyObject(tinyMCE.activeEditor)) {
            tinyMCE.activeEditor.insertContent('');
        }
    }
}
