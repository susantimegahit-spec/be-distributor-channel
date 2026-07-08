<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        try {
            // Cek apakah tabel cron_jobs sudah termigrasi
            if (\Illuminate\Support\Facades\Schema::hasTable('cron_jobs')) {
                $jobs = \App\Models\CronJob::where('is_active', true)->get();
                foreach ($jobs as $job) {
                    $logId = null;

                    $schedule->command($job->command)
                        ->cron($job->expression)
                        ->before(function () use ($job, &$logId) {
                            $log = \App\Models\CronJobLog::create([
                                'cron_job_id' => $job->id,
                                'run_at' => now(),
                                'status' => 'running',
                            ]);
                            $logId = $log->id;
                        })
                        ->onSuccess(function () use ($job, &$logId) {
                            $job->update([
                                'last_run_at' => now(),
                                'last_run_status' => 'success'
                            ]);
                            if ($logId) {
                                $log = \App\Models\CronJobLog::find($logId);
                                if ($log) {
                                    $log->update([
                                        'finished_at' => now(),
                                        'status' => 'success',
                                        'duration_seconds' => (int) abs(now()->timestamp - $log->run_at->timestamp),
                                        'message' => 'Executed successfully via Scheduler.'
                                    ]);
                                }
                            }
                        })
                        ->onFailure(function () use ($job, &$logId) {
                            $job->update([
                                'last_run_at' => now(),
                                'last_run_status' => 'failed'
                            ]);
                            if ($logId) {
                                $log = \App\Models\CronJobLog::find($logId);
                                if ($log) {
                                    $log->update([
                                        'finished_at' => now(),
                                        'status' => 'failed',
                                        'duration_seconds' => (int) abs(now()->timestamp - $log->run_at->timestamp),
                                        'message' => 'Execution failed.'
                                    ]);
                                }
                            }
                        });
                }
            } else {
                $schedule->command('sap:sync-order-status')->everyFifteenMinutes();
            }
        } catch (\Throwable $e) {
            $schedule->command('sap:sync-order-status')->everyFifteenMinutes();
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\Illuminatech\MultipartMiddleware\MultipartFormDataParser::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->first() ?: 'Validation Error';

                $response = [
                    'success' => false,
                    'status_code' => 422,
                    'message' => $firstError,
                    'errors' => (object) [],
                ];

                if (isset($errors['active_session'])) {
                    $response['active_session'] = true;
                }

                return response()->json($response, 200);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status_code' => 401,
                    'message' => 'Unauthenticated.',
                    'errors' => (object) [],
                ], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status_code' => 404,
                    'message' => 'Resource not found.',
                    'errors' => (object) [],
                ], 200);
            }
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                // Treat 0 or invalid status codes as 500
                if ($status < 400 || $status > 599) {
                    $status = 500;
                }
                $message = $e->getMessage() ?: 'Internal Server Error.';
                
                $errors = [];
                if (config('app.debug')) {
                    $errors = [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                }

                return response()->json([
                    'success' => false,
                    'status' => $status,
                    'message' => $message,
                    'errors' => (object) $errors,
                ], 200);
            }
        });
    })->create();
