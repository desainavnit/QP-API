<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RoleUser;
use App\Models\Rating;
use App\Models\CourseQA;
use App\Models\Course;
use App\Models\CourseCategory;
use Response;
use DB;
use common;
use DateTime;

class CourseApiController extends APIController {

    protected $course_qa, $course;

    public function __construct(CourseQA $course_qa, Course $course) {
        $this->course_qa = $course_qa;
        $this->course = $course;
    }

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
        $course_q_as_array = array();


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
                    ->select("ratings.*", 'users.first_name', 'users.last_name', 'users.image_profile')
                    ->where('course_id', '=', $course_id)
                    ->where('module', '=', 'course')
                    ->get();

            $course_q_as_first_step = DB::table('course_q_as as cqas')
                    ->join('users', 'users.id', '=', 'cqas.user_id')
                    ->select("cqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
                    ->where('cqas.course_id', '=', $course_id)
                    ->where('cqas.parent_id', '=', NULL)
                    ->orderby('cqas.id', 'desc')
                    ->get();

            $course_order_status = DB::table('orders')
                    ->select("orders.*")
                    ->where("student_id", '=', $student_id)
                    ->where("course_id", '=', $course_id)
                    ->first();

            $is_rated = 0;
            $raing_info = DB::table('ratings')
                    ->select('id', 'module', 'student_id', 'course_id')
                    ->where('student_id', '=', $student_id)
                    ->where('course_id', '=', $course_id)
                    ->where('module', '=', 'course')
                    ->first();
            if (count($raing_info) > 0) {
                $is_rated = 1;
            }

            $course_sale_status = "";
            $purchased = "";
            $is_paid = "";
            $is_rating = "";

            if (count($course_lesson) > 0) {
                if ($course_detail->price > 0) {
                    if (count($course_order_status) > 0) {
                        if ($course_order_status->status != 0 && $course_order_status->payment_amount != NULL) {
                            $purchased = "True";
                            $is_paid = "True";
                            $is_rating = 1;
                        } else {
                            $purchased = "True";
                            $is_paid = "False";
                            $is_rating = 0;
                        }
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                        $is_rating = 0;
                    }
                } else {
                    if (count($course_order_status) > 0) {
                        $purchased = "True";
                        $is_paid = "True";
                        $is_rating = 1;
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                        $is_rating = 0;
                    }
                }
                $course_sale_status = "True";
            } else {
                $course_sale_status = "False";
                $purchased = "False";
                $is_paid = "False";
                $is_rating = 0;
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
                'final_rating' => $final_ratings,
                'is_rated' => $is_rated,
                'is_rating' => $is_rating,
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


            $user_image_url = "";

            foreach ($course_reviews as $cr) {
                if ($cr->image_profile != null) {
                    $user_image_url = url($cr->image_profile);
                } else {
                    $user_image_url = url("assets/img/download.png");
                }
                $course_review_array[] = array(
                    'review_id' => $cr->id,
                    'name' => $cr->first_name . " " . $cr->last_name,
                    'profile_picture' => $user_image_url,
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
            /* start code for course Q & AS */

            if (count($course_q_as_first_step) > 0) {


                foreach ($course_q_as_first_step as $c_qas) {
                    $course_q_as_second_step = DB::table('course_q_as as cqas')
                            ->join('users', 'users.id', '=', 'cqas.user_id')
                            ->select("cqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
                            ->where('cqas.course_id', '=', $c_qas->course_id)
                            ->where('cqas.parent_id', '=', $c_qas->id)
                            ->orderby('cqas.id', 'desc')
                            ->get();


                    $user_image_url1 = "";
                    if ($c_qas->image_profile != null) {
                        $user_image_url1 = url($c_qas->image_profile);
                    } else {
                        $user_image_url1 = url("assets/img/download.png");
                    }
                    $is_edit = "False";
                    if ($student_id == $c_qas->user_id) {
                        $is_edit = "True";
                    }

                    $course_q_as_array[] = array(
                        'id' => $c_qas->id,
                        'name' => $c_qas->first_name . " " . $c_qas->last_name,
                        'profile_picture' => $user_image_url1,
                        'course_q_as' => $c_qas->content,
                        'parent_id' => 0,
                        'date_time' => $c_qas->created_at,
                        'is_edit' => $is_edit
                    );
                    foreach ($course_q_as_second_step as $c_qas_row) {
                        $user_image_url2 = "";
                        if ($c_qas_row->image_profile != null) {
                            $user_image_url2 = url($c_qas_row->image_profile);
                        } else {
                            $user_image_url2 = url("assets/img/download.png");
                        }
                        $course_q_as_array[] = array(
                            'id' => $c_qas_row->id,
                            'name' => $c_qas_row->first_name . " " . $c_qas_row->last_name,
                            'profile_picture' => $user_image_url2,
                            'course_q_as' => $c_qas_row->content,
                            'parent_id' => $c_qas_row->parent_id,
                            'date_time' => $c_qas_row->created_at,
                            'is_edit' => "False"
                        );
                    }
                }
            }


            /* end code for course Q & AS */


            $response = array(
                'status' => 'success',
                'message' => 'Course found.',
                'course_detail_array' => $course_detail_array,
                'course_lesson_array' => $course_lesson_array,
                'course_material_array' => $course_material_array,
                'course_reviews' => $course_review_array,
                'course_q_as_array' => $course_q_as_array
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
        $course_chapter_array = array();
        $course_lesson_array = array();
        $course_material_array = array();
        $course_review_array = array();
        $course_q_as_array = array();


        if (count($course_detail) != 0) {

            $course_chapter = "";
            $course_lesson_count = 0;
            $course_chapter = DB::table('chapters')
                    ->select("chapters.*")
                    ->where('course_id', $course_id)
                    ->orderby("order_chapter", 'ASC')
                    ->get();
            if (count($course_chapter) > 0) {
                
            } else {
                $course_chapter = DB::table('chapters')
                        ->select("chapters.*")
                        ->where('course_id', 0)
                        ->orderby("order_chapter", 'ASC')
                        ->get();
            }
            foreach ($course_chapter as $chapter) {
                $course_lesson = DB::table('course_lessons as cl')
                        ->select("cl.*")
                        ->where("cl.course_id", '=', $course_id)
                        ->where("cl.chapter_id", '=', $chapter->id)
                        ->where("cl.deleted_at", '=', NULL)
                        ->orderby("cl.order_lesson", 'ASC')
                        ->get();
                $course_chapter_array1 = array(
                    'chapter_id' => $chapter->id,
                    'chapter_title' => $chapter->title,
                    'chapter_description' => $chapter->description,
                    'course_lesson_array' => array()
                );
                if (count($course_lesson) > 0) {
                    $course_lesson_count +=count($course_lesson);

                    foreach ($course_lesson as $lesson_row) {
                        $course_chapter_array1['course_lesson_array'][] = array(
                            'lesson_id' => $lesson_row->id,
                            'lesson_title' => $lesson_row->title,
                            'lesson_sub_title' => $lesson_row->sub_title,
                            'lesson_description' => $lesson_row->description,
                            'lesson_duration' => $lesson_row->duration . " minutes",
                            'lesson_start_on' => tellyDT($lesson_row->start_on),
                            'lesson_video_url' => $lesson_row->video_s3_name,
                            'lesson_chapter_id' => $lesson_row->chapter_id
                        );
                    }
                }
                $course_chapter_array[] = $course_chapter_array1;
            }

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
                    ->select("ratings.*", 'users.first_name', 'users.last_name', 'users.image_profile')
                    ->where('course_id', '=', $course_id)
                    ->where('module', '=', 'course')
                    ->get();

            $course_q_as_first_step = $this->course->find($course_id)->course_qas()->where('parent_id', '=', null)->orderBy('created_at', 'desc')->get();
//            $course_q_as_first_step = DB::table('course_q_as as cqas')
//                    ->join('users', 'users.id', '=', 'cqas.user_id')
//                    ->select("cqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
//                    ->where('cqas.course_id', '=', $course_id)
//                    ->where('cqas.parent_id', '=', NULL)
//                    ->orderby('cqas.id', 'desc')
//                    ->get();

            $course_order_status = DB::table('orders')
                    ->select("orders.*")
                    ->where("student_id", '=', $student_id)
                    ->where("course_id", '=', $course_id)
                    ->first();

            $is_rated = 0;
            $raing_info = DB::table('ratings')
                    ->select('id', 'module', 'student_id', 'course_id')
                    ->where('student_id', '=', $student_id)
                    ->where('course_id', '=', $course_id)
                    ->where('module', '=', 'course')
                    ->first();
            if (count($raing_info) > 0) {
                $is_rated = 1;
            }
            $course_sale_status = "";
            $purchased = "";
            $is_paid = "";
            $is_rating = "";

            if (count($course_lesson_count) > 0) {
                if ($course_detail->price > 0) {
                    if (count($course_order_status) > 0) {
                        if ($course_order_status->status != 0 && $course_order_status->payment_amount != NULL) {
                            $purchased = "True";
                            $is_paid = "True";
                            $is_rating = 1;
                        } else {
                            $purchased = "True";
                            $is_paid = "False";
                            $is_rating = 0;
                        }
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                        $is_rating = 0;
                    }
                } else {
                    if (count($course_order_status) > 0) {
                        $purchased = "True";
                        $is_paid = "True";
                        $is_rating = 1;
                    } else {
                        $purchased = "False";
                        $is_paid = "False";
                        $is_rating = 0;
                    }
                }
                $course_sale_status = "True";
            } else {
                $course_sale_status = "False";
                $purchased = "False";
                $is_paid = "False";
                $is_rating = 0;
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
                'final_rating' => $final_ratings,
                'is_rated' => $is_rated,
                'is_rating' => $is_rating,
            );



            $user_image_url = "";

            foreach ($course_reviews as $cr) {
                if ($cr->image_profile != null) {
                    $user_image_url = url($cr->image_profile);
                } else {
                    $user_image_url = url("assets/img/download.png");
                }
                $course_review_array[] = array(
                    'review_id' => $cr->id,
                    'name' => $cr->first_name . " " . $cr->last_name,
                    'profile_picture' => $user_image_url,
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
            /* start code for course Q & AS */


//            print_r($course_q_as_first_step);

            if (count($course_q_as_first_step) > 0) {

                foreach ($course_q_as_first_step as $c_qas) {

//                $course_q_as_second_step = DB::table('course_q_as as cqas')
//                        ->join('users', 'users.id', '=', 'cqas.user_id')
//                        ->select("cqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
//                        ->where('cqas.course_id', '=', $c_qas->course_id)
//                        ->where('cqas.parent_id', '=', $c_qas->id)
//                        ->orderby('cqas.id', 'desc')
//                        ->get();


                    $user_image_url1 = "";
                    if ($c_qas->image_profile != null) {
                        $user_image_url1 = url($c_qas->image_profile);
                    } else {
                        $user_image_url1 = url("assets/img/download.png");
                    }
                    $is_edit = "False";
                    if ($student_id == $c_qas->user_id) {
                        $is_edit = "True";
                    }

                    $course_q_as_array[] = array(
                        'id' => $c_qas->id,
                        'name' => $c_qas->user->first_name . " " . $c_qas->user->last_name,
                        'profile_picture' => $user_image_url1,
                        'course_q_as' => $c_qas->content,
                        'parent_id' => 0,
                        'date_time' => comment_date($c_qas->created_at),
                        'is_edit' => $is_edit
                    );

                    if (count($c_qas->children) > 0) {
                        foreach ($c_qas->children()->orderBy('created_at', 'desc')->get() as $key => $child) {
                            $user_image_url2 = getProfilePicture($child->user->id);
                            
                            $course_q_as_array[] = array(
                                'id' => $child->id,
                                'name' => $child->user->first_name . " " . $child->user->last_name,
                                'profile_picture' => $user_image_url2,
                                'course_q_as' => $child->content,
                                'parent_id' => $child->parent_id,
                                'date_time' => comment_date($child->created_at),
                                'is_edit' => "False"
                            );
                        }
                    }
                }
                /* foreach ($course_q_as_second_step as $c_qas_row) {
                  $user_image_url2 = "";
                  if ($c_qas_row->image_profile != null) {
                  $user_image_url2 = url($c_qas_row->image_profile);
                  } else {
                  $user_image_url2 = url("assets/img/download.png");
                  }

                  //                        $myDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $c_qas_row->created_at);
                  //        echo $formattedweddingdate1 = $myDateTime->format('Y-m-d H:i:s');
                  //                        $date =  date_create_from_format('Y-m-d H:i:s',$c_qas_row->created_at);
                  //                        $date1=  date_format($date, 'Y-m-d H:i:s');
                  //                        echo $formattedweddingdate=comment_date($date1);
                  //                        $date = new \DateTime();
                  //                        $datetimeFormat = 'Y-m-d H:i:s';
                  //
                  //                        $myDateTime=$date->setTimestamp(strtotime($c_qas_row->created_at));
                  //                        $myDateTime=$date->format($datetimeFormat);
                  //                        $myDateTime =date("Y-m-d H:i:s",  $c_qas_row->created_at);
                  //                        $formattedweddingdate = $myDateTime->format('d-m-Y');

                  echo comment_date($c_qas_row->created_at);
                  $course_q_as_array[] = array(
                  'id' => $c_qas_row->id,
                  'name' => $c_qas_row->first_name . " " . $c_qas_row->last_name,
                  'profile_picture' => $user_image_url2,
                  'course_q_as' => $c_qas_row->content,
                  'parent_id' => $c_qas_row->parent_id,
                  'date_time' => $c_qas_row->created_at,
                  'is_edit' => "False"
                  );
                  } */
            }



            /* end code for course Q & AS */


            $response = array(
                'status' => 'success',
                'message' => 'Course found.',
                'course_detail_array' => $course_detail_array,
                'course_chapter_array' => $course_chapter_array,
                'course_material_array' => $course_material_array,
                'course_reviews' => $course_review_array,
                'course_q_as_array' => $course_q_as_array
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

     

    function post_course_rating(Request $request) {
        $student_id = trim($request->student_id);
        $course_id = trim($request->course_id);
        $point = trim($request->point);
        $comment = trim($request->comment);

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
        if ($point == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please select review points.',
            );
            return $this->respondWithStatus($response);
        }
        if ($comment == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please enter review.',
            );
            return $this->respondWithStatus($response);
        }
        $course_detail = DB::table('courses as c')
                ->join("users as u", "c.user_id", '=', 'u.id')
                ->select('c.id', 'c.title', 'u.first_name', 'u.last_name')
                ->where('c.id', '=', $course_id)
                ->where('c.deleted_at', '=', NULL)
                ->where('u.deleted_at', '=', NULL)
                ->first();

        if (count($course_detail) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Course not found.',
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


        $course_rating_info = DB::table('ratings')
                ->select('id', 'module', 'student_id', 'course_id')
                ->where('student_id', '=', $student_id)
                ->where('course_id', '=', $course_id)
                ->where('module', '=', 'course')
                ->first();
        if (count($course_rating_info) == 0) {
            $rating = new Rating();
            $rating->module = "course";
            $rating->course_id = $course_id;
            $rating->point = $point;
            $rating->comment = $comment;
            $rating->student_id = $student_id;
            $rating->save();
            if ($rating) {
                $response = array(
                    'status' => 'success',
                    'message' => 'Thank you for course rating.',
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Error something went wrong in given rating.',
                );
                return $this->respondWithStatus($response);
            }
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Already given rating.',
            );
            return $this->respondWithStatus($response);
        }
    }

}
