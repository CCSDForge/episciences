var openedPopover = null;

function getSectionForm(button, docid, partial) {
    let isPartial = partial !== '' ? JSON.parse(partial) : false;
    let placement = 'bottom';

    // destroy other popups
    $('button').popover('destroy');

    // check if popup has to open or close
    if (openedPopover && openedPopover == docid) {
        openedPopover = null;
        return false;
    } else {
        openedPopover = docid;
    }

    // fetch section form
    let sectionFormRequest = ajaxRequest('/administratepaper/sectionform', {
        docid: docid,
    });

    $(button)
        .popover({
            placement: placement,
            container: 'body',
            html: true,
            content: getLoader(),
        })
        .popover('show');

    sectionFormRequest.done(function (section_form) {
        // destroy loading popup
        $(button).popover('destroy');
        openedPopover = null;

        // inject section form in popover
        $(button)
            .popover({
                placement: placement,
                container: 'body',
                html: true,
                content: section_form,
            })
            .popover('show');

        $('form[id^="section-assignment-form-"]').on('submit', function () {
            if (!$(this).data('submitted')) {
                // to fix duplicate ajax request
                $(this).data('submitted', true);
                let $section_container = $(button).closest('.section');
                let $editors_container = isPartial
                    ? $(button).closest('tr').find('div.editors')
                    : $('#editors').closest('.editors').parent();
                // process form (ajax)
                let jData = $(this).serialize() + '&docid=' + docid;
                let saveSection = ajaxRequest(
                    '/administratepaper/savesection',
                    jData
                );
                saveSection.done(function (result) {
                    if (result) {
                        if (!isPartial) {
                            location.replace(location.href);
                            return true;
                        }

                        $(button).popover('destroy');
                        $section_container.hide();
                        $section_container.html(getLoader());
                        $section_container.fadeIn();

                        // refresh section block
                        let refreshSection = ajaxRequest(
                            '/administratepaper/displaysection',
                            {
                                docid: docid,
                                partial: isPartial,
                            }
                        );

                        refreshSection.done(function (sResult) {
                            $section_container.hide();
                            $section_container.html(sResult);
                            $section_container.fadeIn();
                        });

                        // if checkbox is checked
                        if ($('#assignEditors').prop('checked')) {
                            // refresh editors block
                            $editors_container.hide();
                            $editors_container.html(getLoader());
                            $editors_container.fadeIn();

                            let displayEditors = ajaxRequest(
                                '/administratepaper/displayeditors',
                                {
                                    docid: docid,
                                    partial: isPartial,
                                }
                            );

                            displayEditors.done(function (eResult) {
                                $editors_container.hide();
                                $editors_container.html(eResult);
                                $editors_container.fadeIn();
                            });
                        }
                        // refresh paper history
                        // if (!isPartial) { // not partial
                        //     refreshPaperHistory(docid);
                        // }
                    }
                });
            }
            return false;
        });
    });
}

function closeResult() {
    $('button').popover('destroy');
}
