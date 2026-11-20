<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * كل رابط في الموقع يشير لدالة موجودة فعلاً في الـ Controller (وإلا يعطي خطأ 500 عند فتحه).
 * php artisan test --filter=RoutesIntegrityTest
 */
class RoutesIntegrityTest extends TestCase
{
    public function test_every_route_points_to_an_existing_method(): void
    {
        $broken = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action === 'Closure' || !str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);
            if (!class_exists($class)) {
                $broken[] = "{$route->uri()} => الكلاس غير موجود {$class}";
            } elseif (!method_exists($class, $method)) {
                $broken[] = "{$route->uri()} => {$class}@{$method} غير موجودة";
            }
        }

        $this->assertSame([], $broken, "روابط تشير لدوال غير موجودة:\n" . implode("\n", $broken));
    }

    public function test_every_web_route_except_login_pages_requires_auth(): void
    {
        $public = ['/', 'login', 'forgot-password', 'reset-password/{token}', 'reset-password', 'register', 'up', 'sanctum/csrf-cookie'];
        $open = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (!in_array('web', $middleware, true) || in_array($route->uri(), $public, true)) {
                continue;
            }
            if (!collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth'))) {
                $open[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $open, "روابط مفتوحة بدون تسجيل دخول:\n" . implode("\n", $open));
    }
}
