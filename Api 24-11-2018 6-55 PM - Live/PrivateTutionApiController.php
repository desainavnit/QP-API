<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\User;
use DB;
use Response;
use App\Models\Order;
use App\Models\PrivateTuition;
use File;
use App\Notifications\RequestPrivatetuition;
use App\Notifications\RequestPrivatetuitionTutor;
use App\Notifications\StudentCancelPrivateTuition;
use App\Notifications\TutorConfirmationPrivatetuition;

class PrivateTutionApiController extends APIController {

    protected $privatetuition;

    public function __construct(PrivateTuition $privatetuition, User $user) {
        $this->privatetuition = $privatetuition;
        $this->user = $user;
    }

    function isValidDateTime($date) {
        if (date('Y-m-d H:i', strtotime($date)) === $date) {
            return 'true';
        } else {
            return "false";
        }
    }

    function book_private_tution(Request $request) {
        $student_id = trim($request->student_id);
        $tutor_id = trim($request->tutor_id);
        $date_time = trim($request->date_time);
        $duration = trim($request->duration);
        $topic = trim($request->topic);
        $description = trim($request->description);
        $file_private = $request->file('file_private');

        $file_private_ext = "";
        $file_private_size_ori = "";
        if (!empty($file_private)) {
            $file_private_ext = strtolower($file_private->getClientOriginalExtension());
            $file_private_size_ori = $file_private->getSize();
        }
        $allow_ext = array("pdf", "png", "jpg", "jpeg", "doc", "docx", "xls", "xlsx");
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.'
            );
            return $this->respondWithStatus($response);
        } else if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        } else if ($date_time == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please specify when you want private tution ?'
            );
            return $this->respondWithStatus($response);
        }
