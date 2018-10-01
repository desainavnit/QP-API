<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RoleUser;
//use App\Models\Course;
//use App\Models\CourseCategory;
//use App\Models\Category;
//use App\Models\CategoryDetail;
//use App\Models\Rating;
//use Sentinel;
use Response;
use DB;

class CourseApiController extends APIController {

    function isValidDate($date) {
        if (date('Y-m-d', strtotime($date)) === $date) {
            return 'true';
        } else {
            return "false";
        }
    }

    public function get_one_course(Request $request) {
        $student_id = trim($request->student_id);
        $course_id = trim($request->course_id);
        $student_role_id = 2;

        if ($course_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide course id.',
            );
            return $this->respondWithStatus($response);
        }
        $course_detail = DB::table('courses as c')
                ->join("users as u", "c.user_id", '=', 'u.id')
                ->join("course_categories as cc", "c.id", '=', 'cc.course_id')
                ->join("category_details as cd", "cc.category_detail_id", '=', 'cd.id')
                ->join("categories as cat", "cd.category_id", '=', 'cat.id')
                ->select('c.*', 'u.first_name', 'u.last_name', 'u.image_profile', 'cat.id as category_id', 'cat.name as category_name', 'cd.id as category_detail_id', 'cd.name as category_detail_name')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->where('u.deleted_at', '=', NULL)
                ->first();
//        print_r($course_detail);

        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        $student_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_id)
                ->where('role_users.role_id', '=', $student_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();
        if (count($student_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.',
            );
            return $this->respondWithStatus($response);
        }
        $course_detail_array = array();
        $course_lesson_array = array();
        $course_material_array = array();
        $course_review_array = array();


        if (count($course_detail) != 0) {
            $course_lesson = DB::table('course_lessons as cl')
                    ->select("cl.*")
                    ->where("cl.course_id", '=', $course_id)
                    ->where("cl.deleted_at", '=', NULL)
                    ->orderby("cl.order_lesson", 'ASC')
                    ->get();
            $course_material = DB::table('course_s_materials')
                    ->select("course_s_materials.*")
                    ->where("course_id", '=', $course_id)
                    ->get();


            $course_avg_ratings = DB::table('ratings')
                    ->select("ratings.*")
                    ->where('course_id', $course_id)
                    ->where('module', 'course')
                    ->avg('point');
            if ($course_avg_ratings == 0) {
                $course_avg_ratings = 4;
                if ($course_detail->user_id == 3 || $course_detail->user_id == 4 || $course_detail->user_id == 7 || $course_detail->user_id == 200) {
                    $course_avg_ratings = 5;
                }
            }

            $tutor_avg_ratings = DB::table("ratings")
                    ->where('user_id', $course_detail->user_id)
                    ->where('module', 'tutor')
                    ->avg('point');

            if ($tutor_avg_ratings == 0) {
                $tutor_avg_ratings = 4;
                if ($course_detail->user_id == 3 || $course_detail->user_id == 4 || $course_detail->user_id == 7 || $course_detail->user_id == 200) {
                    $tutor_avg_ratings = 5;
                }
            }
            $final_ratings = round(($tutor_avg_ratings + $course_avg_ratings) / 2);

            $course_reviews = DB::table('ratings')
                    ->leftjoin('users', 'users.id', '=', 'ratings.student_id')
                    ->select("ratings.*", 'users.first_name', 'users.last_name')
                    ->where('course_id', $course_id)->where('module', 'course')
                    ->where("course_id", $course_id)
                    ->get();

            $course_order_status = DB::table('orders')
                    ->select("orders.*")
                    ->where("student_id", '=', $student_id)
                    ->where("course_id", '=', $course_id)
                    ->first();


            $course_sale_status = "";
            $purchased = "";
            $is_paid = "";


            if (count($course_lesson) > 0) {
                if ($course_detail->price > 0) {
                    if (count($course_order_status) > 0) {
                        if ($course_order_status->status != 0 && $course_order_status->payment_amount != NULL) {
                            $purchased = "True";
                            $is_paid = "True";
                        } else {
                            $purchased = "True";
                            $is_paid = "False";
                        }
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                    }
                } else {
                    if (count($course_order_status) > 0) {
                        $purchased = "True";
                        $is_paid = "True";
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                    }
                }
                $course_sale_status = "True";
            } else {
                $course_sale_status = "False";
                $purchased = "False";
                $is_paid = "False";
            }
            if ($course_detail->image_profile != null) {
                $tutor_image_url = url($course_detail->image_profile);
            } else {
                $tutor_image_url = url("assets/img/download.png");
            }
            $course_detail_array = array(
                'course_id' => $course_detail->id,
                'course_title' => $course_detail->title,
                'course_sub_title' => $course_detail->sub_title,
                'course_category' => $course_detail->category_name,
                'course_description' => $course_detail->description,
                'course_requirement' => $course_detail->requirement,
                'course_image' => url($course_detail->image_course),
                'course_price' => $course_detail->price,
                'course_duration' => convertToHoursMins($course_detail->duration, '%02d hours %02d minutes'),
                'no_of_course_lesson' => count($course_lesson),
                'tutor_name' => $course_detail->first_name . " " . $course_detail->last_name,
                'tutor_image_url' => $tutor_image_url,
                'course_sale_status' => $course_sale_status,
                'purchased' => $purchased,
                'is_paid' => $is_paid,
                'course_avg_rating' => round($course_avg_ratings, 1),
                'tutor_avg_rating' => round($tutor_avg_ratings, 1),
                'final_rating' => $final_ratings
            );
            foreach ($course_lesson as $lesson_row) {
                $course_lesson_array [] = array(
                    'lesson_id' => $lesson_row->id,
                    'lesson_title' => $lesson_row->title,
                    'lesson_sub_title' => $lesson_row->sub_title,
                    'lesson_description' => $lesson_row->description,
                    'lesson_duration' => $lesson_row->duration . " minutes",
                    'lesson_start_on' => tellyDT($lesson_row->start_on),
                    'lesson_video_url' => $lesson_row->video_s3_name,
                );
            }


            foreach ($course_reviews as $cr) {
                $course_review_array[] = array(
                    'review_id' => $cr->id,
                    'name' => $cr->first_name . " " . $cr->last_name,
                    'point' => $cr->point,
                    'comment' => $cr->comment,
                    'date' => date("Y-m-d", strtotime($cr->created_at))
                );
            }
            foreach ($course_material as $cm) {
                $course_material_array [] = array(
                    'material_id' => $cm->id,
                    'material_name' => $cm->name_support_material,
                    'material_file' => url($cm->file_support_material),
                );
            }
            $response = array(
                'status' => 'success',
                'message' => 'Course found.',
                'course_detail_array' => $course_detail_array,
                'course_lesson_array' => $course_lesson_array,
                'course_material_array' => $course_material_array,
                'course_reviews' => $course_review_array
            );
            return $this->respondWithStatus($response);
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
            );
            return $this->respondWithStatus($response);
        }
    }
    public function get_one_course1(Request $request) {
        $student_id = trim($request->student_id);
        $course_id = trim($request->course_id);
        $student_role_id = 2;

        if ($course_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide course id.',
            );
            return $this->respondWithStatus($response);
        }
        $course_detail = DB::table('courses as c')
                ->join("users as u", "c.user_id", '=', 'u.id')
                ->join("course_categories as cc", "c.id", '=', 'cc.course_id')
                ->join("category_details as cd", "cc.category_detail_id", '=', 'cd.id')
                ->join("categories as cat", "cd.category_id", '=', 'cat.id')
                ->select('c.*', 'u.first_name', 'u.last_name', 'u.image_profile', 'cat.id as category_id', 'cat.name as category_name', 'cd.id as category_detail_id', 'cd.name as category_detail_name')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->where('u.deleted_at', '=', NULL)
                ->first();
