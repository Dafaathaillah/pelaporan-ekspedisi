# Upload configuration

Application photo and document validation has no maximum file size. Photo formats and required fields remain validated. Original photos are uploaded and compressed by PrivatePhotoResizer after arrival at the server (stored JPEG target <=512 KiB).

`public/.user.ini` disables PHP upload_max_filesize and post_max_size for PHP-FPM/CGI deployments that permit per-directory settings. PHP may cache this file for up to user_ini.cache_ttl (typically 300 seconds). CLI and Apache mod_php do not load .user.ini. public/.htaccess supplies the same values when the PHP 8 Apache module is present and the host permits php_value overrides. Otherwise set the directives in the active web PHP configuration and reload its service.

For Nginx, set `client_max_body_size 0;` in the relevant server/location block, validate configuration and reload. For Apache, set `LimitRequestBody 0` in the site configuration and reload. External proxies/hosting may impose their own limits; those must be configured separately.

These repository changes do not alter or reload a production server. Verify the effective web PHP values and proxy settings on deployment. Available memory, temporary storage, network and request timeouts still constrain processing; no finite server can guarantee arbitrarily large files.

References:
- https://www.php.net/manual/en/ini.core.php
- https://www.php.net/manual/en/configuration.file.per-user.php
- https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size
- https://httpd.apache.org/docs/2.4/mod/core.html#limitrequestbody
