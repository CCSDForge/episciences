// DOMPurify 3.2.7, previously loaded from cdnjs, now self-hosted via webpack. Standalone (no jQuery
// dependency). Exposed as window.DOMPurify, used as a bare global by public/js/utils/sanitizer.js
// and other page scripts (request-doi, paperAffiAuthors, es.contacts-list).
import DOMPurify from 'dompurify';

window.DOMPurify = DOMPurify;