//        else if ($date_time != "" && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date_time)) {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please select valid date time.'
//            );
//            return $this->respondWithStatus($response);
//        } 
        else if ($date_time != "" && $this->isValidDateTime($date_time) === "false") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please select valid date time.'
            );
            return $this->respondWithStatus($response);
        } else if (date('Y-m-d H:i', strtotime($date_time)) < date("Y-m-d H:i")) {
            $response = array(
                'status' => 'failed',
                'message' => 'Sorry.You can not book private tuition in the past.'
            );
            return $this->respondWithStatus($response);
        } else if ($duration == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please specify duration for the private tution.'
            );
            return $this->respondWithStatus($response);
        } else if ($duration != "" && !preg_match('/^[0-9]*$/', $duration)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Tution duration in hour.'
            );
            return $this->respondWithStatus($response);
        } else if ($topic == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please specify topic.'
            );
            return $this->respondWithStatus($response);
        } else if (!empty($file_private) && !in_array($file_private_ext, $allow_ext)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Please upload pdf,png,jpg,jpeg,doc,docx,xls,xlsx file below.'
            );
            return $this->respondWithStatus($response);
        } else if (!empty($file_private) && $file_private_size_ori > 51200000) {
            $response = array(
                'status' => 'failed',
                'message' => 'Max file size 50 Mb.'
            );
            return $this->respondWithStatus($response);
        } else {

            $student_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug')
                    ->where('users.id', '=', $student_id)
                    ->where('role_users.role_id', '=', 2)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', 3)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();

            if (empty($student_info)) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Student not found.'
                );
                return $this->respondWithStatus($response);
            } else if (empty($tutor_info)) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.'
                );
                return $this->respondWithStatus($response);
            } else {
                DB::beginTransaction();
                try {
                    $PrivateTuition = new PrivateTuition;
                    $private_tution_data = array(
                        'user_id' => $student_info->id,
                        'tutor_id' => $tutor_info->id,
                        'start_date' => date("Y-m-d H:i:s", strtotime($date_time)),
                        'duration' => $duration,
                        'title' => $topic,
                        'description' => $description,
                        'file_private' => ""
                    );
                    if (!empty($request->file_private)) {
                        $upload = upload_file($request->file_private, 'file/private_tuition/', 'file');
                        $private_tution_data['file_private'] = '/' . $upload['original'];
                    }

                    $pt = $PrivateTuition->create($private_tution_data);
                    $private_tution_id = $pt->id;


                    $studentInfo = $student_info->email . '---' . $student_info->first_name . '---' . $student_info->last_name;
                    $tutorInfo = $tutor_info->email . '---' . $tutor_info->first_name . '---' . $tutor_info->last_name;

                    $orderPrivateTuition = new Order;
                    $dataPrivate = array_merge($private_tution_data, ['order_id' => $this->generateRandomString(),
                        'student_id' => $student_info->id,
                        'course_id' => null,
                        'status' => 0,
                        'student_info' => $studentInfo,
                        'course_info' => null,
                        'tutor_info' => $tutorInfo,
                        'trans_type' => 1,
                        'scheduled_date' => date("Y-m-d H:i:s", strtotime($date_time)),
                        'tuition_id' => $private_tution_id]);
                    $orderPrivateTuition->create($dataPrivate);

                    $private_id = DB::getPdo()->lastInsertId();

                    DB::commit();


                    $url = url('student/private-tuition');
                    $userStudent = User::where('id', $student_info->id)->first();
                    $userStudent->notify(new RequestPrivatetuition($userStudent, $url));

                    $urlTutor = url('tutor/private-tuition');
                    $userTutor = User::where('id', $tutor_info->id)->first();
                    $userTutor->notify(new RequestPrivatetuitionTutor($userTutor, $urlTutor));

                    $response = array(
                        'status' => 'success',
                        'message' => 'Thank You. For requesting private tuition. You request has been sent to ' . $tutor_info->first_name . ' ' . $tutor_info->last_name . 'once the session has been confirmed you will receive a notification via email.'
                    );
                    return $this->respondWithStatus($response);
                } catch (Exception $e) {
                    DB::rollback();
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Sorry error in private tution booking.'
                    );
                    return $this->respondWithStatus($response);
                }
            }
        }
    }

    private function generateRandomString($length = 5) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return 'PVT-' . $randomString;
    }

    function cancel_private_tution(Request $request) {
        $tution_id = trim($request->tution_id);
        if ($tution_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tution id.'
            );
            return $this->respondWithStatus($response);
        } else {
            $private_tution = PrivateTuition::where('id', $tution_id)->first();
            if (!empty($private_tution)) {
                $private_tution->delete();
                $order = Order::where('tuition_id', $tution_id)->first();
                if (!empty($order)) {
                    $order->delete();
                }

                $tutor = User::where([['id', $private_tution->tutor_id]])->first();
                $user = User::where([['id', $private_tution->user_id]])->first();
                $user->notify(new StudentCancelPrivateTuition($user, $private_tution, $tutor));
                $response = array(
                    'status' => 'success',
                    'message' => 'Private tution cancel successfully.'
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Private tution not found.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    function get_tutor_booked_tution_list(Request $request) {
        $tutor_id = $request->tutor_id;
        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        } else {
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug', 'price_per_h')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', 3)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            $tutor_private_tution_array = array();
            $tutor_private_tution_date_array = array();

            if (!empty($tutor_info)) {

                $private_tution = DB::table('private_tuitions as pt')
                        ->join('users as u', 'pt.user_id', '=', 'u.id')
                        ->select('pt.*', 'u.first_name', 'u.last_name')
                        ->where('pt.tutor_id', '=', $tutor_id)
                        ->where('pt.status', '=', 1)
                        ->where('pt.payment_status', '=', 1)
                        ->orderBy('pt.id', 'desc')
                        ->get();
                if (!empty($private_tution)) {
                    foreach ($private_tution as $pt_row) {

                        $status_name = "";
                        $status_color = "";

                        $start_date = $pt_row->start_date;
                        $start_date_time = date('Y-m-d H:i:s', strtotime($start_date));
                        $end_date_time = date('Y-m-d H:i:s', strtotime($start_date) + 60 * 60 * $pt_row->duration);
                        $total = $pt_row->price_per_h * $pt_row->duration;

                        if ($pt_row->status == 1) {
                            $status_color = "#14CCA2";
                            if ($pt_row->payment_status == 1) {
                                $status_name = "Paid";
                            }
                        }
                        $tutor_private_tution_array[] = array(
                            'tution_id' => $pt_row->id,
                            'title' => $pt_row->title,
                            'buyer_name' => $pt_row->first_name . " " . $pt_row->last_name,
                            'date_time' => $start_date_time,
                            'duration' => $pt_row->duration,
                            'price' => $pt_row->price_per_h,
                            'total' => number_format($total, 2, ".", ""),
                            'status' => $pt_row->status,
                            'payment_status' => $pt_row->payment_status,
                            'status_name' => $status_name,
                            'status_color' => $status_color
                        );
                    }
                }

                if (!empty($tutor_private_tution_array)) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Tutor booked private tuition list found.',
                        'tutor_private_tution_array' => $tutor_private_tution_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Tutor booked private tuition list not found.',
                    );
                    return $this->respondWithStatus($response);
                }
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    function get_tutor_tution_request_list(Request $request) {
        $tutor_id = $request->tutor_id;
        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        } else {
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug', 'price_per_h')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', 3)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            $tutor_private_tution_array = array();
            $tutor_private_tution_date_array = array();

            if (!empty($tutor_info)) {

                $private_tution = DB::table('private_tuitions as pt')
                        ->join('users as u', 'pt.user_id', '=', 'u.id')
                        ->select('pt.*', 'u.first_name', 'u.last_name')
                        ->where('pt.tutor_id', '=', $tutor_id)
                        ->where('pt.status', '!=', 2)
                        ->where('pt.payment_status', '!=', 1)
                        ->orderBy('pt.id', 'desc')
                        ->get();
                if (!empty($private_tution)) {
                    foreach ($private_tution as $pt_row) {
                        $status_name = "";
                        $status_color = "";
                        $start_date = $pt_row->start_date;
                        $start_date_time = date('Y-m-d H:i:s', strtotime($start_date));
                        $end_date_time = date('Y-m-d H:i:s', strtotime($start_date) + 60 * 60 * $pt_row->duration);

                        if ($pt_row->status == 1) {
                            $status_name = "Approved";
                            $status_color = "#14CCA2";
                            if ($pt_row->payment_status == 1) {
                                $status_name = "Paid";
                            } else {
                                $status_name = "Awaiting Payment";
                            }
                        } else if ($pt_row->status == 3) {
                            $status_name = "Reschedule date";
                            $status_color = "#33E6FF";
                        } else if ($pt_row->status == 2) {
                            $status_name = "Rejected";
                            $status_color = "#d91009";
                        } else {
                            $status_name = "Awaiting Approval";
                            $status_color = "#333CFF";
                        }
                        $tutor_private_tution_array[] = array(
                            'tution_id' => $pt_row->id,
                            'title' => $pt_row->title,
                            'description' => $pt_row->description,
                            'buyer_name' => $pt_row->first_name . " " . $pt_row->last_name,
                            'date_time' => $start_date_time,
                            'duration' => $pt_row->duration,
                            'price' => $tutor_info->price_per_h,
                            'status' => $pt_row->status,
                            'payment_status' => $pt_row->payment_status,
                            'status_name' => $status_name,
                            'status_color' => $status_color
                        );
                    }
                }

                if (!empty($tutor_private_tution_array)) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Tutor private tuition request list found.',
                        'tutor_private_tution_array' => $tutor_private_tution_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Tutor private tuition request list not found.',
                    );
                    return $this->respondWithStatus($response);
                }
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    public function post_confirm_private_tution(Request $request) {
        $tution_id = trim($request->tution_id);
        $status = trim($request->status);

        if ($tution_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tution id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($status == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide status.',
            );
            return $this->respondWithStatus($response);
        }

        $private_tuition = PrivateTuition::where([['id', '=', $tution_id]])->first();

        if (count($private_tuition) > 0) {

            DB::beginTransaction();
            try {
                $message = "";
                if ($status == 1) {
                    $message = "you have accepted a private tuition request.";
                } else if ($status == 2) {
                    $message = "you have rejected a private tuition request.";
                } else if ($status == 3) {
                    $message = "you have asked to re-schedule a private tuition request.";
                } else {
                    $message = 'success update.';
                }
                $url = route('student.private_tuition.re-schedule', $tution_id);
                if ($status != 3) {
//                $data = $request->except('start_date', 'duration', 'reschedule_duration', 'reschedule_date', 'reschedule_note', '_token');
                    $url = route('student.private_tuition');
                }
                $privatetuition = $this->privatetuition->find($tution_id);
                $data = array('status' => $status);

                //send email notification            
                $privatetuition->update($data);

                $order = Order::where('tuition_id', $privatetuition->id)->first();
                if ($order) {
                    $order->status = $status;
                    $order->save();
                }

                DB::commit();

                $id_student = $privatetuition->user_id;
                $user = $this->user->where('id', $id_student)->first();
                $user->notify(new TutorConfirmationPrivatetuition($user, $url, $privatetuition, $status));
                $response = array(
                    'status' => 'success',
                    'message' => $message,
                );
                return $this->respondWithStatus($response);
            } catch (Exception $e) {
                DB::rollback();
                $response = array(
                    'status' => 'failed',
                    'message' => 'Error in private tuition confirm.',
                );
                return $this->respondWithStatus($response);
            }
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Private tution not found.',
            );
            return $this->respondWithStatus($response);
        }
    }

}
