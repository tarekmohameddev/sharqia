<?php

namespace App\Exceptions;

use App\Traits\ErrorLogsTrait;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ErrorLogsTrait;

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param \Throwable $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Redirect 419 (Page Expired / CSRF token mismatch) to login instead of showing error page
        if ($exception instanceof TokenMismatchException) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Please refresh the page and try again.'], 419);
            }
            $request->session()->flash('error', translate('Your_session_has_expired_Please_login_again') ?: 'Your session has expired. Please login again.');
            if ($request->is('admin/*')) {
                $adminLoginSlug = getWebConfig('admin_login_url') ?? 'admin';
                return redirect()->guest(url('login/' . $adminLoginSlug));
            }
            if ($request->is('vendor/*')) {
                return redirect()->guest(url('vendor/auth/login'));
            }
            return redirect()->guest(route('customer.auth.login'));
        }

        if ($this->isHttpException($exception) && $exception?->getStatusCode() == 404) {
            $redirectUrl = $this->storeErrorLogsUrl(url: $request->fullUrl(), statusCode: $exception->getStatusCode());
            if ($redirectUrl && isset($redirectUrl['redirect_url'])) {
                return redirect(to: $redirectUrl['redirect_url'], status: ($redirectUrl['redirect_status'] ?? '301'));
            }
        }
        return parent::render($request, $exception);
    }
}
