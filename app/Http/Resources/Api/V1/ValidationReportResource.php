<?php

namespace App\Http\Resources\Api\V1;

use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ValidationReport $resource
 */
class ValidationReportResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'isValid' => $this->resource->isValid(),
            'passed' => array_map(
                static fn (RuleResult $result): array => self::describe($result),
                $this->resource->passed(),
            ),
            'failed' => array_map(
                static fn (RuleResult $result): array => self::describe($result),
                $this->resource->failed(),
            ),
        ];
    }

    /**
     * @return array{rule: string, reason: ?string}
     */
    private static function describe(RuleResult $result): array
    {
        $segments = explode('\\', $result->rule());

        return [
            'rule' => end($segments),
            'reason' => $result->reason(),
        ];
    }
}
