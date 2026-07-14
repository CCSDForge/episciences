// jQuery UI 1.12.1 widgets, previously loaded from cdnjs, now self-hosted via webpack. Only the
// widgets actually used across the app are imported (see grep for .autocomplete/.tooltip/.sortable/
// .draggable/.datepicker in public/js and application/*/views). Each widget attaches itself to the
// global jQuery set up by assets/jquery.js, which must load first (see VENDOR_JQUERY/VENDOR_JQUERY_UI
// order in layout.phtml). CSS theme is a separate entry, see assets/jquery-ui-theme.js.
import 'jquery-ui/ui/widgets/autocomplete.js';
import 'jquery-ui/ui/widgets/tooltip.js';
import 'jquery-ui/ui/widgets/sortable.js';
import 'jquery-ui/ui/widgets/draggable.js';
import 'jquery-ui/ui/widgets/datepicker.js';
// disableSelection/enableSelection: no longer pulled in by sortable/draggable themselves (deprecated
// internal usage removed upstream), but public/js/volume/form.js still calls $(...).disableSelection()
// directly around its drag-and-drop lists.
import 'jquery-ui/ui/disable-selection.js';
