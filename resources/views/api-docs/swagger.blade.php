<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerPay API — Swagger UI</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon-32x32.png') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('favicon-16x16.png') }}" sizes="16x16">

    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">

</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
<script>
    window.onload = () => {
        SwaggerUIBundle({
            url                 : @json(route('api.docs.spec')),
            dom_id              : '#swagger-ui',
            deepLinking         : true,
            presets             : [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
            layout              : 'StandaloneLayout',
            persistAuthorization: true,
            tryItOutEnabled     : true,
        });
    };
</script>
</body>
</html>
