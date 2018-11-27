<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PrivateTuition;
use App\Models\User;
use Response;
use DB;
use App\Notifications\StudentPaymentPrivateTuition;
use App\Notifications\TutorPaymentPrivateTuition;
use App\Notifications\StudentPaymentCourse;
use App\Notifications\TutorConfirmationCourse;

class PaymentApiController extends APIController {

    function store_stripe_paymet_response(Request $request) {
        $payment_type = $request->payment_type;
        $student_id = $request->student_id;

        /* start strip payment transaction response array parameter */
        $country = trim($request->country);
        $funding = trim($request->funding);
        $brand = trim($request->brand);
        $id = trim($request->id);
        $created = trim($request->created);
        $currency = trim($request->currency);
        $amount = trim($request->amount);
        $description = trim($request->description);
        $receipt_email = trim($request->receipt_email);


        /* end strip payment transaction response array parameter */
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        if ($payment_type == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide payment type.',
            );
            return $this->respondWithStatus($response);
        }
        if ($payment_type != "private" && $payment_type != "order") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide valid payment type.',
            );
            return $this->respondWithStatus($response);
        }
        if ($payment_type == "private") {
            $tution_id = $request->tution_id;
            if ($tution_id == "") {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Please provide tution id.',
                );
                return $this->respondWithStatus($response);
            } else {
                $this->PrivateTuitionemail($student_id, $tution_id);
                $private_tution_order_data = PrivateTuition::where('user_id', $student_id)
                                ->whereIn('id', explode(",", $tution_id))
                                ->where([
                                    ['status', '1'],
                                    ['payment_status', '0']
                                ])->get();
                if (count($private_tution_order_data) == 0) {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Tution data not found.',
                    );
                    return $this->respondWithStatus($response);
                }

                $transaction_info = array();
                $transaction_info[] = $country;
                $transaction_info[] = $funding;
                $transaction_info[] = $brand;
                $transaction_info[] = $id;
                $transaction_info[] = date("d-m-Y H:i:s", $created);
                $transaction_info[] = $currency;
                $transaction_info[] = ($amount / 100 );
                $transaction_info[] = $description;
                $transaction_info[] = $receipt_email;

                $transaction_data = implode("---", $transaction_info);




                foreach ($private_tution_order_data as $key => $orders) {
                    $tutor_scribblar_id = $orders->tutor->scribblar_id;
                    $student_scribblar_id = $orders->student->scribblar_id;
                    $usernameTutor = $orders->tutor->getFullName();

                    if ($tutor_scribblar_id == NULL) {
                        $first_nameTutor = $orders->tutor->first_name;
                        $last_nameTutor = $orders->tutor->last_name;
                        $emailTutor = $orders->tutor->email;
                        $tutor_scribblar_id = register_scribblar_account($usernameTutor, $first_nameTutor, $last_nameTutor, $emailTutor);
                        $updateUser = User::where('id', $orders->tutor->id)->first();
                        $updateUser->scribblar_id = @$tutor_scribblar_id;
                        $updateUser->update();
                    }

                    if ($student_scribblar_id == NULL) {
                        $usernameS = $orders->student->getFullName();
                        $first_nameS = $orders->student->first_name;
                        $last_nameS = $orders->student->last_name;
                        $emailS = $orders->student->email;
                        $student_scribblar_id = register_scribblar_account($usernameS, $first_nameS, $last_nameS, $emailS);
                        $updateUserS = User::where('id', $orders->student->id)->first();
                        $updateUserS->scribblar_id = @$student_scribblar_id;
                        $updateUserS->update();
                    }

                    $course_name = 'Private Tuition ' . $usernameTutor . ' Date : ';
                    $lesson_title = $orders->start_date . ' - ' . str_random(5);
                    $dataClassRoom = get_classroom_scribblar($course_name, $lesson_title, $tutor_scribblar_id);

                    $classroom_id = $dataClassRoom['classroom_id'];
                    $token_tutor = $dataClassRoom['token_id'];
                    $token_student = generate_token_scribblar();

// $orders->update(['payment_status'=>1],['token_tutor'=>$token_tutor],['token_student'=>$token_student],['classroom_id'=>$classroom_id]); //already comment this line in strippayment controller
                    $orders->price_per_h = $orders->tutor->price_per_h;
                    $orders->payment_status = 1;
                    $orders->token_tutor = $token_tutor;
                    $orders->token_student = $token_student;
                    $orders->classroom_id = $classroom_id;
                    $orders->save();

                    $sum_amount = $orders->tutor->price_per_h * $orders->duration;
                    $date_payment = date("Y-m-d H:i:s", $created);
                    $sum_amount = $orders->tutor->price_per_h * $orders->duration;
                    Order::where('tuition_id', $orders->id)->update(['status' => 1, 'date_payment' => $date_payment, 'payment_amount' => $sum_amount, 'payer_info' => $transaction_data]);
                }
                $response = array(
                    'status' => 'success',
                    'message' => 'Payment received successfully.',
                );
                return $this->respondWithStatus($response);
            }
        } else if ($payment_type == "order") {
            $order_pk_id = trim($request->order_pk_id);
            if ($order_pk_id == "") {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Please provide order id.',
                );
                return $this->respondWithStatus($response);
            } else {
                $order_data = $this->getOrders($student_id, $order_pk_id)->first();
                if (count($order_data) > 0) {


                    $transaction_info = array();
                    $transaction_info[] = $country;
                    $transaction_info[] = $funding;
                    $transaction_info[] = $brand;
                    $transaction_info[] = $id;
                    $transaction_info[] = date("d-m-Y H:i:s", $created);
                    $transaction_info[] = $currency;
                    $transaction_info[] = ($amount / 100 );
                    $transaction_info[] = $description;
                    $transaction_info[] = $receipt_email;

                    $transaction_data = implode("---", $transaction_info);

                    $this->CourseNotifemail($student_id, $order_pk_id);
                    $date_payment = date('Y-m-d H:i:s', $created);



                    Order::where('order_id', $order_data->order_id)
                            ->update(['status' => 1, 'date_payment' => $date_payment, 'payment_amount' => $order_data->course->price, 'payer_info' => $transaction_data]);

                    if ($order_data->course->seats_available >= 1) {
                        $order_data->course->seats_available = $order_data->course->seats_available - 1;
                        $order_data->course->save();
                    }
                    $response = array(
                        'status' => 'success',
                        'message' => 'Payment received successfully.',
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Order not found.',
                    );
                    return $this->respondWithStatus($response);
                }
            }  
        }
    }

    /*
      Send Email Private Tuition
     */

    public function PrivateTuitionemail($student_id, $tution_id) {
        $userStudent = User::where('id', $student_id)->first();
        $privatetuition = PrivateTuition::where('user_id', $student_id)
                ->whereIn('id', explode(",", $tution_id))
                ->where('status', 1)
                ->where('payment_status', '0')
                ->get();
        $url = url('student/dashboard');
        $urlTutor = url('tutor/private-tuition');
        $userStudent->notify(new StudentPaymentPrivateTuition($userStudent, $privatetuition, $url));
        foreach ($privatetuition as $key => $value) {
            $tutor = User::where('id', $value->tutor_id)->first();
            $tutor->notify(new TutorPaymentPrivateTuition($tutor, $value, $urlTutor));
        }
    }

    protected function getOrders($student_id, $order_pk_id) {
        $order = Order::where('student_id', '=', $student_id)
                ->where('orders.id', '=', $order_pk_id)
                ->where('status', '=', 0)
                ->join('courses', 'orders.course_id', '=', 'courses.id')
                ->wherenull('courses.deleted_at');
        return $order;
    }

    /*
      Send Email Course
     */

    public function CourseNotifemail($student_id, $order_pk_id) {
        $userStudent = User::where('id', $student_id)->first();
        $orders = $this->getOrders($student_id, $order_pk_id)->get();
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

}
