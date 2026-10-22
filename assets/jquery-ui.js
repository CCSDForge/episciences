// jQuery UI 1.13.2 widgets (1.12.1 when previously loaded from cdnjs), now self-hosted via webpack.
// Since 1.13 the datepicker inserts prevText/nextText as plain text, no longer as HTML (see
// public/js/datepicker/). Only the widgets actually used across the app are imported (see grep for
// .autocomplete/.tooltip/.sortable/.draggable/.datepicker in public/js and application/*/views).
// Each widget attaches itself to the global jQuery set up by assets/jquery.js, which must load first
// (see Episciences_View_Helper_JQuery_Container::_renderScriptTags()). CSS theme is a separate
// entry, see assets/jquery-ui-theme.js.
import 'jquery-ui/ui/widgets/autocomplete.js';
import 'jquery-ui/ui/widgets/tooltip.js';
import 'jquery-ui/ui/widgets/sortable.js';
import 'jquery-ui/ui/widgets/draggable.js';
import 'jquery-ui/ui/widgets/datepicker.js';
// disableSelection/enableSelection: no longer pulled in by sortable/draggable themselves (deprecated
// internal usage removed upstream), but public/js/volume/form.js still calls $(...).disableSelection()
// directly around its drag-and-drop lists.
import 'jquery-ui/ui/disable-selection.js';
