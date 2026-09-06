// FilePond 4.32.12, replaces the archived jQuery File Upload (blueimp) plugin, self-hosted via
// webpack. Standalone (no jQuery dependency). Exposed as window.FilePond, used as a bare global in
// public/js/library/es.filepond.js (loaded outside the webpack bundle, see webpackAssets helper).
//
// The package has no "FilePond" named export (only flat named exports: create, registerPlugin,
// etc.) — a namespace import is required to get the FilePond.create()/registerPlugin() surface.
import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondLocaleFrFr from 'filepond/locale/fr-fr.js';

import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css';

FilePond.registerPlugin(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview
);

window.FilePond = FilePond;
// FilePond's built-in FR label pack (idle/error/a11y strings), applied by
// es.filepond.js when the page locale (see translation.php) is 'fr'.
window.FilePondLocaleFrFr = FilePondLocaleFrFr;
