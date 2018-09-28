<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\User;
use Response;
use App\Models\RoleUser;
use DB;

class StudentProfileApiController extends APIController {

    function isValidDate($date) {
        if (date('Y-m-d', strtotime($date)) === $date) {
            return 'true';
        } else {
            return "false";
        }
    }

    function get_student_calendar(Request $request) {
        $student_id = trim($request->student_id);
        $student_role_id = 2;
        $month_start_date = $request->month_start_date;
        $month_end_date = $request->month_end_date;
        $month_start_date = "";
        $month_end_date = "";
        if ($month_start_date == "") {
            $month_start_date = date('Y-m-01');
        }
        if ($month_end_date == "") {
            $month_end_date = date('Y-m-d');
        }

        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id. '
            );
            return $this->respondWithStatus($response);
        } else if ($month_start_date == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide month start date.'
            );
            return $this->respondWithStatus($response);
        } else if ($month_start_date != "" && $this->isValidDate($month_start_date) === "false") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please select valid month start date.'
            );
            return $this->respondWithStatus($response);
        } else if ($month_end_date == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide month end date.'
            );
            return $this->respondWithStatus($response);
        } else if ($month_end_date != "" && $this->isValidDate($month_end_date) === "false") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please select valid month end date.'
            );
            return $this->respondWithStatus($response);
        } else {
            $student_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $student_id)
                    ->where('role_users.role_id', '=', $student_role_id)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            if (!empty($student_info)) {
                $private_tution = DB::table('private_tuitions as pt')
//                        ->join("role_users", "pt.user_id", '=', 'role_users.user_id')
                        ->join("users as stud", "pt.user_id", '=', 'stud.id')
                        ->join("users as tutor", "pt.tutor_id", '=', 'tutor.id')
                        ->select('pt.*', 'stud.id as student_id', 'stud.first_name as student_fname', 'stud.last_name as student_lname', 'tutor.id as tutor_id', 'tutor.first_name as tutor_fname', 'tutor.first_name as tutor_lname')
                        ->where('pt.user_id', '=', $student_id)
                        ->where('pt.start_date', '>=', date("Y-m-d", strtotime($month_start_date)))
                        ->where('pt.start_date', '<=', date("Y-m-d", strtotime($month_end_date)))
//                        ->where('role_users.role_id', '=', $student_role_id)
                        ->orderBy('pt.start_date', 'DESC')
                        ->get();
                
                $private_tution_array = array();
                foreach ($private_tution as $pt_row) {
                    $status = "";
                    if ($pt_row->status == 1) {
                        $status = "Approve";
                    } else if ($pt_row->status == 3) {
                        $status = "Reschedule date";
                    } else if ($pt_row->status == 2) {
                        $status = "Reject";
                    } else {
                        $status = "Waiting";
                    }
                    $private_tution_array[] = array(
                        'id' => $pt_row->id,
                        'date' => date("Y-m-d", strtotime($pt_row->start_date)),
                        'title' => $pt_row->title,
                        'status' => $status,
                        'student_id' => $pt_row->student_id,
                        'student_name' => $pt_row->student_fname . " " . $pt_row->student_lname,
                        'tutor_id' => $pt_row->tutor_id,
                        'tutor_name' => $pt_row->tutor_fname . " " . $pt_row->tutor_lname
                    );
                }


                if (!empty($private_tution_array)) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Private tution data found.',
                        'private_tution_array' => $private_tution_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Private tution data not found.',
                        'private_tution_array' => $private_tution_array
                    );
                    return $this->respondWithStatus($response);
                }
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Student not found.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    function get_student_profile(Request $request) {
        $student_id = trim($request->student_id);
        $student_role_id = 2;
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id. '
            );
            return $this->respondWithStatus($response);
        } else {
            $student_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.image_profile')
                    ->where('users.id', '=', $student_id)
                    ->where('role_users.role_id', '=', $student_role_id)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();

            $student_info_array = array();
            if (!empty($student_info)) {
                $profile_image_url = "";
                if ($student_info->image_profile != null) {
                    $profile_image_url = url($student_info->image_profile);
                } else {
                    $profile_image_url = url("assets/img/download.png");
                }

                $student_info_array = array(
                    'student_id' => $student_info->id,
                    'first_name' => $student_info->first_name,
                    'last_name' => $student_info->last_name,
                    'email' => $student_info->email,
                    'profile_picture' => $profile_image_url
                );
                $response = array(
                    'status' => 'success',
                    'message' => 'Student found.',
                    'student_info' => $student_info_array
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Student not found.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}
