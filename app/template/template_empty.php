<?php
// Partial views, fetched by AJAX and injected into a page that is already
// loaded. Only the view and its stylesheets are emitted: no <script> tags.
// The scripts the partials used to re-include (DataTables, select2, TinyMCE,
// ce.js ...) are on every parent page already, and jQuery executes injected
// <script src> tags as inline code, which the Content-Security-Policy
// (script-src 'self' 'nonce-...') rightly refuses.
foreach($this->CSS as $CSSfile) {
    print '<link rel="stylesheet" href="'. $CSSfile.'?v='._CURRENT_COMMIT.'">';
}

include $viewPath;
