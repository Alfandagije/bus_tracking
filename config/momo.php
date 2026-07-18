<?php
// MTN MoMo Rwanda Configuration
// Get credentials from: https://momodeveloper.mtn.com

define('MOMO_API_USER', getenv('MTN_MOMO_API_USER') ?: '');
define('MOMO_API_KEY', getenv('MTN_MOMO_API_KEY') ?: '');
define('MOMO_SUBSCRIPTION_KEY', getenv('MTN_MOMO_SUBSCRIPTION_KEY') ?: '');
define('MOMO_ENVIRONMENT', getenv('MTN_MOMO_ENVIRONMENT') ?: 'sandbox'); // sandbox or production
define('MOMO_CALLBACK_URL', getenv('MTN_MOMO_CALLBACK_URL') ?: '');

if (MOMO_ENVIRONMENT === 'sandbox') {
    define('MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com');
} else {
    define('MOMO_BASE_URL', 'https://proxy.momoapi.mtn.com');
}

define('MOMO_CURRENCY', 'RWF');
define('MOMO_FARE_DEFAULT', 500.00);
