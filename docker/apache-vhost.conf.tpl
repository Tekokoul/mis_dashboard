# Africa CDC DHIS Performance Monitor - Apache event MPM, web tier.
# Serves static files directly and proxies PHP to the PHP-FPM container.

ServerName __SERVER_NAME__

# Do not advertise the version or OS. This is the `server_tokens off`
# equivalent and closes the banner finding from the security scan.
ServerTokens Prod
ServerSignature Off
TraceEnable Off

# Real client IP behind the reverse proxy, for access logs and REMOTE_ADDR.
RemoteIPHeader X-Forwarded-For
RemoteIPTrustedProxy __TRUSTED_PROXY_CIDR__

<VirtualHost *:80>
    DocumentRoot /var/www/html/public

    # Hand every .php request to PHP-FPM. The FPM container's document root is
    # the same path, so SCRIPT_FILENAME resolves identically on both sides.
    <FilesMatch "\.php$">
        SetHandler "proxy:fcgi://__FPM_HOST__:__FPM_PORT__"
    </FilesMatch>

    # A PHP file that does not exist must 404 here rather than be proxied,
    # otherwise FPM answers "Primary script unknown" for every stray URL.
    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Nothing above the document root is servable.
    <Directory /var/www/html>
        Require all denied
    </Directory>

    # The reverse proxy terminates TLS and this container serves plain http.
    # Reflect the original scheme so the application can build correct absolute
    # URLs; the entrypoint separately pins HTTPS=on so public/.htaccess never
    # issues a redirect from in here. Redirecting http to https belongs at the
    # proxy, where the certificate is.
    SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on

    # The container health check calls this over plain http from inside the
    # network, so it must bypass the https redirect.
    <Files "health.php">
        Require all granted
        SetEnv HTTPS on
    </Files>

    # Diagnostics: reachable directly because .htaccess exempts real files from
    # the front controller, and it reports PHP/GD/server versions to anyone who
    # loads it. Closed by default.
    <Files "requirements.php">
        Require ip __REQUIREMENTS_ALLOW_FROM__
    </Files>

    # FPM's own status and ping endpoints must never be public.
    <LocationMatch "^/(fpm-status|fpm-ping)$">
        Require ip 127.0.0.1 __TRUSTED_PROXY_CIDR__
    </LocationMatch>

    # Proxy timeout must exceed the pool's request_terminate_timeout (120s) so
    # Apache reports the real error rather than a premature gateway timeout.
    ProxyTimeout 130

    ErrorLog  /dev/stderr
    CustomLog /dev/stdout combined
</VirtualHost>
