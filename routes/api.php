<?php

use App\Http\Controllers\API\Activity\EmployerActivityController;
use App\Http\Controllers\API\Activity\JobSeekerActivityController;
use App\Http\Controllers\API\Admin\AdminAuthController;
use App\Http\Controllers\API\Admin\AllApplicationController;
use App\Http\Controllers\API\Admin\AllEmployerController;
use App\Http\Controllers\API\Admin\AllJobController;
use App\Http\Controllers\API\Admin\AllJobSeekerController;
use App\Http\Controllers\API\Admin\AllUserController;
use App\Http\Controllers\API\Admin\BlogController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\ImpersonationController;
use App\Http\Controllers\API\ApiKeyController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AuthEmployerController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\DashboardEmployerController;
use App\Http\Controllers\API\DashboardJobSeekerController;
use App\Http\Controllers\API\Employer\AdvancedAnalyticsController;
use App\Http\Controllers\API\Employer\ApplicationController as EmployerApplicationController;
use App\Http\Controllers\API\Employer\ApplicationInterviewController;
use App\Http\Controllers\API\Employer\ApplicationNoteController;
use App\Http\Controllers\API\Employer\CalendarEventController;
use App\Http\Controllers\API\Employer\CandidateController;
use App\Http\Controllers\API\Employer\ResumeSearchController;
use App\Http\Controllers\API\EmployerController;
use App\Http\Controllers\API\JobAlertController;
use App\Http\Controllers\API\JobController;
use App\Http\Controllers\API\MigrationController;
use App\Http\Controllers\API\ResumeController;
use App\Http\Controllers\API\SkillController;
use App\Http\Controllers\API\TwoFactorController;

use App\Mail\SendEmailVerificationMail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestEmail;
use Illuminate\Support\Facades\Broadcast;

