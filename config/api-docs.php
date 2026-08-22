<?php

declare(strict_types=1);

return [
    'enabled'      => (bool) env('API_DOCS_ENABLED', false),
    'openapi_path' => base_path('docs/openapi/ledgerpay.openapi.yaml'),
    'local_only'   => (bool) env('API_DOCS_LOCAL_ONLY', true),
];
