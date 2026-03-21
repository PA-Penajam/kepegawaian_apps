<?php
// config/iam.php
return [
    'token_ttl_hours'      => env('IAM_TOKEN_TTL_HOURS', 8),
    'sso_code_ttl_seconds' => env('IAM_SSO_CODE_TTL', 60),
];