<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Response;
use App\Http\Controllers\Api\APIController;
use App\Models\TutorQA;
use App\Models\User;
use App\Notifications\CommentQAParent;
use DB;

class TutorQAsApiController extends APIController {

    function create_tutor_question(Request $request) {
        $tutor_id = trim($request->tutor_id);
        $student_or_tutor_id = trim($request->student_or_tutor_id);
        $question = trim($request->question);
        $tutor_role_id = 3;

        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.',
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
        $tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();
        $student_or_tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_or_tutor_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();

        if (count($tutor_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Tutor not found.',
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
            $tutor_qa = new TutorQA;
            $data = array(
                'tutor_id' => $tutor_id,
                'creator_id' => $student_or_tutor_id,
                'content' => $question,
                'parent_id' => NULL
            );
            $tutor_qa->create($data);
            $tutor_qa_id = $tutor_qa->id;
            DB::commit();
            $getTutor = User::find($tutor_id);
            $getStudent = User::find($student_or_tutor_id);
            $getTutor->notify(new CommentQAParent($getTutor, $getStudent));

            $response = array(
                'status' => 'success',
                'message' => 'Thank You your question is submitted to tutor.'
            );
            return $this->respondWithStatus($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'status' => 'failed',
                'message' => 'Sorry error.Your question is not submitted to tutor.'
            );
            return $this->respondWithStatus($response);
        }
    }

    public function update_tutor_question(Request $request) {
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
        $question_detail = DB::table('tutor_q_as')
                ->select('tutor_q_as.*')
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
            $update_question_data = DB::table('tutor_q_as')
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

    public function post_tutor_question_reply(Request $request) {
        
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
        $question_detail = DB::table('tutor_q_as')
                ->select('tutor_q_as.*')
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
            $tutor_qa_reply = new TutorQA;
            $data = array(
                'tutor_id' => $question_detail->tutor_id,
                'creator_id' => $student_or_tutor_info->id,
                'content' => $reply,
                'parent_id' => $question_id
            );
            $tutor_qa_reply->create($data);
            $tutor_qa_reply_id = $tutor_qa_reply->id;

            if ($tutor_qa_reply) {
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

?>