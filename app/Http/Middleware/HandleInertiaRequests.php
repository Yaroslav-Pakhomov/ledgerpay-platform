<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware для настройки взаимодействия Laravel с Inertia.js.
 *
 * Здесь задаётся корневой Blade-шаблон и данные,
 * которые автоматически передаются во все Inertia-компоненты.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * Корневой Blade-шаблон приложения.
     *
     *  Загружается при первом открытии страницы.
     *  Соответствует файлу resources/views/app.blade.php.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * Возвращает текущую версию frontend-ресурсов.
     *
     *  Inertia использует версию для определения изменений
     *  в JavaScript и CSS-файлах. Если версия изменилась,
     *  выполняется полная перезагрузка страницы.
     */
    public function version(Request $request): ?string
    {
        // Используем стандартную реализацию Inertia Middleware.
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Определяет данные, которые будут доступны
     *  во всех Inertia-компонентах по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Получаем текущего авторизованного пользователя.
        // Если пользователь не авторизован, значение будет null.
        $user = $request->user();

        return array_merge(
            // Сохраняем стандартные данные родительского middleware.
            parent::share($request), [
                /**
                 * Информация об авторизованном пользователе.
                 *
                 * На frontend доступна через:
                 * page.props.auth.user
                 */
                'auth' => [

                    'user' => $user ? [
                        'name'          => $user->name,
                        'email'         => $user->email,
                        'is_backoffice' => $user->isBackOffice(),
                    ] : null,
                ],
                /**
                 * Одноразовые сообщения из сессии.
                 *
                 * Обычно используются после перенаправления:
                 *
                 * return redirect()
                 *     ->route('users.index')
                 *     ->with('success', 'Пользователь создан');
                 *
                 * На frontend доступны через:
                 * page.props.flash.success
                 * page.props.flash.error
                 */
                'flash' => [
                    // Сообщение об успешном выполнении операции.
                    'success' => fn () => $request->session()->get('success'),
                    // Сообщение об ошибке.
                    'error' => fn () => $request->session()->get('error'),
                ],
            ]);
    }
}
