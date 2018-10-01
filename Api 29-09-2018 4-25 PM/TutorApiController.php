<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\FavouriteTutor;
//use App\Models\Category;
use App\Models\User;
use App\Models\RoleUser;
//use App\Models\CourseCategory;
//use App\Models\CategoryDetail;
//use App\Models\Rating;
//use App\Models\Course;
//use App\Models\PrivateTuition;
//use App\Models\ScheduleUnavailable;
//use Sentinel;
use Response;
use DB;

class TutorApiController extends APIController {

    function isValidDate($date) {
        if (date('Y-m-d', strtotime($date)) === $date) {
            return 'true';
        } else {
            return "false";
        }
    }

    public function get_tutor_list(Request $request) {
        $role_tutor = 3;
        $page = $request->page;
        $tutor_id = $request->tutor_id;
        $student_id = $request->student_id;
        $page = $request->page;
        $limit = 5; //Limit per page record display
        if ($page == "") {
            $page = 0;
        }

//        $star_ratings = $request->get('search_ratings');
        $price_min = $request->get('price_min');
        $price_max = $request->get('price_max');

        if ($price_max == "") {
            $price_max = 1000;
        }
        if ($price_min == "") {
            $price_min = 0;
        }
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        //        if ($tutor_id == "") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please provide tutor id.',                
//            );
//            return $this->respondWithStatus($response);
//        }
        else {

            $get_all_tutor = RoleUser::where('role_id', $role_tutor)
                    ->join('users', 'role_users.user_id', '=', 'users.id')
                    ->select(['users.*', 'role_users.*'])
//                ->when($star_ratings, function($query) use ($star_ratings) {
//                    if ($star_ratings > 0) {
//                        return $query->leftjoin('ratings', 'role_users.user_id', '=', 'ratings.user_id')
//                                ->where('ratings.module', '=', 'tutor')
//                                ->havingRaw('AVG(ratings.point) >= ' . $star_ratings);
//                    }
//                })
                    ->where('status', 1)
                    ->where('price_per_h', ">=", $price_min)
                    ->where('price_per_h', "<=", $price_max)
                    ->where('users.deleted_at', '=', NULL)
                    ->where('hide_profile', 0)
                    ->where('is_profile_complete', "=", 1)
                    ->groupBy('users.id')
                    ->groupBy('role_users.role_id')
                    ->groupBy('role_users.user_id')
                    ->orderBy('ordering', 'ASC')
//                        ->where("users.id", '=', 200)->get();
//                    ->skip($page * $limit)
//                    ->take($limit)
                    ->get();
            //Here take(5)  Perpage 5 record 
//        $categories = DB::table('categories')
//                ->select('categories.id', 'categories.name')
//                ->orderBy('order', 'asc')
//                ->get();        
//        print_r($get_all_tutor);

            if (count($get_all_tutor) > 0) {
                $tutor_list_array = array();
                foreach ($get_all_tutor as $row) {
                    /* start get tutor ratting */
                    $tutor_rating_data = DB::table('ratings')
                            ->leftjoin('role_users', 'role_users.user_id', '=', 'ratings.user_id')
                            ->select('ratings.*')
                            ->where('ratings.module', '=', 'tutor')
                            ->where('ratings.user_id', '=', $row->id)
                            ->avg('point');


                    $tutor_rating_count = 0;
                    if (!empty($tutor_rating_data)) {
                        $tutor_rating_count = round($tutor_rating_data);
                    } else {
                        if ($tutor_rating_count == 0) {
                            $tutor_rating_count = 4;
                            if ($row->id == 3 || $row->id == 4 || $row->id == 7) {
                                $tutor_rating_count = 5;
                            }
                        }
                        //$norating = 5 - $tutor_rating_count;
                    }

                    /* end get tutor ratting */

                    /* Start Code Get marked as favourite Tutor */
                    $get_marked_favourite_tutor = DB::table('favourite_tutors as ft')
                            ->select('ft.*')
                            ->where('user_id', $student_id)
                            ->where('tutor_id', $row->id)
                            ->first();
                    $is_favourite_tutor = 0;
                    if (!empty($get_marked_favourite_tutor)) {
                        $is_favourite_tutor = 1;
                    }
                    /* End Code Get marked as favourite Tutor */


                    $profile_image_url = "";
                    if ($row->image_profile != null) {
                        $profile_image_url = url($row->image_profile);
                    } else {
                        $profile_image_url = url("assets/img/download.png");
                    }
                    $tutor_data = array(
                        'tutor_id' => $row->id,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'profile_image' => $profile_image_url,
                        'price_per_h' => $row->price_per_h,
                        'tutor_rating' => $tutor_rating_count,
                        'tutor_experties' => $row->tutor_experties,
//                    'tutor_profile' => $row->profile,
//                    'qualifications' => $row->qualifications,
                        'is_favourite_tutor' => $is_favourite_tutor,
                        'tutor_experties_category' => "",
//                    'tutor_experties_category_array' => array(),
                    );

                    /* code start  Tutor experties category  */

                    if ($row->experties_categories != null || $row->experties_categories != "") {
                        $all_experties = explode(",", $row->experties_categories);
                        $categorie_details = DB::table('category_details')
                                ->join('categories', 'category_details.category_id', '=', 'categories.id')
                                ->select('category_details.id as category_detail_id', 'category_details.category_id', 'category_details.name as category_detail_name', 'categories.name as category_name')
                                ->whereIn('category_details.id', $all_experties)
                                ->orderBy('category_id', 'ASC')
                                ->get();
                        //print_r($categorie_details);


                        $old_cat_id = "";
                        $tutor_experties_category = "";
                        $i = 0;
                        foreach ($categorie_details as $cat_detail_row) {
                            if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                                if ($i == 0) {
                                    $tutor_experties_category = $cat_detail_row->category_name;
                                    $i++;
                                } else {
                                    $tutor_experties_category .= ',' . $cat_detail_row->category_name;
                                }
                                /* $tutor_data['tutor_experties_category_array'][] = array(
                                  'category_id' => $cat_detail_row->category_id,
                                  'category_name' => $cat_detail_row->category_name,
                                  'category_detail_array' => array(),
                                  ); */
                            }
                            /* $key2 = array_search($cat_detail_row->category_id, array_column($tutor_data['tutor_experties_category_array'], 'category_id'));
                              $tutor_data['tutor_experties_category_array'][$key2]['category_detail_array'][] = array(
                              'category_detail_id' => $cat_detail_row->category_detail_id,
                              'category_detail_name' => $cat_detail_row->category_detail_name,
                              ); */
                            $old_cat_id = $cat_detail_row->category_id;
                        }
                    }

                    $tutor_data['tutor_experties_category'] = $tutor_experties_category;
                    $tutor_list_array[] = $tutor_data;
                    /* code end Tutor experties category   */
                }
                $response = array(
                    'status' => 'success',
                    'message' => 'Tutor list found.',
                    'tutor_list_array' => $tutor_list_array
                );
                return $this->respondWithStatus($response);
//            print_r($tutor_list_array);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor list not found.',
                    'tutor_list_array' => array()
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    public function get_one_tutor(Request $request) {
        $role_tutor = 3;
        $tutor_id = $request->tutor_id;

        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.',
            );
            return $this->respondWithStatus($response);
        } else {

            $get_one_tutor = RoleUser::where('role_id', $role_tutor)
                    ->join('users', 'role_users.user_id', '=', 'users.id')
                    ->select(['users.*', 'role_users.*'])
                    ->where('status', 1)
                    ->where('users.deleted_at', '=', NULL)
                    ->where('hide_profile', 0)
                    ->where('is_profile_complete', "=", 1)
                    ->groupBy('users.id')
                    ->groupBy('role_users.role_id')
                    ->groupBy('role_users.user_id')
                    ->orderBy('ordering', 'ASC')
                    ->where("users.id", $tutor_id)
                    ->first();
//                        print_r($get_one_tutor);
//                        return;
//        $categories = DB::table('categories')
//                ->select('categories.id', 'categories.name')
//                ->orderBy('order', 'asc')
//                ->get();


            if (count($get_one_tutor) > 0) {
                $tutor_detail_array = array();

                /* start get tutor ratting */
                $tutor_rating_data = DB::table('ratings')
                        ->leftjoin('role_users', 'role_users.user_id', '=', 'ratings.user_id')
                        ->select('ratings.*')
                        ->where('ratings.module', '=', 'tutor')
                        ->where('ratings.user_id', '=', $get_one_tutor->id)
                        ->avg('point');


                $tutor_rating_count = 0;
                if (!empty($tutor_rating_data)) {
                    $tutor_rating_count = round($tutor_rating_data);
                } else {
                    if ($tutor_rating_count == 0) {
                        $tutor_rating_count = 4;
                        if ($get_one_tutor->id == 3 || $get_one_tutor->id == 4 || $get_one_tutor->id == 7) {
                            $tutor_rating_count = 5;
                        }
                    }
                    //$norating = 5 - $tutor_rating_count;
                }

                /* end get tutor ratting */

                $profile_image_url = "";
                if ($get_one_tutor->image_profile != null) {
                    $profile_image_url = url($get_one_tutor->image_profile);
                } else {
                    $profile_image_url = url("assets/img/download.png");
                }
                $tutor_profile_video = "";
                if ($get_one_tutor->profile_video != null) {
                    $tutor_profile_video = $get_one_tutor->profile_video;
                }
                $tutor_data = array(
                    'tutor_id' => $get_one_tutor->id,
                    'first_name' => $get_one_tutor->first_name,
                    'last_name' => $get_one_tutor->last_name,
                    'profile_image' => $profile_image_url,
                    'tutor_profile_video' => $tutor_profile_video,
                    'price_per_h' => $get_one_tutor->price_per_h,
                    'tutor_rating' => $tutor_rating_count,
                    'tutor_experties' => $get_one_tutor->tutor_experties,
                    'tutor_profile' => $get_one_tutor->profile,
                    'qualifications' => $get_one_tutor->qualifications,
                    'tutor_experties_category' => "",
//                    'tutor_experties_category_array' => array()
                );
// str_replace(PHP_EOL, '',$str);
                /* code start  Tutor experties category  */

                if ($get_one_tutor->experties_categories != null || $get_one_tutor->experties_categories != "") {
                    $all_experties = explode(",", $get_one_tutor->experties_categories);
                    $categorie_details = DB::table('category_details')
                            ->join('categories', 'category_details.category_id', '=', 'categories.id')
                            ->select('category_details.id as category_detail_id', 'category_details.category_id', 'category_details.name as category_detail_name', 'categories.name as category_name')
                            ->whereIn('category_details.id', $all_experties)
                            ->orderBy('category_id', 'ASC')
                            ->get();
                    //print_r($categorie_details);


                    $old_cat_id = "";
                    $tutor_experties_category = "";
                    $tutor_experties_category_array=array();
                    $i = 0;
                    foreach ($categorie_details as $cat_detail_row) {
                        if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                            if ($i == 0) {
                                $tutor_experties_category = $cat_detail_row->category_name;
                                $i++;
                            } else {
                                $tutor_experties_category .= ',' . $cat_detail_row->category_name;
                            }


                            $tutor_experties_category_array[] = array(
                                'category_id' => $cat_detail_row->category_id,
                                'category_name' => $cat_detail_row->category_name,
                                'category_detail_array' => array(),
                            );
                        }
                        $key2 = array_search($cat_detail_row->category_id, array_column($tutor_experties_category_array, 'category_id'));
                        $tutor_experties_category_array[$key2]['category_detail_array'][] = array(
                            'category_detail_id' => $cat_detail_row->category_detail_id,
                            'category_detail_name' => $cat_detail_row->category_detail_name,
//                  'category_name' => $cat_detail_row->category_name,
                        );
                        $old_cat_id = $cat_detail_row->category_id;
                    }
                }
                $tutor_data['tutor_experties_category'] = $tutor_experties_category;
                $tutor_detail_array[] = $tutor_data;
                /* code end Tutor experties category   */
                
                /* start code get tutor course */
                $tutor_course_array = array();
                $get_tutor_courses = DB::table('courses')
                        ->select('courses.id', 'courses.title', 'courses.description', 'courses.image_course', 'courses.duration')
                        ->where('user_id', $get_one_tutor->id)
                        ->where('deleted_at', "=", NULL)
                        ->get();
//            print_r($get_tutor_courses);

                if (!empty($get_tutor_courses)) {
                    foreach ($get_tutor_courses as $tutor_course_row) {
                        $tutor_course_array [] = array(
                            'course_id' => $tutor_course_row->id,
                            'course_title' => $tutor_course_row->title,
                            'description' => $tutor_course_row->description,
                            'image_course' => url($tutor_course_row->image_course),
                            'course_duration' => floor($tutor_course_row->duration / 60) . ' hours ' . ($tutor_course_row->duration - floor($tutor_course_row->duration / 60) * 60) . " mins"
                        );
                    }
                }

                /* end code get tutor course */


                /* start code get tutor course */
                $tutor_course_array = array();
                $get_tutor_courses = DB::table('courses')
                        ->join("course_lessons as cl",'courses.id','=','cl.course_id')
                        ->select('courses.id', 'courses.title', 'courses.description', 'courses.image_course', 'courses.duration')
                        ->where('courses.user_id', $get_one_tutor->id)
                        ->where('courses.deleted_at', "=", NULL)
                        ->where('cl.deleted_at', "=", NULL)
                        ->groupby('courses.id')
                        ->get();


                if (count($get_tutor_courses) > 0) {
                    foreach ($get_tutor_courses as $tutor_course_row) {
                        $tutor_course_array [] = array(
                            'course_id' => $tutor_course_row->id,
                            'course_title' => $tutor_course_row->title,
                            'description' => $tutor_course_row->description,
                            'image_course' => url($tutor_course_row->image_course),
                            'course_duration' => floor($tutor_course_row->duration / 60) . ' hours ' . ($tutor_course_row->duration - floor($tutor_course_row->duration / 60) * 60) . " mins"
                        );
                    }
                }

                /* end code get tutor course */




                $response = array(
                    'status' => 'success',
                    'message' => 'Tutor found.',
                    'tutor_detail_array' => $tutor_detail_array,
                    'tutor_course_array' => $tutor_course_array,
                    'tutor_experties_category_array'=>$tutor_experties_category_array
                );
                return $this->respondWithStatus($response);
//            print_r($tutor_list_array);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                    'tutor_list_array' => array(),
                    'tutor_course_array' => array(),
                    'tutor_experties_category_array'=>array()
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    function get_tutor_calendar(Request $request) {
        $tutor_id = $request->tutor_id;
//        $month_start_date = $request->month_start_date;
//        $month_end_date = $request->month_end_date;
//        $month_start_date = "";
//        $month_end_date = "";
//        if ($month_start_date == "") {
//            $month_start_date = date('Y-m-01'); 
//        }
//        if ($month_end_date == "") {
//            $month_end_date = date('Y-m-d');
//        }


        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        }
//        else if ($month_start_date == "") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please provide month start date.'
//            );
//            return $this->respondWithStatus($response);
//        }
//        else if ($month_start_date != "" && $this->isValidDate($month_start_date) === "false") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please select valid month start date.'
//            );
//            return $this->respondWithStatus($response);
//        }
//        else if ($month_end_date == "") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please provide month end date.'
//            );
//            return $this->respondWithStatus($response);
//        } 
//        else if ($month_end_date != "" && $this->isValidDate($month_end_date) === "false") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please select valid month end date.'
//            );
//            return $this->respondWithStatus($response);
//        } 
        else {
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'slug')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', 3)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            $tutor_calendar_array = array();
            if (!empty($tutor_info)) {

                $private_tution = DB::table('private_tuitions as pt')
                        ->select('pt.*')
                        ->where('pt.tutor_id', '=', $tutor_id)
                        ->where('pt.status', '=', 1)
                        ->where('pt.payment_status', '=', 1)
                        ->get();
                if (!empty($private_tution)) {
                    foreach ($private_tution as $pt_row) {
                        $timestamp = $pt_row->start_date;
                        $date = date('Y-m-d', strtotime($timestamp));
                        $time = date('H:i:s', strtotime($timestamp));
                        $tutor_calendar_array[] = array(
                            'id' => $pt_row->id,
                            'title' => $pt_row->title,
//                            'start' => $date . 'T' . $time,
//                            'end' => $date . 'T' . $time
                            'start' => $date . ' ' . $time,
                            'end' => $date . ' ' . $time,
                            'type' => 'private_tution'
                        );
                    }
                }


                $course_lession = DB::table('courses as c')
                        ->join("course_lessons as cl", 'c.id', '=', 'cl.course_id')
                        ->select('c.id', 'c.title as course_title', 'c.user_id', 'cl.id as course_lession_id', 'cl.title as course_lession_title', 'cl.start_on')
                        ->where('c.user_id', '=', $tutor_id)
                        ->where('c.deleted_at', '=', NULL)
                        ->where('cl.deleted_at', '=', NULL)
                        ->get();
                foreach ($course_lession as $value) {

                    $timestamp = $value->start_on;
                    $date = date('Y-m-d', strtotime($timestamp));
                    $time = date('H:i:s', strtotime($timestamp));
                    $tutor_calendar_array[] = array(
                        'id' => $value->course_lession_id . '_l',
                        'title' => $value->course_title . '-' . $value->course_lession_title,
                        'start' => $date . ' ' . $time,
                        'end' => $date . ' ' . $time,
                        'type' => 'course_lession'
                    );
                }
                $schedule_unavailable = DB::table('schedule_unavailables as su')
                        ->select('su.*')
                        ->where('su.tutor_id', '=', $tutor_id)
                        ->get();

                if (!empty($schedule_unavailable)) {
                    foreach ($schedule_unavailable as $row) {
                        $timestamp_start = $row->start_date;
                        $timestamp_end = $row->end_date;
                        $date_start = date('Y-m-d', strtotime($timestamp_start));
                        $date_end = date('Y-m-d', strtotime($timestamp_end));
                        $tutor_calendar_array[] = array(
                            'id' => $row->id . '_U',
                            'title' => 'Unavailable Schedule',
                            'start' => $date_start,
                            'end' => $date_end,
                            'type' => 'unavailable_schedule'
                        );
                    }
                }
                if (!empty($tutor_calendar_array)) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Tutor calendar found.',
                        'tutor_calendar_array' => $tutor_calendar_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Tutor calendar not found.',
                        'tutor_calendar_array' => array()
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

    function make_as_favourite_tutor(Request $request) {
        $student_id = trim($request->student_id);
        $tutor_id = trim($request->tutor_id);
        $tutor_role_id = 3;
        $student_role_id = 2;
        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id. '
            );
            return $this->respondWithStatus($response);
        } else if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.'
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
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', $tutor_role_id)
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
                $check_favourite_tutor = DB::table('favourite_tutors as ft')
                        ->select('ft.*')
                        ->where('ft.user_id', '=', $student_info->id)
                        ->where('ft.tutor_id', '=', $tutor_info->id)
                        ->first();
                if (!empty($check_favourite_tutor)) {

                    $id = $check_favourite_tutor->id;
                    $delete_query_res = FavouriteTutor::where('id', '=', $id)->delete();
                    if ($delete_query_res) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Successfully unmarked as favourite tutor.',
                            'data' => array(
                                'tutor_id' => $tutor_info->id,
                                'is_favourite_tutor' => 0,
                            )
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Sorry error in unmarked as favourite tutor.',
                            'data' => array()
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $favourite_tutor = new FavouriteTutor();
                    $favourite_tutor->user_id = $student_info->id;
                    $favourite_tutor->tutor_id = $tutor_info->id;
                    $favourite_tutor->save();
                    if ($favourite_tutor) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Successfully marked as favourite tutor.',
                            'data' => array(
                                'tutor_id' => $tutor_info->id,
                                'is_favourite_tutor' => 1,
                            )
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Sorry error in marked as favourite tutor.',
                            'data' => array()
                        );
                        return $this->respondWithStatus($response);
                    }
                }
            }
        }
    }

    function make_as_unfavourite_tutor(Request $request) {
        $student_id = trim($request->student_id);
        $tutor_id = trim($request->tutor_id);
        $tutor_role_id = 3;
        $student_role_id = 2;
        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id. '
            );
            return $this->respondWithStatus($response);
        } else if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.'
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
            $tutor_info = DB::table('users')
                    ->join("role_users", "users.id", '=', 'role_users.user_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $tutor_id)
                    ->where('role_users.role_id', '=', $tutor_role_id)
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
                $check_favourite_tutor = DB::table('favourite_tutors as ft')
                        ->select('ft.*')
                        ->where('ft.user_id', '=', $student_info->id)
                        ->where('ft.tutor_id', '=', $tutor_info->id)
                        ->first();
                if (!empty($check_favourite_tutor)) {
//                    return;
                    $id = $check_favourite_tutor->id;
                    $delete_query_res = FavouriteTutor::where('id', '=', $id)->delete();
                    if ($delete_query_res) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Successfully unmarked as favourite tutor.',
                            'data' => array(
                                'tutor_id' => $tutor_info->id,
                                'is_favourite_tutor' => 0,
                            )
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Sorry error in unmarked as favourite tutor.',
                            'data' => array()
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Sorry marked as favourite tutor not found.',
                        'data' => array()
                    );
                    return $this->respondWithStatus($response);
                }
            }
        }
    }

    public function get_favourite_tutor_list(Request $request) {
        $role_tutor = 3;
        $page = $request->page;
        $tutor_id = $request->tutor_id;
        $student_id = $request->student_id;
        $page = $request->page;
        $limit = 5; //Limit per page record display
        if ($page == "") {
            $page = 0;
        }

//        $star_ratings = $request->get('search_ratings');
        $price_min = $request->get('price_min');
        $price_max = $request->get('price_max');

        if ($price_max == "") {
            $price_max = 1000;
        }
        if ($price_min == "") {
            $price_min = 0;
        }
        if ($student_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide student id.',
            );
            return $this->respondWithStatus($response);
        }
        //        if ($tutor_id == "") {
