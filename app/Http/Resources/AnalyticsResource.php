<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overview' => [
                'totalViews' => $this->resource['overview']['totalViews'] ?? 0,
                'totalApplications' => $this->resource['overview']['totalApplications'] ?? 0,
                'totalHires' => $this->resource['overview']['totalHires'] ?? 0,
                'totalJobPostings' => $this->resource['overview']['totalJobPostings'] ?? 0,
                'viewsChange' => $this->resource['overview']['viewsChange'] ?? 0,
                'applicationsChange' => $this->resource['overview']['applicationsChange'] ?? 0,
                'hiresChange' => $this->resource['overview']['hiresChange'] ?? 0,
                'jobPostingsChange' => $this->resource['overview']['jobPostingsChange'] ?? 0,
            ],
            'applicantDemographics' => [
                'byGender' => $this->resource['applicantDemographics']['byGender'] ?? [],
                'byAge' => $this->resource['applicantDemographics']['byAge'] ?? [],
                'byEducation' => $this->resource['applicantDemographics']['byEducation'] ?? [],
                'byExperience' => $this->resource['applicantDemographics']['byExperience'] ?? [],
            ],
            'applicationTrends' => [
                'byMonth' => $this->resource['applicationTrends']['byMonth'] ?? [],
                'byJobType' => $this->resource['applicationTrends']['byJobType'] ?? [],
                'byLocation' => $this->resource['applicationTrends']['byLocation'] ?? [],
            ],
            'performanceMetrics' => [
                'conversionRate' => $this->resource['performanceMetrics']['conversionRate'] ?? 0,
                'averageTimeToHire' => $this->resource['performanceMetrics']['averageTimeToHire'] ?? 0,
                'applicationCompletionRate' => $this->resource['performanceMetrics']['applicationCompletionRate'] ?? 0,
                'applicantQualityScore' => $this->resource['performanceMetrics']['applicantQualityScore'] ?? 0,
                'conversionRateChange' => $this->resource['performanceMetrics']['conversionRateChange'] ?? 0,
                'timeToHireChange' => $this->resource['performanceMetrics']['timeToHireChange'] ?? 0,
                'completionRateChange' => $this->resource['performanceMetrics']['completionRateChange'] ?? 0,
                'qualityScoreChange' => $this->resource['performanceMetrics']['qualityScoreChange'] ?? 0,
            ],
            'topPerformingJobs' => $this->resource['topPerformingJobs'] ?? [],
        ];
    }
}
