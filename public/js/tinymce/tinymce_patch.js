function __initMCE(selectorName, context, options) {
    var f = __initEditor(selectorName, context, options);
    __initAction(f);

    $(document).on('click', 'input[type="submit"], button[type="submit"]', function (event) {
        $(this).closest("form").find(".glyphicon-ok").parent().trigger("click");
    })
};

function __initEditor(selectorName, context, options) {

    // see https://www.tiny.cloud/docs-4x/configure/url-handling/#domainabsoluteurls
    let domainAbsoluteURLsOptions = {
        relative_urls: true,
        remove_script_host: true,
        document_base_url: window.location.origin
    }

    if (options === undefined) {
        options = $.extend(domainAbsoluteURLsOptions, {
            theme: "modern",
            plugins: "link image code fullscreen table",
            toolbar1: "bold italic underline | forecolor backcolor | styleselect | undo redo | alignleft aligncenter alignright alignjustify | bullist numlist | link image  | fullscreen",
            menubar: false,
        });

    } else {
        options = $.extend(options, domainAbsoluteURLsOptions);
    }

    if (context !== undefined) {
        $(selectorName, $(context)).tinymce(options);
    } else {
        options = $.extend(options, {selector: selectorName});
        tinymce.init(options);
    }

    tinyMCE.DOM.setStyle(tinyMCE.DOM.get('content'), 'height', '500px');

    return $(selectorName).closest('.form-group');
}

function __pasteContentMCE() {
    tiny = tinyMCE.activeEditor;

    tiny.getElement().value = tiny.getContent({format: 'html'});
};

function __destroyActiveMCE() {
    tiny = tinyMCE.activeEditor;
    $(tiny.getElement()).tinymce().remove();
};

function __removeAttrOnclick(e) {
    var fct = $(e).attr('onclick');
    $(e).removeAttr('onclick');
    return fct;
};

function __initAction(context) {

    $('.glyphicon-plus', context).parent().each(function (i) {
        var fct = __removeAttrOnclick(this);

        $(this).click(function (event) {
            __pasteContentMCE();
            eval(fct);
            $(this).closest('.textarea-group').parent().find('.textarea-group:not(:last-child)').each(function (i) {
                __initModifications(this);
            });
        });
    });

    $(context).find('.textarea-group:not(:last-child)').each(function (i) {
        __initModifications(this);
    });

    function __initModifications(context) {
        var textarea = $(context).find('textarea');
        $('.glyphicon-pencil', context).parent().each(function (i) {
            var fct = __removeAttrOnclick(this);
            $(this).click(function (event) {
                tinyMCE.activeEditor.setContent(textarea[0].value);
                eval(fct);
                $('.glyphicon-ok', $(context).parent().find('.textarea-group:last')).parent().each(function (i) {
                    var fct = __removeAttrOnclick(this);
                    $(this).click(function (event) {
                        __pasteContentMCE();
                        eval(fct);
                        if (!$.isEmptyObject(tinyMCE.activeEditor)) {
                            tinyMCE.activeEditor.setContent("");
                        }
                    });
                });
            });
        });

        if (!$.isEmptyObject(tinyMCE.activeEditor)) {
            tinyMCE.activeEditor.setContent("");
        }
    };
};