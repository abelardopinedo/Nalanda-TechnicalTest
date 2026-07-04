<?php

use App\Http\Controllers\Api\V1\AssignEvaluatorController;
use App\Http\Controllers\Api\V1\CandidacyEvaluatorListingController;
use App\Http\Controllers\Api\V1\CandidacySummaryController;
use App\Http\Controllers\Api\V1\RegisterCandidacyController;
use App\Http\Controllers\Api\V1\ValidateCandidacyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('candidacies', CandidacyEvaluatorListingController::class);
    Route::post('candidacies', RegisterCandidacyController::class);
    Route::get('candidacies/{candidacy}/summary', CandidacySummaryController::class);
    Route::post('candidacies/{candidacy}/validate', ValidateCandidacyController::class);
    Route::post('candidacies/{candidacy}/evaluator', AssignEvaluatorController::class);
});