//            $response = array(
//                'status' => 'failed',
//                'message' => 'Please provide tutor id.',                
//            );
//            return $this->respondWithStatus($response);
//        }
        else {

            $get_all_favourite_tutor = RoleUser::where('role_id', $role_tutor)
                    ->join('users', 'role_users.user_id', '=', 'users.id')
                    ->join('favourite_tutors', 'role_users.user_id', '=', 'favourite_tutors.tutor_id')
                    ->select(['users.*', 'role_users.*'])
                    ->where('status', 1)
                    ->where('favourite_tutors.user_id', $student_id)
                    ->where('price_per_h', ">=", $price_min)
                    ->where('price_per_h', "<=", $price_max)
                    ->where('users.deleted_at', '=', NULL)
                    ->where('hide_profile', 0)
                    ->where('is_profile_complete', "=", 1)
                    ->groupBy('users.id')
                    ->groupBy('role_users.role_id')
                    ->groupBy('role_users.user_id')
                    ->orderBy('ordering', 'ASC')
//                        ->where("users.id", '=', 200)->get();
//                    ->skip($page * $limit)
//                    ->take($limit)
                    ->get();


            if (count($get_all_favourite_tutor) > 0) {
                $tutor_list_array = array();
                foreach ($get_all_favourite_tutor as $row) {
                    /* start get tutor ratting */
                    $tutor_rating_data = DB::table('ratings')
                            ->leftjoin('role_users', 'role_users.user_id', '=', 'ratings.user_id')
                            ->select('ratings.*')
                            ->where('ratings.module', '=', 'tutor')
                            ->where('ratings.user_id', '=', $row->id)
                            ->avg('point');


                    $tutor_rating_count = 0;
                    if (!empty($tutor_rating_data)) {
                        $tutor_rating_count = round($tutor_rating_data);
                    } else {
                        if ($tutor_rating_count == 0) {
                            $tutor_rating_count = 4;
                            if ($row->id == 3 || $row->id == 4 || $row->id == 7) {
                                $tutor_rating_count = 5;
                            }
                        }
                        //$norating = 5 - $tutor_rating_count;
                    }

                    /* end get tutor ratting */

                    /* Start Code Get marked as favourite Tutor */
                    $get_marked_favourite_tutor = DB::table('favourite_tutors as ft')
                            ->select('ft.*')
                            ->where('user_id', $student_id)
                            ->where('tutor_id', $row->id)
                            ->first();
                    $is_favourite_tutor = 0;
                    if (!empty($get_marked_favourite_tutor)) {
                        $is_favourite_tutor = 1;
                    }
                    /* End Code Get marked as favourite Tutor */


                    $profile_image_url = "";
                    if ($row->image_profile != null) {
                        $profile_image_url = url($row->image_profile);
                    } else {
                        $profile_image_url = url("assets/img/download.png");
                    }
                    $tutor_data = array(
                        'tutor_id' => $row->id,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'profile_image' => $profile_image_url,
                        'price_per_h' => $row->price_per_h,
                        'tutor_rating' => $tutor_rating_count,
                        'tutor_experties' => $row->tutor_experties,
                        'is_favourite_tutor' => $is_favourite_tutor,
                        'tutor_experties_category' => "",
//                    'tutor_experties_category_array' => array(),
                    );

                    /* code start  Tutor experties category  */

                    if ($row->experties_categories != null || $row->experties_categories != "") {
                        $all_experties = explode(",", $row->experties_categories);
                        $categorie_details = DB::table('category_details')
                                ->join('categories', 'category_details.category_id', '=', 'categories.id')
                                ->select('category_details.id as category_detail_id', 'category_details.category_id', 'category_details.name as category_detail_name', 'categories.name as category_name')
                                ->whereIn('category_details.id', $all_experties)
                                ->orderBy('category_id', 'ASC')
                                ->get();
                        //print_r($categorie_details);


                        $old_cat_id = "";
                        $tutor_experties_category = "";
                        $i = 0;
                        foreach ($categorie_details as $cat_detail_row) {
                            if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                                if ($i == 0) {
                                    $tutor_experties_category = $cat_detail_row->category_name;
                                    $i++;
                                } else {
                                    $tutor_experties_category .= ',' . $cat_detail_row->category_name;
                                }
                                /* $tutor_data['tutor_experties_category_array'][] = array(
                                  'category_id' => $cat_detail_row->category_id,
                                  'category_name' => $cat_detail_row->category_name,
                                  'category_detail_array' => array(),
                                  ); */
                            }
                            /* $key2 = array_search($cat_detail_row->category_id, array_column($tutor_data['tutor_experties_category_array'], 'category_id'));
                              $tutor_data['tutor_experties_category_array'][$key2]['category_detail_array'][] = array(
                              'category_detail_id' => $cat_detail_row->category_detail_id,
                              'category_detail_name' => $cat_detail_row->category_detail_name,
                              ); */
                            $old_cat_id = $cat_detail_row->category_id;
                        }
                    }

                    $tutor_data['tutor_experties_category'] = $tutor_experties_category;
                    $tutor_list_array[] = $tutor_data;
                    /* code end Tutor experties category   */
                }
                $response = array(
                    'status' => 'success',
                    'message' => 'Favourite tutor list found.',
                    'tutor_list_array' => $tutor_list_array
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Favourite tutor list not found.',
                    'tutor_list_array' => array()
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}
