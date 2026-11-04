<?php

use App\Http\Middleware\EnsureCitizenAccess;
use App\Http\Middleware\EnsureEmployeeAccess;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'citizen.only' => EnsureCitizenAccess::class,
            'employee.only' => EnsureEmployeeAccess::class,
        ]);
        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // شكل موحّد لكل أخطاء الـ API: { success:false, message, errors }
        $json = fn (string $message, int $code, mixed $errors = null) => response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);

        $isApi = fn (Request $request) => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                return $json(collect($e->errors())->flatten()->first() ?? 'بيانات غير صحيحة', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                return $json('انتهت الجلسة — سجّل الدخول مجدداً', 401);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                return $json('محاولات كثيرة، انتظر دقيقة ثم أعد المحاولة', 429);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                return $json($e->getMessage() && $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : 'ليس لديك صلاحية لهذا الإجراء', 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                $msg = $e->getMessage();

                return $json($msg && ! str_starts_with($msg, 'No query results') && ! str_contains($msg, 'could not be found') ? $msg : 'العنصر المطلوب غير موجود', 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($json, $isApi) {
            if ($isApi($request)) {
                return $json($e->getMessage() ?: 'حدث خطأ', $e->getStatusCode());
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($json, $isApi) {
            if ($isApi($request) && ! config('app.debug')) {
                return $json('حدث خطأ في الخادم — حاول لاحقاً', 500);
            }
        });
    })->create();
