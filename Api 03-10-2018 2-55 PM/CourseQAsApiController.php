<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Response;
use App\Http\Controllers\Api\APIController;
use App\Models\CourseQA;
use DB;

//use Sentinel;

class CourseQAsApiController extends APIController {

    protected $course_qa;

    public function __construct(CourseQA $course_qa) {
        $this->course_qa = $course_qa;
    }

    public function create_course_question(Request $request) {
        $course_id = trim($request->course_id);
        $student_id = trim($request->student_id);
        $question = trim($request->question);
        $student_role_id = 2;
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
        if ($question == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide question.',
            );
            return $this->respondWithStatus($response);
        }

        $course_detail = DB::table('courses as c')
                ->select('c.id', 'c.title')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->first();
        $student_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_id)
                ->where('role_users.role_id', '=', $student_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();

        if (count($course_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
            );
            return $this->respondWithStatus($response);
        }

        if (count($student_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.',
            );
            return $this->respondWithStatus($response);
        }

        DB::beginTransaction();
        try {
            $course_qa = new CourseQA;
            $data = array(
                'user_id' => $student_id,
                'course_id' => $course_id,
                'content' => $question,
                'parent_id' => NULL
            );
            $course_qa->create($data);
            $course_qa_id = $course_qa->id;
            DB::commit();
            $response = array(
                'status' => 'success',
                'message' => 'Thank You your question is submitted to course tutor.'
            );
            return $this->respondWithStatus($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'status' => 'failed',
                'message' => 'Sorry error.Your question is not submitted to course tutor.'
            );
            return $this->respondWithStatus($response);
        }
    }

    public function update_course_question(Request $request) {
        $course_id = trim($request->course_id);
        $student_id = trim($request->student_id);
        $question = trim($request->question);
        $question_id = trim($request->question_id);
        $student_role_id = 2;
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
        if ($question_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide question id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($question == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide question.',
            );
            return $this->respondWithStatus($response);
        }
        $question_detail = DB::table('course_q_as')
                ->select('course_q_as.*')
                ->where('id', '=', $question_id)
                ->where('parent_id', '=', NULL)
                ->first();

        $course_detail = DB::table('courses as c')
                ->select('c.id', 'c.title')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->first();
        $student_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_id)
                ->where('role_users.role_id', '=', $student_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();

        if (count($course_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
            );
            return $this->respondWithStatus($response);
        }

        if (count($student_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.',
            );
            return $this->respondWithStatus($response);
        }

        if (count($question_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Question not found.',
            );
            return $this->respondWithStatus($response);
        }
        $update_question_data = DB::table('course_q_as')
                ->where('id', $question_id)
                ->update([
            'content' => $question,
            'updated_at'=>date("Y-m-d H:i:s")        
        ]);      


        if ($update_question_data) {
            $response = array(
                'status' => 'success',
                'message' => 'Thank You your question is updated successfully.'
            );
            return $this->respondWithStatus($response);
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Sorry error.your question is not updated.'
            );
            return $this->respondWithStatus($response);
        }
    }

}