// guests
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('verify-email', [AuthController::class, 'verifyEmail']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::get('login-with-token', [AuthController::class, 'loginWithToken']);

Route::group(['prefix' => 'employer'], function () {
    Route::post('login', [AuthEmployerController::class, 'login']);
    Route::post('register', [AuthEmployerController::class, 'register']);
    Route::post('verify-email', [AuthEmployerController::class, 'verifyEmail']);
    Route::post('forgot-password', [AuthEmployerController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthEmployerController::class, 'resetPassword']);
    Route::post('login-as-employer', [AuthEmployerController::class, 'loginAsEmployer']);
});

Route::get('job/latest', [JobController::class, 'index']);
Route::get('job/description/{slug}', [JobController::class, 'jobDescription']);
Route::get('job/popular', [JobController::class, 'popularJobs']);
Route::get('job/expiring', [JobController::class, 'expiringJobs']);
Route::get('job/search', [JobController::class, 'search']);

Route::get("job/departments", [JobController::class, 'departments']);

Route::get("seo/job-info/{slug}", [JobController::class, 'jobSEOInfo']);
Route::get("seo/job-slugs", [JobController::class, 'getJobSlugs']);

// protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('all-employers', [AuthEmployerController::class, 'allEmployers'])->middleware('checkRole:employer');

    Route::group(['prefix' => 'admin'], function () {
        Route::get('/users', [AuthController::class, 'allUsers']);
        Route::get('/user/{id}', [AuthController::class, 'getUser']);
        Route::post('/user/update/{id}', [AuthController::class, 'updateUser']);
        Route::delete('/user/delete/{id}', [AuthController::class, 'deleteUser']);
        Route::group(['prefix' => 'skill'], function () {
            Route::post('/store', [SkillController::class, 'store']);
            Route::post('/update/{id}', [SkillController::class, 'update']);
            Route::delete('/delete/{id}', [SkillController::class, 'destroy']);
            Route::get('/all-skills', [SkillController::class, 'allSkills']);
            Route::get('/{id}', [SkillController::class, 'show']);
        });
    });

    Route::get('get-skill', [SkillController::class, 'index']);

    Route::middleware(['checkRole:jobseeker'])->group(function () {
        //job seeker routes
        // Route::get('login-with-token', [AuthController::class, "loginWithToken"]);
        Route::get('jobseeker/user-profile', [AuthController::class, 'userProfile']);
        Route::get('jobseeker/user-preference', [AuthController::class, 'userPreference']);
        Route::post('jobseeker/update-preference', [AuthController::class, 'updatePreference']);
        Route::post('jobseeker/deactivate-account', [AuthController::class, 'deactivateAccount']);
        Route::post('jobseeker/activate-account', [AuthController::class, 'activateAccount']);
        Route::post('jobseeker/change-password', [AuthController::class, 'changePassword']);

        Route::group(['prefix' => 'jobseeker'], function () {
            Route::apiResource('/resume', ResumeController::class);
            Route::get('/resume/show', [ResumeController::class, 'show']);
            Route::get('generate-resume', [ResumeController::class, 'generateResume']);
            Route::get('resume-stats', [ResumeController::class, 'resumeStats']);
            Route::apiResource('/application', ApplicationController::class);
            Route::delete('application/delete/{id}', [ApplicationController::class, 'destroy']);
            Route::post('application/update/{id}', [ApplicationController::class, 'update']);
            Route::get('application/show/{id}', [ApplicationController::class, 'show']);
            Route::post('/application/apply/{job_id}', [ApplicationController::class, 'apply']);
            Route::post('applications/{id}/restore', [ApplicationController::class, 'restore'])->name('application.restore');
            Route::post('upload-image', [AuthController::class, 'uploadImage']);
        });

        Route::prefix('activity')->group(function () {
            Route::get('save-job/{jobId}', [JobSeekerActivityController::class, 'saveJobs']);
            Route::get('view-job/{jobId}', [JobSeekerActivityController::class, 'viewJob']);
            Route::get('unsave-job/{jobId}', [JobSeekerActivityController::class, 'unsaveJobs']);
            Route::get('all-saved-job', [JobSeekerActivityController::class, 'allSavedJobs']);
            Route::get('recent-activity', [JobSeekerActivityController::class, 'recentActivity']);
        });

        Route::prefix('job-alert')->group(function () {
            Route::get('/', [JobAlertController::class, 'index']);
            Route::post('/', [JobAlertController::class, 'store']);
            Route::post('/update/{id}', [JobAlertController::class, 'update']);
            Route::post('/pause-alert/{id}', [JobAlertController::class, 'pauseAlert']);
            Route::delete('/delete/{id}', [JobAlertController::class, 'destroy']);
        });

        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/all-applications', [DashboardJobSeekerController::class, 'getTotalApplications']);
            Route::get('/jobseeker/recent-applications', [DashboardJobSeekerController::class, 'recentApplications']);
            Route::get('/recommended-jobs', [DashboardJobSeekerController::class, 'recommendedJobs']);
            Route::get('/application-stats', [DashboardJobSeekerController::class, 'applicationStats']);
            Route::get('profile-completion', [DashboardJobSeekerController::class, 'profileCompletion']);
        });
    });

    Route::middleware(['checkRole:employer'])->group(function () {
        Route::apiResource('/job', JobController::class);
        Route::get('/', [JobController::class, 'index']);
        Route::post('job/update/{id}', [JobController::class, 'update']);
        Route::post('job/update-status/{id}', [JobController::class, 'updateStatus']);
        Route::get('job/trash/jobs', [JobController::class, 'trash']);
        Route::post('job/restore/{id}', [JobController::class, 'restore']);
        Route::post('job/force-delete/{id}', [JobController::class, 'forceDelete']);
        Route::post('job/batch-restore', [JobController::class, 'batchRestore']);
        Route::post('job/batch-delete', [JobController::class, 'batchDelete']);
        Route::post('job/soft-delete/{id}', [JobController::class, 'delete']);
        Route::post('job/batch-force-delete', [JobController::class, 'batchForceDelete']);
        Route::get('job/listing-overview/all', [JobController::class, 'jobListingOverview']);

        Route::group(['prefix' => 'employer'], function () {
            Route::post('/', [EmployerController::class, 'store']);
            Route::get('/all-employers', [EmployerController::class, 'index']);
            Route::get('/{id}', [EmployerController::class, 'getEmployer']);
            Route::post('/update/{id}', [EmployerController::class, 'update']);
            Route::delete('/delete/{id}', [EmployerController::class, 'destroy']);
            Route::post('/update-application-status/{id}', [ApplicationController::class, 'updateApplicationStatus']);
            Route::post('/available-slug/{id}', [JobController::class, 'availableSlug']);
            Route::get('job/all-employer-job', [JobController::class, 'allEmployerJobs']);
            Route::get('job/applications', [EmployerApplicationController::class, 'index']);
            Route::get('job/application/{id}', [EmployerApplicationController::class, 'viewApplication']);
            Route::get('job/application-summary', [EmployerApplicationController::class, 'applicationSummary']);
            Route::get('download-document/{id}', [EmployerApplicationController::class, 'generateDocument']);
            Route::get('download-document-by-profile/{id}', [EmployerApplicationController::class, 'generateDocumentByProfile']);
            Route::post('update-settings', [AuthEmployerController::class, 'updateSettings']);
            Route::get('settings/all', [AuthEmployerController::class, 'employerSettings']);
            Route::post('2fa/update', [AuthEmployerController::class, 'employer2faUpdate']);


          
        });

          Route::prefix('analytics')->group(function () {
                // Main analytics endpoint
                Route::get('/', [AdvancedAnalyticsController::class, 'getAnalytics']);

                // Export analytics data
                Route::post('/export', [AdvancedAnalyticsController::class, 'exportAnalytics']);

                // Individual job analytics
                Route::get('/job/{jobId}', [AdvancedAnalyticsController::class, 'getJobAnalytics']);

                // Applicant funnel analytics
                Route::get('/funnel', [AdvancedAnalyticsController::class, 'getApplicantFunnel']);

                // Competitor analysis
                Route::get('/competitor', [AdvancedAnalyticsController::class, 'getCompetitorAnalysis']);

                // Real-time analytics
                Route::get('/realtime', [AdvancedAnalyticsController::class, 'getRealTimeAnalytics']);

                // Application quality metrics
                Route::get('/quality', [AdvancedAnalyticsController::class, 'getApplicationQualityMetrics']);

                // Hiring pipeline analytics
                Route::get('/pipeline', [AdvancedAnalyticsController::class, 'getHiringPipelineAnalytics']);

                // Clear cache
                Route::delete('/cache', [AdvancedAnalyticsController::class, 'clearAnalyticsCache']);
            });

        Route::prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index']);
            Route::post('/', [ApiKeyController::class, 'store']);
            Route::get('/permissions', [ApiKeyController::class, 'permissions']);
            Route::get('/statistics', [ApiKeyController::class, 'statistics']);
            Route::get('/usage-logs', [ApiKeyController::class, 'usageLogs']);
            Route::post('/test', [ApiKeyController::class, 'testKey']);

            Route::get('/{apiKey}', [ApiKeyController::class, 'show']);
            Route::put('/{apiKey}', [ApiKeyController::class, 'update']);
            Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy']);
            Route::post('/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate']);
            Route::post('/{apiKey}/toggle-status', [ApiKeyController::class, 'toggleStatus']);
        });

        // Add these routes to your existing api.php file



        Route::prefix('employer/calendar')->group(function () {
            Route::get('events', [CalendarEventController::class, 'index']);
            Route::post('events', [CalendarEventController::class, 'store']);
            Route::get('events/{event}', [CalendarEventController::class, 'show']);
            Route::POST('events/{event}', [CalendarEventController::class, 'update']);
            Route::delete('events/{event}', [CalendarEventController::class, 'destroy']);

            // Additional endpoints
            Route::get('statistics', [CalendarEventController::class, 'statistics']);
            Route::get('upcoming', [CalendarEventController::class, 'upcoming']);
            Route::get('export', [CalendarEventController::class, 'export']);
            Route::post('bulk-update-status', [CalendarEventController::class, 'bulkUpdateStatus']);
            Route::get('events-by-date', [CalendarEventController::class, 'getEventsByDate']);
        });



        Route::group(['prefix' => 'candidate'], function () {
            Route::get('/all-candidates', [CandidateController::class, 'index']);
            Route::get('/{id}', [CandidateController::class, 'candidateProfile']);
            Route::get('/stats/all', [CandidateController::class, 'candidateStats']);
        });

        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('/active-jobs-applications', [DashboardEmployerController::class, 'getActiveJobsApplications']);
            Route::get('/recent-applications', [DashboardEmployerController::class, 'getRecentApplicatins']);
            Route::get('/active-job-listing', [DashboardEmployerController::class, 'getActiveJobListing']);
            Route::get('jobseeker-profile-completion/{id}', [DashboardEmployerController::class, 'profileCompletion']);
            Route::get('application-trends', [DashboardEmployerController::class, 'applicationStats']);
            Route::get('popular-jobs', [DashboardEmployerController::class, 'popularJobs']);
            Route::get('hiring-statistics', [DashboardEmployerController::class, 'getHiringStats']);
        });

        Route::group(['prefix' => 'application-note'], function () {
            Route::get('/{applicationId}', [ApplicationNoteController::class, 'index']);
            Route::post('/{applicationId}', [ApplicationNoteController::class, 'store']);
            Route::delete('/{id}', [ApplicationNoteController::class, 'destroy']);
        });

        Route::group(['prefix' => 'dashboard'], function () {});

        Route::get('/resume', [ResumeController::class, 'index']);

        Route::prefix('activity')->group(function () {
            Route::get('view-jobseeker/{jobseekerId}', [EmployerActivityController::class, 'viewJobSeekerProfile']);
        });

        Route::group(['prefix' => 'application-interview'], function () {
            Route::post('/add-interview-type', [ApplicationInterviewController::class, 'addInterviewType']);
            Route::post('/add-interviewers', [ApplicationInterviewController::class, 'addInterviewers']);
            Route::post('/schedule-interview', [ApplicationInterviewController::class, 'scheduleInterview']);
            Route::get('/interview-types', [ApplicationInterviewController::class, 'getInterviewType']);
            Route::get('/interviewers', [ApplicationInterviewController::class, 'getInterviewers']);
            Route::post('/withdraw-interview', [ApplicationInterviewController::class, 'withdrawInterview']);
        });
    });
});

