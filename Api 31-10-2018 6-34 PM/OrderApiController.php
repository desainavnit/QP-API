<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Models\CourseLesson;
use App\Notifications\StudentCancelPaymentCourse;
use App\Notifications\StudentPaymentCourse;
use App\Notifications\TutorConfirmationCourse;
use DB;

class OrderApiController extends APIController {

    protected $order, $course;

    public function __construct(Order $order, Course $course) {
        $this->order = $order;
        $this->course = $course;
    }

    public function course_order(Request $request) {
        $course_id = $request->course_id;
        $student_id = $request->student_id;
        if ($course_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide course id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        $student_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug')
                ->where('users.id', '=', $student_id)
                ->where('role_users.role_id', '=', 2)
                ->where('users.deleted_at', '=', NULL)
                ->first();
        if (empty($student_info)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.'
            );
            return $this->respondWithStatus($response);
        }





        // dd($courseLesson);
        DB::beginTransaction();
        try {
            $course = $this->course->find($course_id);
            $courseLesson = CourseLesson::where('course_id', $course_id)->orderBy('start_on', 'desc')->first();
            if ($course) {
                $tutor = User::find($course->user_id);
                $courseInfo = $course->title . '---' . $course->sub_title . '---' . $course->price . '---' . $course->duration . '---' . $course->seats_available . '---' . $course->created_by;
                $studentInfo = $student_info->email . '---' . $student_info->first_name . '---' . $student_info->last_name;
                $tutorInfo = $tutor->email . '---' . $tutor->first_name . '---' . $tutor->last_name;

                $scheduled_date = null;
                if ($courseLesson) {
                    $scheduled_date = $courseLesson->start_on;
                } else {
                    $scheduled_date = null;
                }


                if ($course->price <= 0) {
                    $order = new Order;
                    $data = [
                        'order_id' => $this->genOrderCode($course),
                        'course_id' => $course_id,
                        'student_id' => $student_id,
                        'student_info' => $studentInfo,
                        'tutor_info' => $tutorInfo,
                        'course_info' => $courseInfo,
                        'trans_type' => 0,
                        'scheduled_date' => $scheduled_date,
                        'status' => 1
                    ];
                    $messageOrder = 'This course is free, please check your Dashboard.';
                } else {

                    $order = new Order;
                    $data = [
                        'order_id' => $this->genOrderCode($course),
                        'course_id' => $course_id,
                        'student_id' => $student_id,
                        'student_info' => $studentInfo,
                        'tutor_info' => $tutorInfo,
                        'course_info' => $courseInfo,
                        'trans_type' => 0,
                        'scheduled_date' => $scheduled_date,
                        'status' => 0
                    ];
                }

                $order->create($data);
                DB::commit();
                if ($course->price <= 0) {
                    $this->CourseNotifemail($student_id);
                }
                $response = array(
                    'status' => 'failed',
                    'message' => 'Order has been created. Please continue to PayPal for your payment'
                );
                return $this->respondWithStatus($response);
            } else {
                DB::rollback();
                $response = array(
                    'status' => 'failed',
                    'message' => 'Course Not Found.'
                );
                return $this->respondWithStatus($response);
            }
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'status' => 'failed',
                'message' => 'Course order error.'
            );
            return $this->respondWithStatus($response);
        }
    }

    protected function genOrderCode($course, $length = 15) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = substr(implode('0', explode('-', $course->slug)), 0, 5);
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /*
      Send Email Course
     */

    public function CourseNotifemail($student_id) {
        $userStudent = User::where('id', $student_id)->first();
        $orders = $this->getNewOrder($student_id)->get();
        $url = url('student/dashboard');
        $urlTutor = url('tutor/private-tuition');
        $userStudent->notify(new StudentPaymentCourse($userStudent, $orders, $url));
        foreach ($orders as $key => $value) {
            $tutor_info = explode("---", $value->tutor_info);
            $Emailtutor = $tutor_info[0];
            $tutor = User::where('email', $Emailtutor)->first();
            $tutor->notify(new TutorConfirmationCourse($tutor, $value, $urlTutor));
        }
    }

    protected function getNewOrder($student_id) {
        $order = Order::where('student_id', $student_id)->where('status', '=', 1)
                        ->join('courses', 'orders.course_id', '=', 'courses.id')->wherenull('courses.deleted_at')->orderBy('orders.created_at', 'DESC')->limit(1);
        return $order;
    }

}
