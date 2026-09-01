# nginx main config - tier M (16 GB RAM / 4 CPUs).
#
# The reference profile is written for Apache event MPM; these are the nginx
# equivalents of the same knobs. Concurrency is really bounded by PHP-FPM's
# pm.max_children (14) - nginx just needs enough connections to hold the queue
# and serve static files without blocking.

user  www-data;   # Debian nginx package; matches the FPM pool user
worker_processes  auto;            # one per CPU: 4 on this tier
worker_rlimit_nofile 65535;
pid /var/run/nginx.pid;

events {
    worker_connections  2048;      # 4 x 2048 well above the Apache tier's 150
    multi_accept        on;
    use                 epoll;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    # Do not advertise the version. Equivalent to Apache's ServerTokens Prod,
    # and closes the server-banner finding from the security scan.
    server_tokens off;

    sendfile      on;
    tcp_nopush    on;
    tcp_nodelay   on;
    types_hash_max_size 2048;

    # Mirrors the KeepAlive block in the reference Apache config.
    keepalive_timeout  5;
    keepalive_requests 500;

    # The app accepts media uploads; must match post_max_size in php.ini.
    client_max_body_size 128M;
    client_body_buffer_size 128k;

    # Compression, matching the mod_deflate block in public/.htaccess.
    gzip              on;
    gzip_vary         on;
    gzip_proxied      any;
    gzip_comp_level   5;
    gzip_min_length   256;
    gzip_types
        application/javascript application/x-javascript application/json
        application/xml application/xhtml+xml application/rss+xml
        application/vnd.ms-fontobject application/x-font-ttf
        font/opentype font/otf font/ttf
        image/svg+xml image/x-icon
        text/css text/plain text/javascript text/xml;

    # Real client IP behind the reverse proxy.
    set_real_ip_from  __TRUSTED_PROXY_CIDR__;
    real_ip_header    X-Forwarded-For;
    real_ip_recursive on;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" rt=$request_time uct=$upstream_connect_time '
                    'urt=$upstream_response_time';
    access_log /dev/stdout main;
    error_log  /dev/stderr warn;

    # The reverse proxy terminates TLS. These translate its X-Forwarded-Proto
    # into what PHP expects, so the app builds https URLs while this container
    # continues to speak plain http. Empty when the header is absent, because
    # fastcgi_param ... if_not_empty then omits HTTPS entirely.
    map $http_x_forwarded_proto $forwarded_https {
        default  "";
        https    on;
    }
    map $http_x_forwarded_proto $forwarded_scheme {
        default  $scheme;
        ""       $scheme;
        http     http;
        https    https;
    }

    include /etc/nginx/conf.d/*.conf;
}