//        print_r($course_detail);

        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        $student_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $student_id)
                ->where('role_users.role_id', '=', $student_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();
        if (count($student_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Student not found.',
            );
            return $this->respondWithStatus($response);
        }
        $course_detail_array = array();
        $course_lesson_array = array();
        $course_material_array = array();
        $course_review_array = array();


        if (count($course_detail) != 0) {
            $course_lesson = DB::table('course_lessons as cl')
                    ->select("cl.*")
                    ->where("cl.course_id", '=', $course_id)
                    ->where("cl.deleted_at", '=', NULL)
                    ->orderby("cl.order_lesson", 'ASC')
                    ->get();
            $course_material = DB::table('course_s_materials')
                    ->select("course_s_materials.*")
                    ->where("course_id", '=', $course_id)
                    ->get();


            $course_avg_ratings = DB::table('ratings')
                    ->select("ratings.*")
                    ->where('course_id', $course_id)
                    ->where('module', 'course')
                    ->avg('point');
            if ($course_avg_ratings == 0) {
                $course_avg_ratings = 4;
                if ($course_detail->user_id == 3 || $course_detail->user_id == 4 || $course_detail->user_id == 7 || $course_detail->user_id == 200) {
                    $course_avg_ratings = 5;
                }
            }

            $tutor_avg_ratings = DB::table("ratings")
                    ->where('user_id', $course_detail->user_id)
                    ->where('module', 'tutor')
                    ->avg('point');

            if ($tutor_avg_ratings == 0) {
                $tutor_avg_ratings = 4;
                if ($course_detail->user_id == 3 || $course_detail->user_id == 4 || $course_detail->user_id == 7 || $course_detail->user_id == 200) {
                    $tutor_avg_ratings = 5;
                }
            }
            $final_ratings = round(($tutor_avg_ratings + $course_avg_ratings) / 2);

            $course_reviews = DB::table('ratings')
                    ->leftjoin('users', 'users.id', '=', 'ratings.student_id')
                    ->select("ratings.*", 'users.first_name', 'users.last_name')
                    ->where('course_id', $course_id)->where('module', 'course')
                    ->where("course_id", $course_id)
                    ->get();
            
//             $final_data1 = DB::table('product_categories as pc')
//                        ->join('product_categories as pc1', 'pc1.id', '=', 'pc.category_id', 'left')
//                        ->select('pc.category_name', 'pc.id', 'pc.is_sub_category', 'pc1.category_name as parent_name')
//                        ->where('pc.deleted_at', '=', NULL)
//                        ->orderBy('pc.id')
//                        ->get();
             $final_data = DB::table('course_q_as as c_qas')
                        ->join('course_q_as as c_qas1', 'c_qas1.id', '=', 'c_qas.parent_id', 'left')
                        ->join('users as u', 'c_qas.user_id', '=', 'u.id')
                        ->select('c_qas.*','u.id as user_id','u.first_name','u.last_name')
                        ->where('c_qas.course_id','=',$course_id)
                        ->orderBy('c_qas.id')
                        ->orderBy('c_qas1.created_at','desc')
                        ->get();
              
             print_r($final_data);
             return;

            $course_order_status = DB::table('orders')
                    ->select("orders.*")
                    ->where("student_id", '=', $student_id)
                    ->where("course_id", '=', $course_id)
                    ->first();


            $course_sale_status = "";
            $purchased = "";
            $is_paid = "";


            if (count($course_lesson) > 0) {
                if ($course_detail->price > 0) {
                    if (count($course_order_status) > 0) {
                        if ($course_order_status->status != 0 && $course_order_status->payment_amount != NULL) {
                            $purchased = "True";
                            $is_paid = "True";
                        } else {
                            $purchased = "True";
                            $is_paid = "False";
                        }
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                    }
                } else {
                    if (count($course_order_status) > 0) {
                        $purchased = "True";
                        $is_paid = "True";
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                    }
                }
                $course_sale_status = "True";
            } else {
                $course_sale_status = "False";
                $purchased = "False";
                $is_paid = "False";
            }
            if ($course_detail->image_profile != null) {
                $tutor_image_url = url($course_detail->image_profile);
            } else {
                $tutor_image_url = url("assets/img/download.png");
            }
            $course_detail_array = array(
                'course_id' => $course_detail->id,
                'course_title' => $course_detail->title,
                'course_sub_title' => $course_detail->sub_title,
                'course_category' => $course_detail->category_name,
                'course_description' => $course_detail->description,
                'course_requirement' => $course_detail->requirement,
                'course_image' => url($course_detail->image_course),
                'course_price' => $course_detail->price,
                'course_duration' => convertToHoursMins($course_detail->duration, '%02d hours %02d minutes'),
                'no_of_course_lesson' => count($course_lesson),
                'tutor_name' => $course_detail->first_name . " " . $course_detail->last_name,
                'tutor_image_url' => $tutor_image_url,
                'course_sale_status' => $course_sale_status,
                'purchased' => $purchased,
                'is_paid' => $is_paid,
                'course_avg_rating' => round($course_avg_ratings, 1),
                'tutor_avg_rating' => round($tutor_avg_ratings, 1),
                'final_rating' => $final_ratings
            );
            foreach ($course_lesson as $lesson_row) {
                $course_lesson_array [] = array(
                    'lesson_id' => $lesson_row->id,
                    'lesson_title' => $lesson_row->title,
                    'lesson_sub_title' => $lesson_row->sub_title,
                    'lesson_description' => $lesson_row->description,
                    'lesson_duration' => $lesson_row->duration . " minutes",
                    'lesson_start_on' => tellyDT($lesson_row->start_on),
                    'lesson_video_url' => $lesson_row->video_s3_name,
                );
            }


            foreach ($course_reviews as $cr) {
                $course_review_array[] = array(
                    'review_id' => $cr->id,
                    'name' => $cr->first_name . " " . $cr->last_name,
                    'point' => $cr->point,
                    'comment' => $cr->comment,
                    'date' => date("Y-m-d", strtotime($cr->created_at))
                );
            }
            foreach ($course_material as $cm) {
                $course_material_array [] = array(
                    'material_id' => $cm->id,
                    'material_name' => $cm->name_support_material,
                    'material_file' => url($cm->file_support_material),
                );
            }
            $response = array(
                'status' => 'success',
                'message' => 'Course found.',
                'course_detail_array' => $course_detail_array,
                'course_lesson_array' => $course_lesson_array,
                'course_material_array' => $course_material_array,
                'course_reviews' => $course_review_array
            );
            return $this->respondWithStatus($response);
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
            );
            return $this->respondWithStatus($response);
        }
    }

}
