<?php

$secrets = json_decode(file_get_contents('/root/notification-tenant-secrets.json'), true, flags: JSON_THROW_ON_ERROR);
$sites = [
    '/var/www/primejobalerts.com/public_html/.env' => 'prime-job-alerts',
    '/var/www/primescholarshipalerts.com/public_html/.env' => 'prime-scholarship-alerts',
    '/var/www/zinstablog.com/public_html/.env' => 'zinstablog',
];

function setEnvironmentValue(string $path, string $key, string $value): void
{
    $contents = file_get_contents($path);
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    $contents = preg_match($pattern, $contents)
        ? preg_replace($pattern, $line, $contents)
        : rtrim($contents).PHP_EOL.$line.PHP_EOL;
    file_put_contents($path, $contents);
}

foreach ($sites as $path => $site) {
    setEnvironmentValue($path, 'NOTIFICATION_PLATFORM_ENABLED', 'true');
    setEnvironmentValue($path, 'NOTIFICATION_PLATFORM_URL', 'https://api.alphilnetworks.com/notifications');
    setEnvironmentValue($path, 'NOTIFICATION_PLATFORM_SITE', $site);
    setEnvironmentValue($path, 'NOTIFICATION_PLATFORM_KEY', $secrets[$site]);
    setEnvironmentValue($path, 'NOTIFICATION_PLATFORM_TIMEOUT', '15');
}

function setWordPressConstant(string $path, string $key, string $value): void
{
    $contents = file_get_contents($path);
    $definition = "define( '".$key."', '".addslashes($value)."' );";
    $pattern = '/^\s*define\s*\(\s*[\'\"]'.preg_quote($key, '/').'[\'\"]\s*,.*?\);\s*$/m';
    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, $definition, $contents);
    } else {
        $marker = "/* That's all, stop editing! Happy publishing. */";
        $contents = str_replace($marker, $definition.PHP_EOL.PHP_EOL.$marker, $contents);
    }
    file_put_contents($path, $contents);
}

setWordPressConstant('/var/www/zambiajobalerts.com/public_html/wp-config.php', 'CUSTOM_NOTIFICATIONS_URL', 'https://api.alphilnetworks.com/notifications');
setWordPressConstant('/var/www/zambiajobalerts.com/public_html/wp-config.php', 'CUSTOM_NOTIFICATIONS_KEY', $secrets['zambia-job-alerts']);

echo "Configured notification credentials for all four sites.\n";
