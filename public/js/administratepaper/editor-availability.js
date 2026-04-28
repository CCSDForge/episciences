/**
 * Handle editor availability in paper assignment forms
 * Disable checkboxes for unavailable editors
 * Allow unchecking unavailable editors if already selected
 * Redisable after unchecking
 */

function initializeEditorAvailability() {
    console.log('Initializing editor availability...');

    // Use the global unavailableEditors variable set in editorsform.phtml
    if (typeof unavailableEditors === 'undefined') {
        console.log('unavailableEditors variable not defined');
        return;
    }

    console.log('Unavailable editors:', unavailableEditors);

    if (!unavailableEditors || unavailableEditors.length === 0) {
        console.log('No unavailable editors');
        return;
    }

    unavailableEditors.forEach(function (editorId) {
        console.log('Processing editor ID:', editorId);
        const checkbox = $('input[name="editors[]"][value="' + editorId + '"]');

        if (checkbox.length === 0) {
            console.log('Checkbox not found for editor:', editorId);
            return;
        }

        console.log(
            'Checkbox found for editor:',
            editorId,
            'checked:',
            checkbox.is(':checked')
        );

        // Style the label to show unavailable status
        const label = checkbox.closest('label');
        label.css({
            color: '#dddddd',
        });

        // Track if this editor was initially checked
        const wasInitiallyChecked = checkbox.is(':checked');

        // Mark as unavailable with data attribute
        checkbox.data('unavailable-editor', true);

        if (!wasInitiallyChecked) {
            // If not checked initially, prevent any checking
            checkbox.data('prevent-check', true);

            // Visual indicator: more grayed out and add pointer events
            label.css({
                color: '#dddddd',
                cursor: 'not-allowed',
            });
            checkbox.css('cursor', 'not-allowed');

            console.log('Marked as non-checkable for editor:', editorId);
        } else {
            // If initially checked, allow unchecking but prevent re-checking
            checkbox.data('was-initially-checked', true);
            console.log('Marked as initially checked for editor:', editorId);
        }

        // Add click handler to prevent checking (but not unchecking)
        checkbox.on('click', function (e) {
            const $this = $(this);

            // If this is an unavailable editor that should not be checked
            if ($this.data('prevent-check') && $this.is(':checked')) {
                e.preventDefault();
                $this.prop('checked', false);
                console.log('Prevented checking unavailable editor:', editorId);
                return false;
            }

            // If this was initially checked and is now being unchecked
            if ($this.data('was-initially-checked') && !$this.is(':checked')) {
                // Mark it as prevent-check from now on
                $this.data('prevent-check', true);
                $this.data('was-initially-checked', false);

                // Update visual to show it can't be re-checked
                label.css({
                    color: '#dddddd',
                    'text-decoration': 'line-through',
                    cursor: 'not-allowed',
                });
                checkbox.css('cursor', 'not-allowed');

                console.log('Editor unchecked and now locked:', editorId);
            }
        });
    });
}
