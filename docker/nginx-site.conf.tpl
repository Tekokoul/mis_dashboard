# Africa CDC DHIS Performance Monitor - site config.
#
# nginx does not read .htaccess, so every rule in public/.htaccess is
# reproduced here deliberately. The one rule intentionally NOT carried over is
# the http-to-https redirect: TLS terminates at the reverse proxy in front of
# this container, and redirecting from in here would loop.

server {
    listen 80;
    server_name __SERVER_NAME__;

    root  /var/www/html/public;
    index index.php;

    # Apache: Options -Indexes
    autoindex off;

    charset utf-8;

    # Security headers. Repeated inside every location that has an add_header
    # of its own, because add_header does not inherit (see the snippet).
    include snippets/security-headers.conf;

    # --- front controller ---------------------------------------------------
    # Apache:
    #   RewriteCond %{REQUEST_FILENAME} !-f
    #   RewriteCond %{REQUEST_FILENAME} !-d
    #   RewriteCond %{REQUEST_URI} !=/favicon.ico
    #   RewriteRule ^ index.php [L]
    location = /favicon.ico {
        log_not_found off;
        access_log off;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # --- PHP ----------------------------------------------------------------
    # The only directories PHP may write to are never executed as PHP, so a
    # file that lands there through an upload is served as a download or not
    # at all - never run.
    location ~* ^/(media|cache)/.*\.(php|phtml|phar)$ {
        deny all;
    }

    location ~ \.php$ {
        # try_files first: without it nginx would forward any URL ending in
        # .php to FPM, which answers "Primary script unknown" and turns a
        # would-be 404 into a 502.
        try_files $uri =404;

        include fastcgi_params;
        fastcgi_pass  __FPM_HOST__:__FPM_PORT__;
        fastcgi_index index.php;

        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO       $fastcgi_path_info;

        # The app builds absolute URLs from these. HTTPS is reported from the
        # proxy's X-Forwarded-Proto so links come out https even though this
        # container speaks plain http.
        fastcgi_param HTTPS           $forwarded_https if_not_empty;
        fastcgi_param REQUEST_SCHEME  $forwarded_scheme;

        # Must exceed the pool's request_terminate_timeout (120s) so a slow
        # request surfaces PHP's own error rather than a premature 504.
        fastcgi_read_timeout 130s;
        fastcgi_buffers      16 16k;
        fastcgi_buffer_size  32k;
    }

    # --- health -------------------------------------------------------------
    # Reachable from inside the container network without auth; used by the
    # container health check and by setup-production.sh.
    location = /health.php {
        include fastcgi_params;
        fastcgi_pass  __FPM_HOST__:__FPM_PORT__;
        fastcgi_param SCRIPT_FILENAME $document_root/health.php;
        access_log off;
    }

    # --- diagnostics ---------------------------------------------------------
    # requirements.php reports PHP, GD and server versions to whoever loads it.
    # Closed by default; widen REQUIREMENTS_ALLOW_FROM only while diagnosing.
    location = /requirements.php {
        allow __REQUIREMENTS_ALLOW_FROM__;
        allow 127.0.0.1;
        deny  all;
        include fastcgi_params;
        fastcgi_pass  __FPM_HOST__:__FPM_PORT__;
        fastcgi_param SCRIPT_FILENAME $document_root/requirements.php;
    }

    # FPM's own status endpoints must never be public.
    location ~ ^/(fpm-status|fpm-ping)$ {
        deny all;
    }

    # --- caching -------------------------------------------------------------
    # Mirrors the mod_expires / mod_headers blocks in public/.htaccess.
    # Each of these sets add_header, which drops the server-level security
    # headers for that location - hence the include in every block.
    location ~* \.(ico|flv|jpg|jpeg|png|gif|webp|svg|css|swf)$ {
        expires 31d;
        add_header Cache-Control "max-age=2678400, public";
        include snippets/security-headers.conf;
        access_log off;
        try_files $uri =404;
    }
    location ~* \.js$ {
        expires 31d;
        add_header Cache-Control "max-age=2678400, private";
        include snippets/security-headers.conf;
        access_log off;
        try_files $uri =404;
    }
    location ~* \.pdf$ {
        expires 1d;
        add_header Cache-Control "max-age=86400, public";
        include snippets/security-headers.conf;
        try_files $uri =404;
    }
    location ~* \.(woff|woff2|ttf|otf|eot)$ {
        expires 1y;
        add_header Cache-Control "max-age=31536000, public";
        include snippets/security-headers.conf;
        access_log off;
        try_files $uri =404;
    }

    # --- hardening -----------------------------------------------------------
    # Dotfiles, and anything that should never be fetched even if it lands in
    # the document root.
    location ~ /\.(?!well-known) {
        deny all;
        access_log off;
        log_not_found off;
    }
    location ~* \.(sql|sh|py|md|log|bak|tpl|ini|yml|yaml)$ {
        deny all;
    }
}
