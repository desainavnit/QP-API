<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\User;
use Response;
use App\Models\RoleUser;
use DB;
use Sentinel;

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
        //$month_start_date = "";
        //$month_end_date = "";
//        $month_start_date = $request->start_date;
//        $month_end_date = $request->end_date;
//        if ($month_start_date == "") {
//            $month_start_date = date('Y-m-01');
//        }
//        if ($month_end_date == "") {
//            $month_end_date = date('Y-m-t');
//        }

        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id. '
            );
            return $this->respondWithStatus($response);
        }
        /* else if ($month_start_date == "") {
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
          } */ else {
            $student_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $student_id)
                    ->where('role_users.role_id', '=', $student_role_id)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            if (!empty($student_info)) {

                $private_tution = DB::table('private_tuitions as pt')
                        ->join("users as stud", "pt.user_id", '=', 'stud.id')
                        ->join("users as tutor", "pt.tutor_id", '=', 'tutor.id')
                        ->select('pt.*', 'stud.id as student_id', 'stud.first_name as student_fname', 'stud.last_name as student_lname', 'tutor.id as tutor_id', 'tutor.first_name as tutor_fname', 'tutor.last_name as tutor_lname', 'tutor.price_per_h')
                        ->where('pt.user_id', '=', $student_id)
//                        ->where('pt.start_date', '>=', date("Y-m-d", strtotime($month_start_date)))
//                        ->where('pt.start_date', '<=', date("Y-m-d", strtotime($month_end_date)))
                        ->orderBy('pt.start_date', 'DESC')
                        ->get();


                $private_tution_array = array();
                $private_tution_date_array = array();
                foreach ($private_tution as $pt_row) {
                    $final_tution_price = 0;
                    $status_name = "";
                    $status_color = "";
                    if ($pt_row->status == 1) {
                        $status_name = "Approved";
                        $status_color = "#14CCA2";
                    } else if ($pt_row->status == 3) {
                        $status_name = "Reschedule date";
                        $status_color = "#33E6FF";
                    } else if ($pt_row->status == 2) {
                        $status_name = "Rejected";
                        $status_color = "#d91009";
                    } else {
                        $status_name = "Waiting";
                        $status_color = "#333CFF";
                    }

                    $final_tution_price = ($pt_row->price_per_h * $pt_row->duration);
                    $final_tution_price=number_format($final_tution_price,2, ".", "");
                    
                    $private_tution_array[] = array(
                        'id' => $pt_row->id,
                        'date' => date("d M Y", strtotime($pt_row->start_date)),
                        'title' => $pt_row->title,
                        'status' => $pt_row->status,
                        'status_name' => $status_name,
                        'status_color' => $status_color,
                        'payment_status' => $pt_row->payment_status,
                        'student_id' => $pt_row->student_id,
                        'student_name' => $pt_row->student_fname . " " . $pt_row->student_lname,
                        'tutor_id' => $pt_row->tutor_id,
                        'tutor_name' => $pt_row->tutor_fname . " " . $pt_row->tutor_lname,
                        'final_tution_price'=>$final_tution_price, 
                    );

                    array_push($private_tution_date_array, date("Y-m-d", strtotime($pt_row->start_date)));
                }


                if (count($private_tution) > 0) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Private tution data found.',
                        'private_tution_array' => $private_tution_array,
                        'private_tution_date_array' => $private_tution_date_array,
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Private tution data not found.'
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

    function student_private_tution_history(Request $request) {
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
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $student_id)
                    ->where('role_users.role_id', '=', $student_role_id)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            if (!empty($student_info)) {
                $private_tution_confirm = DB::table('private_tuitions as pt')
                        ->join("users as stud", "pt.user_id", '=', 'stud.id')
                        ->join("users as tutor", "pt.tutor_id", '=', 'tutor.id')
                        ->select('pt.*', 'stud.id as student_id', 'stud.first_name as student_fname', 'stud.last_name as student_lname', 'tutor.id as tutor_id', 'tutor.first_name as tutor_fname', 'tutor.last_name as tutor_lname','tutor.image_profile as tutor_profile_image')
                        ->where('pt.user_id', '=', $student_id)
                        ->where('pt.status', '=', 1)
                        ->where('pt.payment_status', '=', 1)
                        ->orderBy('pt.start_date', 'DESC')
                        ->get();

                $private_tution_confirm_array = array();
                foreach ($private_tution_confirm as $pt_row) {
//                    $status = "";
//                    if ($pt_row->status == 1) {
//                        $status = "Approve";
//                    } else if ($pt_row->status == 3) {
//                        $status = "Reschedule date";
//                    } else if ($pt_row->status == 2) {
//                        $status = "Reject";
//                    } else {
//                        $status = "Waiting";
//                    }



                    $tutor_profile_image_url = "";
                    if ($pt_row->tutor_profile_image != null) {
                        $tutor_profile_image_url = url($pt_row->tutor_profile_image);
                    } else {
                        $tutor_profile_image_url = url("assets/img/download.png");
                    }


                    $private_tution_confirm_array[] = array(
                        'time' => date("d-m-Y", strtotime($pt_row->start_date)),
                        'title' => $pt_row->tutor_fname . " " . $pt_row->tutor_lname,
                        'description' => $pt_row->title,
//                        'lineColor' => '#009688',
                        'imageUrl' => $tutor_profile_image_url
 
//                        'id' => $pt_row->id,
//                        'date' => date("Y-m-d", strtotime($pt_row->start_date)),
//                        'title' => $pt_row->title,
//                        'status' => $pt_row->status,
//                        'payment_status' => $pt_row->payment_status,
//                        'student_id' => $pt_row->student_id,
//                        'student_name' => $pt_row->student_fname . " " . $pt_row->student_lname,
//                        'tutor_id' => $pt_row->tutor_id,
//                        'tutor_name' => $pt_row->tutor_fname . " " . $pt_row->tutor_lname
                    );
                }


                if (count($private_tution_confirm) > 0) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Private tution data found.',
                        'private_tution_confirm_array' => $private_tution_confirm_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Private tution data not found.'
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

    function student_change_password(Request $request) {
        $student_id = trim($request->student_id);
        $student_role_id = 2;
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->password)) < "5") {
            $response = array(
                'status' => 'failed',
                'message' => 'password must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->confirmation_password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please confirm your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->confirmation_password)) < "5") {
            $response = array(
                'status' => 'failed',
                'message' => 'Confirm password must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) != trim($request->confirmation_password)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Password and Confirm Password does not match.'
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
                $user = Sentinel::findById($student_id);
                if (Sentinel::update($user, array('password' => trim($request->password)))) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Password changed successfully.',
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Sorry error in password change.',
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

    function change_student_profile(Request $request) {
        $student_id = trim($request->student_id);
        $first_name = trim($request->first_name);
        $last_name = trim($request->last_name);
        $email = trim($request->email);
        $student_role_id = 2;
        $student_info = "";
        $allow_profile_image_ext = array('jpg', 'jpeg', 'png', 'gif');

        $profile_image = $request->file('image_profile');
        $profile_image_ext = "";
        $profile_image_size = 0;
        if (!empty($profile_image)) {
            $profile_image_ext = strtolower($profile_image->getClientOriginalExtension());
            $profile_image_size_ori = $profile_image->getSize();
        }
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
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
        }
        if (empty($student_info)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.'
            );
            return $this->respondWithStatus($response);
        }

        if ($first_name == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add first name below.',
            );
            return $this->respondWithStatus($response);
        }
        if ($last_name == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add last name below.',
            );
            return $this->respondWithStatus($response);
        }
        if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add email below.',
            );
            return $this->respondWithStatus($response);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = array(
                'status' => 'failed',
                'message' => 'The email must be a valid email address.'
            );
            return $this->respondWithStatus($response);
        }
        if ($student_info->image_profile == NULL || $student_info->image_profile == '') {
            if (empty($profile_image)) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Please upload profile picture below.'
                );
                return $this->respondWithStatus($response);
            } else if (!in_array($profile_image_ext, $allow_profile_image_ext)) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The image profile must be a file of type: jpeg, png, jpg, gif.'
                );
                return $this->respondWithStatus($response);
            } else if (!empty($profile_image) && $profile_image_size_ori > 2000000) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The image profile may not be greater than 2 Mb.'
                );
                return $this->respondWithStatus($response);
            }
        }
        if (!empty($profile_image)) {
            if (!in_array($profile_image_ext, $allow_profile_image_ext)) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The image profile must be a file of type: jpeg, png, jpg, gif.'
                );
                return $this->respondWithStatus($response);
            } else if (!empty($profile_image) && $profile_image_size_ori > 2000000) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The image profile may not be greater than 2 Mb.'
                );
                return $this->respondWithStatus($response);
            }
        }


        if (!empty($student_info)) {
            $data = array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email
            );
            $data['image_profile'] = "";
            $image_profile = $request->file('image_profile');
            if (!empty($image_profile)) {
                $upload = upload_file($request->image_profile, 'images/profiles/');
                $data['image_profile'] = '/' . $upload['thumbnail'];
            } else {
                $data['image_profile'] = $student_info->image_profile;
            }

            $check_user_email_duplicate = DB::table('users')
                            ->where('id', '!=', $student_id)
                            ->where('email', '=', $email)
                            ->where('deleted_at', '=', NULL)->exists();
            if ($check_user_email_duplicate >= 1) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The email has already been taken.'
                );
                return $this->respondWithStatus($response);
            } else {
                $user = User::find($student_id);
                $update_user = $user->update($data);
                if ($update_user) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Profile updated successfully.'
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Sorry error in update profile.'
                    );
                    return $this->respondWithStatus($response);
                }
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
