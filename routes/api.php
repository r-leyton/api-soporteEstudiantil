<?php

use App\Http\Controllers\AcademicReportController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\TutoringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => '¡API funcionando correctamente!',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// Rutas públicas (solo lectura)
Route::get('/forums', [ForumController::class, 'index']);
Route::get('/forums/{forum}', [ForumController::class, 'show']);
Route::get('/forums/{forumId}/threads', [ThreadController::class, 'indexByForum']);
Route::get('/threads/{thread}', [ThreadController::class, 'show']);
Route::get('/threads/{threadId}/comments', [CommentController::class, 'indexByThread']);
Route::get('/threads/{threadId}/votes', [VoteController::class, 'getThreadVotes']);
Route::get('/comments/{commentId}/votes', [VoteController::class, 'getCommentVotes']);

// Rutas protegidas (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Forums (crear, editar, eliminar)
    Route::post('/forums', [ForumController::class, 'store']);
    Route::put('/forums/{forum}', [ForumController::class, 'update']);
    Route::delete('/forums/{forum}', [ForumController::class, 'destroy']);

    // Threads (crear, editar, eliminar)
    Route::post('/forums/{forumId}/threads', [ThreadController::class, 'store']);
    Route::put('/threads/{thread}', [ThreadController::class, 'update']);
    Route::delete('/threads/{thread}', [ThreadController::class, 'destroy']);
    Route::get('/users/{userId}/threads', [ThreadController::class, 'indexByUser']);

    // Comments (crear, editar, eliminar)
    Route::post('/threads/{threadId}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::get('/users/{userId}/comments', [CommentController::class, 'indexByUser']);

    // Votes (crear/toggle)
    Route::post('/threads/{threadId}/votes', [VoteController::class, 'voteThread']);
    Route::post('/comments/{commentId}/votes', [VoteController::class, 'voteComment']);

    // ===== TUTORÍAS =====
    Route::prefix('tutoring')->group(function () {
        // Teacher routes
        Route::get('/requests', [TutoringController::class, 'getTeacherRequests']);
        Route::post('/requests/{id}/accept', [TutoringController::class, 'acceptRequest']);
        Route::post('/requests/{id}/reject', [TutoringController::class, 'rejectRequest']);
        Route::post('/requests/{id}/mark-attendance', [TutoringController::class, 'markAttendance']);
        Route::get('/history', [TutoringController::class, 'getTeacherHistory']);

        // Student routes
        Route::post('/requests', [TutoringController::class, 'createRequest']);
        Route::get('/my-requests', [TutoringController::class, 'getStudentRequests']);
        Route::get('/teachers', [TutoringController::class, 'getAvailableTeachers']);

        // Shared
        Route::get('/availabilities/{teacherId}', [TutoringController::class, 'getTeacherAvailability']);
    });
    
});
Route::prefix('report')->group(function () {
        Route::post('/reports/enrolled-courses', [AcademicReportController::class, 'enrolledCoursesReport']);
        Route::post('/reports/single-course-grades', [AcademicReportController::class, 'singleCourseGradesReport']);
        Route::post('/reports/academic-summary', [AcademicReportController::class, 'studentAcademicSummary']);

        Route::get('/student-groups', [AcademicReportController::class, 'getStudentGroups']);
        Route::get('/group-grades', [AcademicReportController::class, 'getGroupGrades']);
        Route::get('/academic-summary-info', [AcademicReportController::class, 'getAcademicSummary']);
    });
