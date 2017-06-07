<?php
/**
 * @var \rethink\hrouter\CfgGenerator $this
 */
?>
global
    #log /dev/log    local0
    #log /dev/log    local1 notice
    #chroot /var/lib/haproxy
    stats socket 0.0.0.0:9999 mode 660 level admin
    stats timeout 300s
    #user haproxy
    #group haproxy
    #daemon

    maxconn 99999

    tune.ssl.default-dh-param 2048

    # Default SSL material locations
    ca-base /usr/local/etc/haproxy/ssl/certs
    crt-base /usr/local/etc/haproxy/ssl/private

    # Default ciphers to use on SSL-enabled listening sockets.
    # For more information, see ciphers(1SSL). This list is from:
    #  https://hynek.me/articles/hardening-your-web-servers-ssl-ciphers/
    ssl-default-bind-ciphers ECDH+AESGCM:DH+AESGCM:ECDH+AES256:DH+AES256:ECDH+AES128:DH+AES:ECDH+3DES:DH+3DES:RSA+AESGCM:RSA+AES:RSA+3DES:!aNULL:!MD5:!DSS
    ssl-default-bind-options no-sslv3

defaults
    #log    global
    mode    http
    #option    httplog
    #option    dontlognull
    option forwardfor
    option http-server-close
    timeout connect 5000
    timeout client  500000
    timeout server  500000

    #errorfile 400 /etc/haproxy/errors/400.http
    #errorfile 403 /etc/haproxy/errors/403.http
    #errorfile 408 /etc/haproxy/errors/408.http
    #errorfile 500 /etc/haproxy/errors/500.http
    #errorfile 502 /etc/haproxy/errors/502.http
    #errorfile 503 /etc/haproxy/errors/503.http
    #errorfile 504 /etc/haproxy/errors/504.http

listen stats
    bind 0.0.0.0:1080 #Listen on localhost port 9000
    mode http
    stats enable
    stats refresh 10s
    stats hide-version
    stats realm Haproxy\ Statistics
    stats uri /haproxy_stats
    stats auth seiue:haproxy


frontend http-in
    bind *:<?= $this->httpPort . PHP_EOL ?>
    mode http

    timeout http-keep-alive 1000
    option http-server-close

    acl is_https hdr(Host),map_reg(<?=$this->httpsMap()?>) -m found
    redirect scheme https code 301 if is_https

    use_backend %[base,map_reg(<?=$this->routeMap()?>)] if {  base,map_reg(<?=$this->routeMap()?>) -m found }


frontend https-in
    bind *:<?= $this->httpsPort . PHP_EOL ?>
    mode http

    use_backend %[base,map_reg(<?=$this->routeMap()?>)] if {  base,map_reg(<?=$this->routeMap()?>) -m found }


<?php foreach ($this->services as $def):?>

backend service_<?= $def['name'] ?>

    mode http
    fullconn <?= ($def['fullconn'] ?? 9999) . PHP_EOL?>
    option forwardfor
    option forceclose
    #option httpclose

    http-request set-header X-Forwarded-Port %[dst_port]
    http-request add-header X-Forwarded-Proto https if { ssl_fc }

<?php foreach ($def['rewrites'] ?? [] as $from => $to):?>
    reqrep ^([^\ :]*)\ <?= $from ?>     \1\ <?= $to ?>

<?php endforeach ?>

    option httpchk GET /

<?php foreach ($def['nodes'] as $index => $def):?>
    <?= $this->generateServer($def) ?>

<?php endforeach ?>
    compression algo gzip
    compression type text/css text/html text/javascript application/javascript text/plain text/xml application/json

<?php endforeach ?>