<?php

use App\Models\Content;
use App\Models\Admin\Role;
use App\Models\Learning\Batch;
use App\Models\Learning\Course;
use App\Models\Student\Student;
use App\Http\Controllers\Api\V1;
use App\Models\Student\Enrollment;
use Illuminate\Support\Facades\Route;
use App\Models\Learning\CourseSubject;
use App\Http\Controllers\TestController;
use App\Models\Student\CourseSubjectStudent;
use App\Http\Controllers\Api\V1\Exam\ExamController;
use App\Http\Controllers\Api\V1\StudentActivityController;
use App\Http\Controllers\Api\V1\StudentAssignmentController;
use App\Http\Controllers\Api\V1\StudentLessonPlanController;
use App\Http\Controllers\Api\V1\TeacherAssignmentController;

Route::post('login',                                    [V1\LoginController::class, 'login']);
Route::post('forgot-password',                          [V1\ResetPasswordController::class, 'forgotPassword']);
Route::post('check-otp',                                [V1\ResetPasswordController::class, 'OtpCheck']);
Route::put('reset-password',                            [V1\ResetPasswordController::class, 'resetPassword']);
Route::get('applink',                                   V1\ApplicationLinkController::class);
Route::get('cms',                                       V1\CMS\CMSController::class);


Route::middleware(['auth:sanctum'])->group(function (){

    Route::get('/announcements', [V1\AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcementId}', [V1\AnnouncementController::class, 'show']);
    Route::post('/announcements/read', [V1\AnnouncementController::class, 'read']);

    Route::post('password-reset',                       V1\FirstTimeLoginController::class);

    Route::get('profile',                               [V1\ProfileController::class, 'show']);
    Route::post('profile',                              [V1\ProfileController::class, 'edit']);
    Route::post('logout',                               V1\LogoutController::class);

    Route::get('/notifications',                        [V1\NotificationController::class, 'getNoti']);
    Route::get('/mark-all-as-read',                     [V1\NotificationController::class, 'markAllRead']);
    Route::get('/notifications-check-unread',           [V1\NotificationController::class, 'checkUnread']);
    Route::get('/fetch-offline-notifications',          [V1\NotificationController::class, 'fetchOfflineNotifications']);

    Route::get('/chat-rooms',                           [V1\ChatApiController::class, 'getChatRoomList']);
    Route::get('/chat-rooms/{id}',                      [V1\ChatApiController::class, 'getChatRoomDetail']);
    Route::get('/chat-room-members',                    [V1\ChatApiController::class, 'getChatRoomMembers']);
    Route::get('/chat-messages',                        [V1\ChatApiController::class, 'getMessages']);
    Route::post('/send-message',                        [V1\ChatApiController::class, 'sendMessage']);
    Route::get('/user-connection-token',                [V1\ChatApiController::class, 'genConnectionToken']);
    Route::get('/user-subscription-token',              [V1\ChatApiController::class, 'genSubscriptionToken']);
    Route::get('/delete-message/{id}',                  [V1\ChatApiController::class, 'deleteChatMessage']);
    Route::get('/get-metas',                            [V1\ChatApiController::class, 'fetchDOMMetaData']);

    Route::post('/emit-user-change',                    [V1\ChatApiController::class, 'emitUserChange']);

    Route::post('/{content}/delete', function(Content $content) {
        app()
            ->make(\App\Services\StorageService\Service::class)::deleteStorageHelper($content);

        delete_file($content->content);

        $content->delete();

        return response()->json([],204);
    });
});

Route::get('/exam/{batch}/result-board',   [ExamController::class, 'examBoard']);
Route::get('/exam/{student}/exam-courses', [ExamController::class, 'examCourses']);

Route::controller(TestController::class)
    ->middleware(['auth:sanctum'])
    ->group(function() {
        Route::get('/{batch}/{student}/timetables-format', 'timetablesFormat');
        Route::get('/{overallFormat}/{student}/overall/detail', 'overallDetail');
        Route::post('/{student}/leave-apply', 'leaveApply');
    });

Route::get('/{student}/student/batches', function(Student $student) {

    $courseIdArr = Enrollment::where('student_id', $student->id)->get()->groupBy('course_id')->keys();
    $batchIdArr  = Enrollment::where('student_id', $student->id)->get()->groupBy('batch_id')->keys();
    $courses     = Course::whereIn('id', $courseIdArr)->with('subjects')->get();
    $batches     = Batch::whereIn('id', $batchIdArr)->get();
    $data['subjects'] = collect([]);

    $data['courses'] = $courses->map(
        function($course) use(&$data, $student) {
            $course
                ->subjects
                ->map(function($subject) use(&$data, $course, $student) {

                    if($course->is_custom) {
                        $courseSubject = CourseSubject::where([
                            'course_id'  => $course->id,
                            'subject_id' => $subject->id
                        ])->first();

                        $enrollment    = Enrollment::where([
                            'student_id' => $student->id,
                            'course_id'  => $course->id,
                        ])->first();

                        $record        = CourseSubjectStudent::where([
                            'course_subject_id' => $courseSubject->id,
                            'enrollment_id'     => $enrollment->id
                        ])->first();

                        $status = isset($record);

                    } else {
                        $status = true;
                    }

                    if($status) {
                        $data['subjects']->push([
                            'name'      => $subject->name,
                            'id'        => $subject->id,
                            'course_id' => $course->id
                        ]);
                    }

                });

            return [
                'id'   => $course->id,
                'name' => $course->name,
            ];
        }
    );

    $data['batches'] = $batches->map(function($batch) {
        return [
            'id'        => $batch->id,
            'name'      => $batch->name,
            'course_id' => $batch->course_id
        ];
    });

    return response()->json($data, 200);
})
->middleware(['auth:sanctum']);

Route::controller(StudentActivityController::class)
    ->middleware(['auth:sanctum'])
    ->group(function() {
        Route::get('/{student}/{batch}/activity-calendar', 'activityCalendar');
        Route::get('/{student}/{batch}/attendances', 'getAttendances');
        Route::get('/{student}/{batch}/attendance-percent', 'attendancePercent');
    });

Route::controller(StudentAssignmentController::class)
    ->middleware(['auth:sanctum'])
    ->group(function() {
        Route::get('/student/{student}/assignments', 'index');
        Route::get('/student/{student}/tasks', 'taskIndex');
    });

Route::controller(StudentLessonPlanController::class)
    ->group(function() {
        Route::get('/student/{student}/lessonPlans', 'index');
        Route::get('/lessonPlans/{lessonPlan}', 'show');
    });

Route::controller(TeacherAssignmentController::class)
    ->group(function() {
        Route::get('/teacher/assignment/{assignment}/students', 'assignmentStudents');
        Route::get('/teacher/task/{task}/students', 'taskStudents');
        Route::post('/teacher/assignment/{assignment}/{student}/finish', 'finishAssignment');
        Route::post('/teacher/task/{task}/{student}/finish', 'finishTask');
        Route::post('/teacher/assignment/{submission}/cancel', 'cancelSubmission');
        Route::post('/teacher/task/{taskSubmission}/cancel', 'cancelTaskSubmission');
    });
