// jQuery 1.12.4, previously loaded from cdnjs, now self-hosted via webpack. Exposed as the
// window.jQuery/window.$ globals expected by non-bundled inline scripts, ZendX_JQuery_Form_Element
// widgets, jquery.fastLiveFilter.js and assets/jquery-ui.js. Imported from the concrete dist file
// rather than the bare "jquery" specifier so this entry actually bundles the library instead of
// being redirected to the external "jQuery" global by webpack.config.js's addExternals (which exists
// precisely so every *other* module gets that same global instead of its own private copy).
import $ from 'jquery/dist/jquery.js';

window.jQuery = window.$ = $;