// Chat routes
Route::middleware('multiAuth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Broadcast::routes(['middleware' => ['multiAuth']]);
    Route::get('/chats', [ChatController::class, 'getChats']);
    Route::post('/chats', [ChatController::class, 'getOrCreateChat']);
    Route::get('/chats/{chatId}/messages', [ChatController::class, 'getMessages']);
    Route::post('/messages', [ChatController::class, 'sendMessage']);
    Route::post('/messages/read', [ChatController::class, 'markAsRead']);
    Route::get('/chats/previews', [ChatController::class, 'getChatPreviews']);
});

// Route::get('/send-test-email', function () {
//     Mail::to('subodhacharya21@gmail.com')->send(new SendEmailVerificationMail());
//     return 'Email sent!';
// });

//jobseeker employer
Route::middleware('multiAuth')->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', 'App\Http\Controllers\API\NotificationController@index');
        Route::get('/unread', 'App\Http\Controllers\API\NotificationController@unread');
        Route::post('/{id}/read', 'App\Http\Controllers\API\NotificationController@markAsRead');
        Route::post('/read-all', 'App\Http\Controllers\API\NotificationController@markAllAsRead');
        Route::delete('/{id}', 'App\Http\Controllers\API\NotificationController@destroy');
    });
});

Route::post('/run-migrations', [MigrationController::class, 'runMigrations']);

