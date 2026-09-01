; Africa CDC DHIS Performance Monitor - PHP runtime, tier M (16 GB / 4 CPUs).
; Values follow the enterprise optimisation guide's php.ini, minus the pieces
; that belong to the FPM pool (see docker/php-fpm-pool.conf).

; --- limits ------------------------------------------------------------------
memory_limit        = 256M
max_execution_time  = 120
max_input_time      = 120
max_input_vars      = 10000
max_input_nesting_level = 256
default_socket_timeout  = 120

; --- uploads -----------------------------------------------------------------
; The app has a file manager and accepts media uploads.
file_uploads        = On
upload_max_filesize = 128M
post_max_size       = 128M
max_file_uploads    = 50

; --- errors ------------------------------------------------------------------
; settings.php also sets display_errors from _DEBUG_MODE. These are the floor:
; errors are logged to the container log, never rendered to a visitor.
display_errors         = Off
display_startup_errors = Off
log_errors             = On
error_log              = /proc/self/fd/2
; E_STRICT is excluded because settings.php still references it.
error_reporting        = E_ALL & ~E_DEPRECATED & ~E_STRICT

; --- security ----------------------------------------------------------------
expose_php          = Off
allow_url_fopen     = On
allow_url_include   = Off
cgi.fix_pathinfo    = 0
zend.assertions     = -1
mail.add_x_header   = Off

; --- sessions ----------------------------------------------------------------
; The session cookie carries the login. Secure is flipped off by the entrypoint
; only when APP_URL is plain http, otherwise nobody could sign in.
session.use_strict_mode = 1
session.use_cookies     = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure   = 1
session.cookie_samesite = Lax
session.gc_maxlifetime  = 7200

; --- filesystem --------------------------------------------------------------
realpath_cache_size = 4096K
realpath_cache_ttl  = 600

; --- misc --------------------------------------------------------------------
date.timezone   = __PHP_TIMEZONE__
default_charset = UTF-8
zend.enable_gc  = On
