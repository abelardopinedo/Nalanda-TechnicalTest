<?php

use App\Http\Controllers\Api\V1\AssignEvaluatorController;
use App\Http\Controllers\Api\V1\CandidacyEvaluatorListingController;
use App\Http\Controllers\Api\V1\RegisterCandidacyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('candidacies', CandidacyEvaluatorListingController::class);
    Route::post('candidacies', RegisterCandidacyController::class);
    Route::post('candidacies/{candidacy}/evaluator', AssignEvaluatorController::class);
});
