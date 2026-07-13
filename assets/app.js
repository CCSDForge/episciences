// assets/app.js
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// Vendor CSS (base styles) — imported first so the application's own theme (main.scss, below) can
// override them in the cascade.
// Bootstrap 3.3.7 CSS, previously loaded from the cdnjs CDN (VENDOR_BOOTSTRAP), now self-hosted via webpack.
import 'bootstrap/dist/css/bootstrap.min.css';
// Font Awesome 7.0.1 CSS, previously loaded from cdnjs (VENDOR_FONT_AWESOME*), now self-hosted via webpack.
import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import '@fortawesome/fontawesome-free/css/solid.min.css';
import '@fortawesome/fontawesome-free/css/brands.min.css';
// Cookie Consent 3.1.1 CSS, previously loaded from cdnjs (VENDOR_COOKIE_CONSENT_CSS), now self-hosted via webpack.
import 'cookieconsent/build/cookieconsent.min.css';

// Application theme — imported last so it overrides the vendor CSS above (any CSS you import will
// output into a single css file, main.css in this case).
import '../sass/main.scss';
// Cookie Consent 3.1.1 JS, previously loaded from cdnjs with an SRI hash, now self-hosted via webpack.
// Exposes window.cookieconsent, used by the inline initialise() call in layout.phtml.
import 'cookieconsent/build/cookieconsent.min.js';

// Bootstrap 3.2.0 JS, previously loaded from cdnjs (VENDOR_BOOTSTRAP_JS), now self-hosted via webpack.
// Kept at 3.2.0 (not the 3.3.7 used for the CSS above) to match the version already in production —
// aliased in package.json as "bootstrap-legacy-js" so it can coexist with the 3.3.7 CSS package.
// Attaches its plugins ($.fn.modal, $.fn.tooltip, ...) to the global jQuery (see addExternals in
// webpack.config.js), so it behaves exactly like the previous <script> tag.
import 'bootstrap-legacy-js/dist/js/bootstrap.min.js';

// Bootbox 5.5.3 JS, previously loaded from cdnjs (VENDOR_BOOTBOX), now self-hosted via webpack.
// Exposes window.bootbox, used as a bare global throughout public/js.
import bootbox from 'bootbox';

window.bootbox = bootbox;

// Need jQuery? Install it with "yarn add jquery", then uncomment to import it.
// import $ from 'jquery';