//test api
Route::get('/test', function () {
    return 'testing';
});

Route::middleware('auth:api')->group(function () {
    Route::post('/2fa/generate', [TwoFactorController::class, 'generateSession'])->middleware('checkRole:employer');
});
Route::post('/2fa/verify', [TwoFactorController::class, 'verifyCode'])->middleware('checkRole:employer');

Route::post('/verify-2fa/{token}', [TwoFactorController::class, 'verifyCode']);

Route::get('/all-companies', [EmployerController::class, 'getAllEmployers']);

Route::get('/all-industries', [EmployerController::class, 'getEmployersByIndustry']);

Route::prefix('admin')->group(function () {
    Route::get('/jobs', [AllJobController::class, 'index'])->middleware('checkRole:admin');
    Route::get('/employers', [AllEmployerController::class, 'index'])->middleware('checkRole:admin');
    Route::get('/job-seekers', [AllJobSeekerController::class, 'index'])->middleware('checkRole:admin');
    Route::get('/all-users', [AllUserController::class, 'index'])->middleware('checkRole:admin');
    Route::get('/all-applications', [AllApplicationController::class, 'index'])->middleware('checkRole:admin');
    Route::post('/blog/store', [BlogController::class, 'store'])->middleware('checkRole:admin');
    Route::post('/blog-category/store', [BlogController::class, 'storeCategory'])->middleware('checkRole:admin');
    Route::get('/all-blogs', [BlogController::class, 'allBlogs'])->middleware('checkRole:admin');
    Route::get('/blog-categories', [BlogController::class, 'getBlogCategory'])->middleware('checkRole:admin');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'impersonate'])->middleware('checkRole:admin');
    Route::post('/leave-impersonate', [ImpersonationController::class, 'leaveImpersonation'])->middleware('checkRole:admin');
    Route::get('/top-stats', [DashboardController::class, 'topStats'])->middleware('checkRole:admin');
    Route::get('/recent-list', [DashboardController::class, 'recentList'])->middleware('checkRole:admin');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::get('/login-with-token', [AdminAuthController::class, 'loginWithToken']);
    Route::post('/import-jobs', [JobController::class, 'importLinkedInJobs']);
    Route::post('/bulk-delete-jobs', [AllJobController::class, 'bulkDeleteJobs']);
    Route::get('/jobseekers-growth', [DashboardController::class, 'jobseekersGrowth'])->middleware('checkRole:admin');
    Route::get('/jobs-by-month', [DashboardController::class, 'jobsByMonth'])->middleware('checkRole:admin');
    Route::get('/application-trends', [DashboardController::class, 'applicatinTrends'])->middleware('checkRole:admin');
    Route::get('/jobs-by-category', [DashboardController::class, 'jobByCategory'])->middleware('checkRole:admin');
    Route::get('/application-by-status', [DashboardController::class, 'applicationByStatus'])->middleware('checkRole:admin');
    Route::post('logout', [AuthController::class, 'logout']);
});



