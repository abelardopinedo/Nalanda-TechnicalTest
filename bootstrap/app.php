<?php

use Candidacy\Application\Exception\CandidacyNotFoundException;
use Candidacy\Application\Exception\CandidacyValidationException;
use Candidacy\Application\Exception\EvaluatorNotFoundException;
use Candidacy\Domain\Exception\InvalidCandidacyStatusTransition;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (CandidacyValidationException $e) => response()->json([
            'message' => $e->getMessage(),
            'errors' => ['candidacy' => $e->reasons()],
        ], 422));

        $exceptions->render(fn (CandidacyNotFoundException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (EvaluatorNotFoundException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (InvalidCandidacyStatusTransition $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));
    })->create();
