<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Response;
use App\Http\Controllers\Api\APIController;
use App\Models\CourseQA;
use DB;

class CourseQAsApiController extends APIController {

    protected $course_qa;

    public function __construct(CourseQA $course_qa) {
        $this->course_qa = $course_qa;
    }

    public function create_course_question(Request $request) {
        $course_id = trim($request->course_id);
        $student_or_tutor_id = trim($request->student_or_tutor_id);
        $question = trim($request->question);
        
        if ($course_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide course id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($student_or_tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student or tutor id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($question == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please enter question.',
            );
            return $this->respondWithStatus($response);
        }

        $course_detail = DB::table('courses as c')
                ->select('c.id', 'c.title')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->first();
        $student_or_tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_or_tutor_id)                
                ->where('users.deleted_at', '=', NULL)
                ->first();

        if (count($course_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
            );
            return $this->respondWithStatus($response);
        }

        if (count($student_or_tutor_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student or tutor not found.',
            );
            return $this->respondWithStatus($response);
        }

        DB::beginTransaction();
        try {
            $course_qa = new CourseQA;
            $data = array(
                'user_id' => $student_or_tutor_id, 
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
        $question = trim($request->question);
        $question_id = trim($request->question_id);
         

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
                'message' => 'Please enter question.',
            );
            return $this->respondWithStatus($response);
        }
        $question_detail = DB::table('course_q_as')
                ->select('course_q_as.*')
                ->where('id', '=', $question_id)
                ->where('parent_id', '=', NULL)
                ->first();


        if (count($question_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Question not found.',
            );
            return $this->respondWithStatus($response);
        } else {
            $update_question_data = DB::table('course_q_as')
                    ->where('id', $question_id)
                    ->update([
                'content' => $question,
                'updated_at' => date("Y-m-d H:i:s")
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

    public function post_course_question_reply(Request $request) {
        $student_or_tutor_id = trim($request->student_or_tutor_id);
        $reply = trim($request->reply);
        $question_id = trim($request->question_id);


        if ($student_or_tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student or tutor id.',
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
        if ($reply == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please enter reply.',
            );
            return $this->respondWithStatus($response);
        }
        $question_detail = DB::table('course_q_as')
                ->select('course_q_as.*')
                ->where('id', '=', $question_id)
                ->where('parent_id', '=', NULL)
                ->first();


        $student_or_tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_or_tutor_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();



        if (count($student_or_tutor_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student or tutor not found.',
            );
            return $this->respondWithStatus($response);
        }

        if (count($question_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Question not found.',
            );
            return $this->respondWithStatus($response);
        } else {
            $course_qa_reply = new CourseQA;
            $data = array(
                'user_id' => $student_or_tutor_info->id,
                'course_id' => $question_detail->course_id,
                'content' => $reply,
                'parent_id' => $question_id
            );
            $course_qa_reply->create($data);
            $course_qa_reply_id = $course_qa_reply->id;

            if ($course_qa_reply) {
                $response = array(
                    'status' => 'success',
                    'message' => 'Thank You your reply is successfully submitted.'
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Sorry error.your reply is not submitted.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

   

}
