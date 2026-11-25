<?php
declare(strict_types=1);
return [
 'name' => getenv('APP_NAME') ?: 'أصل المتصدر',
 'env' => getenv('APP_ENV') ?: 'production',
 'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
 'url' => rtrim(getenv('APP_URL') ?: '', '/'),
 'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Aden',
 'whatsapp' => preg_replace('/\D+/', '', getenv('WHATSAPP_NUMBER') ?: '967733148606'),
];
