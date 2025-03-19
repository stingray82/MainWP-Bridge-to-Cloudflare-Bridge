@echo off
setlocal enabledelayedexpansion

:: Define variables
set "pluginFile=main-wp-to-cloudflare-bridge-extension/main-wp-to-cloudflare-bridge-extension.php"   :: Path to main plugin file

:: Run PHP script to update plugin headers
php -f update_plugin_headers.php "%pluginFile%"

echo Plugin headers updated successfully!
pause
