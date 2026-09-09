// Tom Select 2.6.2, previously loaded from jsdelivr as the "complete" UMD bundle, now self-hosted via
// webpack. Standalone (no jQuery dependency). The "complete" import bundles every optional plugin
// (dropdown_input, remove_button, checkbox_options, ...) so it matches the CDN build used by
// public/js/administratepaper/paper-assignment-modal.js. Exposed as window.TomSelect.
import TomSelect from 'tom-select';

import 'tom-select/dist/css/tom-select.min.css';

window.TomSelect = TomSelect;
