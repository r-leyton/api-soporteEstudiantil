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
        'message' => '¡API funcionando correctamente!:)',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// ===== FOROS =====
// Rutas públicas (solo lectura)
Route::get('/forums', [ForumController::class, 'index']);
Route::get('/forums/{forum}', [ForumController::class, 'show']);
Route::get('/forums/{forumId}/threads', [ThreadController::class, 'indexByForum']);
Route::get('/threads/{thread}', [ThreadController::class, 'show']);
Route::get('/threads/{threadId}/comments', [CommentController::class, 'indexByThread']);
Route::get('/threads/{threadId}/votes', [VoteController::class, 'getThreadVotes']);
Route::get('/comments/{commentId}/votes', [VoteController::class, 'getCommentVotes']);

// Rutas de escritura
Route::post('/forums', [ForumController::class, 'store']);
Route::put('/forums/{forum}', [ForumController::class, 'update']);
Route::delete('/forums/{forum}', [ForumController::class, 'destroy']);

Route::post('/forums/{forumId}/threads', [ThreadController::class, 'store']);
Route::put('/threads/{thread}', [ThreadController::class, 'update']);
Route::delete('/threads/{thread}', [ThreadController::class, 'destroy']);
Route::get('/users/{userId}/threads', [ThreadController::class, 'indexByUser']);

Route::post('/threads/{threadId}/comments', [CommentController::class, 'store']);
Route::put('/comments/{comment}', [CommentController::class, 'update']);
Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
Route::get('/users/{userId}/comments', [CommentController::class, 'indexByUser']);

Route::post('/threads/{threadId}/votes', [VoteController::class, 'voteThread']);
Route::post('/comments/{commentId}/votes', [VoteController::class, 'voteComment']);

// ===== TUTORÍAS =====
// Teacher routes
Route::get('/tutoring/requests', [TutoringController::class, 'getTeacherRequests']);
Route::post('/tutoring/requests/{id}/accept', [TutoringController::class, 'acceptRequest']);
Route::post('/tutoring/requests/{id}/reject', [TutoringController::class, 'rejectRequest']);
Route::post('/tutoring/requests/{id}/mark-attendance', [TutoringController::class, 'markAttendance']);
Route::get('/tutoring/history', [TutoringController::class, 'getTeacherHistory']);

// Teacher availability management
Route::get('/tutoring/my-availability', [TutoringController::class, 'getMyAvailability']);
Route::post('/tutoring/availability', [TutoringController::class, 'createAvailability']);
Route::delete('/tutoring/availability/{id}', [TutoringController::class, 'deleteAvailability']);

// Shared
Route::get('/availabilities/{teacherId}', [TutoringController::class, 'getTeacherAvailability']);

Route::prefix('report')->group(function () {
    Route::post('/reports/enrolled-courses', [AcademicReportController::class, 'enrolledCoursesReport']); 
    Route::post('/reports/single-course-grades', [AcademicReportController::class, 'singleCourseGradesReport']); 
    Route::post('/reports/academic-summary', [AcademicReportController::class, 'studentAcademicSummary']);

    Route::get('/student-groups', [AcademicReportController::class, 'getStudentGroups']);
    Route::get('/group-grades', [AcademicReportController::class, 'getGroupGrades']);
    Route::get('/academic-summary-info', [AcademicReportController::class, 'getAcademicSummary']);
});
// Student routes
Route::post('/tutoring/requests', [TutoringController::class, 'createRequest']);
Route::get('/tutoring/my-requests', [TutoringController::class, 'getStudentRequests']);
Route::get('/tutoring/teachers', [TutoringController::class, 'getAvailableTeachers']);

// Shared
Route::get('/tutoring/availabilities/{teacherId}', [TutoringController::class, 'getTeacherAvailability']);