Route::middleware(['apiKey', 'apiRateLimit:100,1'])->prefix('v1/employer')->group(function () {
    // Jobs endpoints
    Route::middleware('apiKey:read:jobs')->group(function () {
        Route::get('/jobs', [JobController::class, 'allEmployerJobs']);
        Route::get('/jobs/{slug}', [JobController::class, 'jobDescription']);
    });

    Route::middleware('apiKey:write:jobs')->group(function () {
        Route::post('/jobs', [JobController::class, 'store']);
        Route::put('/jobs/{job}', [JobController::class, 'update']);
        Route::delete('/jobs/{job}', [JobController::class, 'destroy']);
    });

    // Applications endpoints
    Route::middleware('apiKey:read:applications')->group(function () {
        Route::get('/applications', [EmployerApplicationController::class, 'index']);
        Route::get('/job-applications', [EmployerApplicationController::class, 'index']);
    });

    Route::middleware('apiKey:write:applications')->group(function () {
        Route::put('/applications/{application}/status', [ApplicationController::class, 'updateApplicationStatus']);
    });

    // // Analytics endpoints
    // Route::middleware('apiKey:read:analytics')->group(function () {
    //     Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    //     Route::get('/analytics/jobs/{job}', [AnalyticsController::class, 'jobAnalytics']);
    // });
});



// Resume search routes for employers
Route::middleware(['checkRole:employer'])->prefix('advance')->group(function () {
    Route::get('/resume-search', [ResumeSearchController::class, 'search']);
    Route::get('/resume-search/filters', [ResumeSearchController::class, 'getSearchFilters']);
    Route::get('/resume-search/skills', [ResumeSearchController::class, 'getPopularSkills']);
    Route::get('/resume-search/locations', [ResumeSearchController::class, 'getPopularLocations']);
    Route::post('/resume-search/save-candidate', [ResumeSearchController::class, 'saveCandidate']);
    Route::post('/resume-search/unsave-candidate', [ResumeSearchController::class, 'unsaveCandidate']);
    Route::get('/resume-search/saved-candidates', [ResumeSearchController::class, 'getSavedCandidates']);
    Route::get('/resume-search/{jobseekerId}', [ResumeSearchController::class, 'getCandidateProfile']);
    Route::post('/resume-search/update-notes', [ResumeSearchController::class, 'updateCandidateNotes']);
});
