<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\IDomainRuleViolation;
use App\Http\Middleware\ApiRequestLoggingMiddleware;
use App\Http\Middleware\EnsureBackofficeUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Support\Http\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'backoffice' => EnsureBackofficeUser::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->api(append: [
            RequestIdMiddleware::class,
            ApiRequestLoggingMiddleware::class,
            SecurityHeadersMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Определяет, что ответ должен быть JSON (Problem Details), а не HTML.
         *
         * Срабатывает для маршрутов api/* (включая Accept: *\*)
         * или когда Request::expectsJson() === true.
         * shouldRenderJsonWhen() подключает это же правило к стандартному
         * рендерингу исключений Laravel (до кастомных render-обработчиков).
         */
        $wantsApiResponse = static function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        /**
         * Превышен кол-во запросов — 429
         *
         * Исключение ThrottleRequestsException возникает при срабатывании middleware throttle:*
         * (auth, api-global, money-movement, backoffice-heavy).
         *
         * Для API возвращается ответ Problem Details вместо общей HTTP-ошибки.
         */
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            return ProblemDetails::make(
                request: $request,
                title: 'Слишком много запросов',
                detail: 'Превышен лимит скорости. Пожалуйста, повторите попытку позже.',
                status: Response::HTTP_TOO_MANY_REQUESTS,
                type: 'https://ledgerpay.local/problems/rate-limit-exceeded',
            );
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $wantsApiResponse($request),
        );

        /**
         * Некорректные входящие данные — 422
         *
         * Обрабатывает ошибки валидации входящих данных.
         *
         *  Например:
         *  - не передано обязательное поле;
         *  - строка передана вместо числа;
         *  - email имеет неправильный формат.
         *
         *  Laravel выбрасывает ValidationException, когда данные
         *  не проходят правила валидации FormRequest или Validator.
         */
        $exceptions->render(function (ValidationException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Формируем стандартизированный ответ с кодом 422.
             *
             * $exception->errors() возвращает ошибки по полям:
             *
             * [
             *     'email' => ['The email field is required.'],
             *     'amount' => ['The amount must be a number.'],
             * ]
             */
            return ProblemDetails::validation(
                request: $request,
                errors: $exception->errors(),
            );
        });

        /**
         * Запись в базе не найдена — 404
         *
         * Обрабатывает ситуацию, когда запись Eloquent не найдена.
         *
         *  Такое исключение обычно возникает при использовании:
         *
         *  Model::findOrFail($id);
         *  Model::firstOrFail();
         *
         *  Или при автоматическом Route Model Binding.
         */
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращаем HTTP 404.
             *
             * Реальное сообщение ModelNotFoundException наружу не передаётся,
             * потому что оно может содержать имя PHP-модели и другие
             * внутренние детали приложения.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Resource not found',
                detail: 'The requested resource does not exist.',
                status: Response::HTTP_NOT_FOUND,
                type: 'https://ledgerpay.local/problems/resource-not-found',
            );
        });

        /**
         * Маршрут или HTTP-ресурс не найден — 404
         *
         * Обрабатывает HTTP-исключение NotFoundHttpException.
         *
         * Такое исключение обычно возникает, когда:
         *
         * - запрошенный маршрут не зарегистрирован;
         * - URL указан неверно;
         * - вызывается abort(404);
         * - фреймворку не удалось найти запрашиваемый HTTP-ресурс.
         *
         * В отличие от ModelNotFoundException, это исключение относится
         * не обязательно к записи в базе данных, а к HTTP-уровню приложения.
         */
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращаем стандартный JSON-ответ с HTTP-кодом 404.
             *
             * Реальное сообщение NotFoundHttpException не передаётся клиенту,
             * поскольку оно может содержать внутреннюю информацию о маршрутах
             * или структуре приложения.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Resource not found',
                detail: 'The requested resource does not exist.',
                status: Response::HTTP_NOT_FOUND,
                type: 'https://ledgerpay.local/problems/resource-not-found',
            );
        });

        /**
         * Нарушено бизнес-правило — 409
         *
         * Обрабатывает нарушения бизнес-правил приложения.
         *
         *  IDomainRuleViolation — общий интерфейс для доменных исключений.
         *
         *  Его могут реализовывать, например:
         *
         *  - InsufficientFundsException;
         *  - CurrencyMismatchException;
         *  - InactiveAccountException;
         *  - SameAccountTransferException.
         *
         *  Благодаря интерфейсу не нужно регистрировать отдельный обработчик
         *  для каждого доменного исключения.
         */
        $exceptions->render(function (IDomainRuleViolation $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращается 409 Conflict.
             *
             * Это означает, что запрос технически корректный,
             * но его невозможно выполнить из-за текущего состояния системы
             * или нарушения бизнес-правила.
             *
             * Например:
             * - недостаточно средств;
             * - счёт заблокирован;
             * - валюты счетов не совпадают.
             *
             * Клиенту передаётся сообщение конкретного доменного исключения,
             * поэтому такие сообщения должны быть безопасными
             * и не содержать технических подробностей.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Domain rule violation',
                detail: $exception->getMessage(),

                status: Response::HTTP_CONFLICT,
                type: 'https://ledgerpay.local/problems/domain-rule-violation',
            );
        });

        /**
         * Пользователь не авторизован — 401
         *
         * Обрабатывает запросы от неавторизованного пользователя.
         *
         *  AuthenticationException возникает, когда защищённый маршрут
         *  требует авторизации, но пользователь:
         *
         *  - не передал токен;
         *  - передал недействительный токен;
         *  - не имеет активной аутентифицированной сессии.
         */
        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращаем JSON с кодом 401 вместо HTML-редиректа на страницу входа.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Unauthenticated',
                detail: 'Authentication is required.',
                status: Response::HTTP_UNAUTHORIZED,
                type: 'https://ledgerpay.local/problems/unauthenticated',
            );
        });

        /**
         * Недостаточно прав для выполнения действия — 403
         *
         * Обрабатывает ошибки авторизации на уровне Laravel.
         *
         * AuthorizationException обычно возникает, когда проверка доступа
         * через Gate или Policy завершается отказом, например при вызове:
         *
         * - Gate::authorize(...);
         * - $this->authorize(...);
         * - authorizeResource(...);
         * - метода Policy, вернувшего false или Response::deny().
         *
         * В отличие от AuthenticationException с кодом 401, пользователь
         * может быть успешно аутентифицирован, но не иметь разрешения
         * на выполнение конкретного действия.
         */
        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращаем единообразный JSON-ответ с кодом 403,
             * не раскрывая клиенту внутренние правила Gate или Policy.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Forbidden',
                detail: 'You are not allowed to perform this action.',
                status: Response::HTTP_FORBIDDEN,
                type: 'https://ledgerpay.local/problems/forbidden',
            );
        });

        /**
         * Доступ к HTTP-ресурсу запрещён — 403
         *
         * Обрабатывает HTTP-исключение AccessDeniedHttpException
         * на уровне Symfony HttpKernel.
         *
         * Такое исключение может возникнуть, когда:
         *
         * - вызывается abort(403);
         * - middleware запрещает доступ к ресурсу;
         * - исключение авторизации преобразуется в HTTP-исключение;
         * - компонент фреймворка отклоняет запрос из-за отсутствия прав.
         *
         * Отдельный обработчик нужен, чтобы любые HTTP-ошибки доступа
         * возвращались в том же формате Problem Details, что и Laravel-ошибки
         * авторизации.
         */
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Возвращаем безопасный ответ с кодом 403 без исходного сообщения
             * исключения, которое может содержать внутренние детали проверки.
             */
            return ProblemDetails::make(
                request: $request,
                title: 'Forbidden',
                detail: 'You are not allowed to perform this action.',
                status: Response::HTTP_FORBIDDEN,
                type: 'https://ledgerpay.local/problems/forbidden',
            );
        });

        /**
         * Все остальные ошибки — исходный HTTP-код или 500
         *
         * Универсальный обработчик всех остальных исключений.
         *
         *  Throwable является базовым типом для:
         *
         *  - Exception;
         *  - Error;
         *  - TypeError;
         *  - RuntimeException;
         *  - HTTP-исключений;
         *  - других необработанных ошибок.
         *
         *  Этот обработчик обязательно должен находиться последним,
         *  иначе он может перехватить исключения раньше специализированных
         *  обработчиков выше.
         */
        $exceptions->render(function (Throwable $exception, Request $request) use ($wantsApiResponse) {
            if (!$wantsApiResponse($request)) {
                return null;
            }

            /*
             * Если исключение является HTTP-исключением,
             * сохраняем его исходный HTTP-статус.
             *
             * Например:
             * - 403 Forbidden;
             * - 404 Not Found;
             * - 405 Method Not Allowed;
             * - 429 Too Many Requests.
             *
             * Если это обычное PHP-исключение, возвращаем 500.
             */
            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            /*
             * Записываем настоящую ошибку в серверный лог.
             *
             * Клиенту технические подробности не показываются,
             * но разработчик сможет найти исключение по request_id.
             */
            Log::error('Unhandled API exception.', [
                'request_id'      => $request->headers->get('X-Request-Id'),
                'exception_class' => $exception::class,
                'message'         => $exception->getMessage(),
                'path'            => $request->path(),
                'method'          => $request->method(),
            ]);

            /*
             * Для ошибок 500 и выше скрываем настоящее сообщение исключения.
             *
             * Например, клиент не должен увидеть:
             *
             * - SQL-запрос;
             * - пароль подключения;
             * - путь к файлу;
             * - stack trace;
             * - внутреннее имя класса.
             *
             * Для HTTP-ошибок 4xx текущее сообщение исключения передаётся клиенту.
             */
            return ProblemDetails::make(
                request: $request,

                title: $status >= 500 ? 'Internal server error' : 'HTTP error',

                detail: $status >= 500
                    ? 'An unexpected error occurred.'
                    : $exception->getMessage(),

                status: $status,
                type: 'https://ledgerpay.local/problems/http-error',
            );
        });
    })->create();
