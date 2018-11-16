<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Models\FavouriteTutor;
use App\Models\User;
use App\Models\RoleUser;
use App\Models\Rating;
use App\Models\TutorQA;
use Response;
use DB;

class TutorApiController extends APIController {

    protected $tutor_qa, $tutor;

    public function __construct(TutorQA $tutor_qa, User $tutor) {
        $this->tutor_qa = $tutor_qa;
        $this->tutor = $tutor;
    }

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
                    ->where('status','=',1)
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

    public function get_tutor_list_for_search(Request $request) {
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

                return response()->json(
                                $tutor_list_array
                );
            } else {
                return response()->json(
                );
            }
        }
    }

    public function get_one_tutor(Request $request) {
        $role_tutor = 3;
        $role_student = 2;
        $tutor_id = trim($request->tutor_id);
        $student_id = trim($request->student_id);
        $user_role_id = trim($request->user_role_id);

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

                /* start Code check code for student purchase course or private tution and give rate to tutor */
                $purchased = "";
                $is_paid = "";
                $is_rated = 0;
                $course_order_status = DB::table('orders')
                        ->select("orders.*")
                        ->where("student_id", '=', $student_id)
                        ->where("status", '=', 1)
                        ->get();

                $private_tution_order_status = DB::table('private_tuitions')
                        ->select("private_tuitions.*")
                        ->where("user_id", '=', $student_id)
                        ->where("tutor_id", '=', $tutor_id)
                        ->where("status", '=', 1)
                        ->where("payment_status", '=', 1)
                        ->get();

                //print_r($private_tution_order_status); 
                //print_r($course_order_status);  
                //echo count($private_tution_order_status) . "..." . count($course_order_status);
                if (count($private_tution_order_status) > 0) {
                    $purchased = "True";
                    $is_paid = "True";
                } else if (count($course_order_status) > 0) {
                    $purchased = "True";
                    $is_paid = "True";
                } else {
                    $purchased = "False";
                    $is_paid = "False";
                }

                $raing_info = DB::table('ratings')
                        ->select('id', 'module', 'student_id', 'course_id')
                        ->where('user_id', '=', $tutor_id)
                        ->where('student_id', '=', $student_id)
                        ->where('module', '=', 'tutor')
                        ->get();
                if (count($raing_info) > 0) {
                    $is_rated = 1;
                }

                /* end Code check code for student purchase course or private tution and give rate to tutor */

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
                    'purchased' => $purchased,
                    'is_paid' => $is_paid,
                    'is_rated' => $is_rated
//                    'tutor_experties_category_array' => array()
                );

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
                    $tutor_experties_category_array = array();
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
                        ->join("course_lessons as cl", 'courses.id', '=', 'cl.course_id')
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


                /* start code for get tutor review code */

                $tutor_reviews = DB::table('ratings')
                        ->leftjoin('users', 'users.id', '=', 'ratings.student_id')
                        ->select("ratings.*", 'users.first_name', 'users.last_name', 'users.image_profile')
                        ->where('user_id', $tutor_id)
                        ->where('module', 'tutor')
                        ->get();
                if (count($tutor_reviews) > 0) {
                    foreach ($tutor_reviews as $tr) {
                        if ($tr->image_profile != null) {
                            $user_image_url = url($tr->image_profile);
                        } else {
                            $user_image_url = url("assets/img/download.png");
                        }
                        $name = "";
                        if ($tr->first_name == "" || $tr->last_name == "") {
                            $name = "Qualpros";
                        } else {
                            $name = $tr->first_name . " " . $tr->last_name;
                        }
                        $tutor_review_array[] = array(
                            'review_id' => $tr->id,
                            'name' => $name,
                            'profile_picture' => $user_image_url,
                            'point' => $tr->point,
                            'comment' => $tr->comment,
                            'date' => date("Y-m-d", strtotime($tr->created_at))
                        );
                    }
                }

                /* end code for get tutor review code */

                /* start code for tutor Q & AS */

//                $tutor_q_as_first_step = DB::table('tutor_q_as as tqas')
//                        ->join('users', 'users.id', '=', 'tqas.creator_id')
//                        ->select("tqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
//                        ->where('tqas.tutor_id', '=', $tutor_id)
//                        ->where('tqas.parent_id', '=', NULL)
//                        ->orderby('tqas.id', 'desc')
//                        ->get();
                $tutor_q_as_array = array();
                $tutor_q_as_first_step = $this->tutor->find($tutor_id)->tutor_qas()->where('parent_id', '=', null)->orderBy('created_at', 'desc')->get();
                if (count($tutor_q_as_first_step) > 0) {
                    foreach ($tutor_q_as_first_step as $t_qas) {



                        $user_image_url1 = "";
                        if ($t_qas->image_profile != null) {
                            $user_image_url1 = url($t_qas->image_profile);
                        } else {
                            $user_image_url1 = url("assets/img/download.png");
                        }
                        $is_edit = "False";
                        if ($user_role_id == 1 || $user_role_id == 3) {
                            if ($tutor_id == $t_qas->creator_id) {
                                $is_edit = "True";
                            }
                        } else {
                            if ($student_id == $t_qas->creator_id) {
                                $is_edit = "True";
                            }
                        }
                        $tutor_q_as_array[] = array(
                            'id' => $t_qas->id,
                            'name' => $t_qas->user->first_name . " " . $t_qas->user->last_name,
                            'profile_picture' => $user_image_url1,
                            'course_q_as' => $t_qas->content,
                            'parent_id' => 0,
                            'date_time' => comment_date($t_qas->created_at),
                            'is_edit' => $is_edit
                        );

                        if (count($t_qas->children) > 0) {
                            foreach ($t_qas->children()->orderBy('created_at', 'desc')->get() as $key => $child) {
                                $user_image_url2 = getProfilePicture($child->user->id);

                                $tutor_q_as_array[] = array(
                                    'id' => $child->id,
                                    'name' => $child->user->first_name . " " . $child->user->last_name,
                                    'profile_picture' => $user_image_url2,
                                    'tutor_q_as' => $child->content,
                                    'parent_id' => $child->parent_id,
                                    'date_time' => comment_date($child->created_at),
                                    'is_edit' => "False"
                                );
                            }
                        }
                        /* $tutor_q_as_second_step = DB::table('tutor_q_as as tqas')
                          ->join('users', 'users.id', '=', 'tqas.creator_id')
                          ->select("tqas.*", 'users.id as user_id', 'users.first_name', 'users.last_name', 'users.image_profile')
                          ->where('tqas.tutor_id', '=', $t_qas->tutor_id)
                          ->where('tqas.parent_id', '=', $t_qas->id)
                          ->orderby('tqas.id', 'desc')
                          ->get();
                          foreach ($tutor_q_as_second_step as $t_qas_row) {
                          $user_image_url2 = "";
                          if ($t_qas_row->image_profile != null) {
                          $user_image_url2 = url($t_qas_row->image_profile);
                          } else {
                          $user_image_url2 = url("assets/img/download.png");
                          }
                          $tutor_q_as_array[] = array(
                          'id' => $t_qas_row->id,
                          'name' => $t_qas_row->first_name . " " . $t_qas_row->last_name,
                          'profile_picture' => $user_image_url2,
                          'tutor_q_as' => $t_qas_row->content,
                          'parent_id' => $t_qas_row->parent_id,
                          'date_time' => $t_qas_row->created_at,
                          'is_edit' => "False"
                          );
                          } */
                    }
                }


                /* end code for tutor Q & AS */


                $response = array(
                    'status' => 'success',
                    'message' => 'Tutor found.',
                    'tutor_detail_array' => $tutor_detail_array,
                    'tutor_course_array' => $tutor_course_array,
                    'tutor_experties_category_array' => $tutor_experties_category_array,
                    'tutor_review_array' => $tutor_review_array,
                    'tutor_q_as_array' => $tutor_q_as_array
                );
                return $this->respondWithStatus($response);
//            print_r($tutor_list_array);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                    'tutor_detail_array' => array(),
                    'tutor_course_array' => array(),
                    'tutor_experties_category_array' => array(),
                    'tutor_review_array' => array()
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
            $tutor_private_tution_array = array();
            $tutor_schedule_unavailable_array = array();
            $tutor_private_tution_date_array = array();
            $tutor_schedule_unavailable_date_array = array();

            if (!empty($tutor_info)) {

                $private_tution = DB::table('private_tuitions as pt')
                        ->select('pt.*')
                        ->where('pt.tutor_id', '=', $tutor_id)
                        ->where('pt.status', '=', 1)
                        ->where('pt.payment_status', '=', 1)
                        ->get();
                if (!empty($private_tution)) {
                    foreach ($private_tution as $pt_row) {
                        $start_date = $pt_row->start_date;
                        $start_date_time = date('Y-m-d H:i:s', strtotime($start_date));
//                        $end_time = strtotime('+' . $pt_row->duration . ' hour');
                        $end_date_time = date('Y-m-d H:i:s', strtotime($start_date) + 60 * 60 * $pt_row->duration);
                        $tutor_private_tution_array[] = array(
                            'id' => $pt_row->id,
                            'title' => $pt_row->title,
                            'start' => $start_date_time,
                            'end' => $end_date_time,
                            'type' => 'private_tution'
                        );
                        array_push($tutor_private_tution_date_array, date("Y-m-d", strtotime($start_date)));
                    }
                }

                $schedule_unavailable = DB::table('schedule_unavailables as su')
                        ->select('su.*')
                        ->where('su.tutor_id', '=', $tutor_id)
                        ->get();

                if (!empty($schedule_unavailable)) {
                    foreach ($schedule_unavailable as $row) {
                        $timestamp_start = $row->start_date;
                        $timestamp_end = $row->end_date;
                        $date_start = date('Y-m-d H:i:s', strtotime($timestamp_start));
                        $date_end = date('Y-m-d H:i:s', strtotime($timestamp_end));
                        $tutor_schedule_unavailable_array[] = array(
                            'id' => $row->id,
                            'title' => 'Unavailable Schedule',
                            'start' => $date_start,
                            'end' => $date_end,
                            'type' => 'unavailable_schedule'
                        );

                        for ($i = strtotime($timestamp_start); $i <= strtotime($timestamp_end); $i+=86400) {
                            $date = date("Y-m-d", $i);
                            array_push($tutor_schedule_unavailable_date_array, $date);
                        }
                    }
                }
                if (!empty($tutor_private_tution_array) || !empty($tutor_schedule_unavailable_array)) {
                    $response = array(
                        'status' => 'success',
                        'message' => 'Tutor calendar found.',
                        'tutor_private_tution_array' => $tutor_private_tution_array,
                        'tutor_schedule_unavailable_array' => $tutor_schedule_unavailable_array,
                        'tutor_private_tution_date_array' => $tutor_private_tution_date_array,
                        'tutor_schedule_unavailable_date_array' => $tutor_schedule_unavailable_date_array
                    );
                    return $this->respondWithStatus($response);
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Tutor calendar not found.',
                        'tutor_private_tution_array' => array(),
                        'tutor_schedule_unavailable_array' => array(),
                        'tutor_private_tution_date_array' => array(),
                        'tutor_schedule_unavailable_date_array' => array()
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

    function post_tutor_rating(Request $request) {
        $tutor_id = trim($request->tutor_id);
        $student_id = trim($request->student_id);
        $point = trim($request->point);
        $comment = trim($request->comment);

        $student_role_id = 2;
        $tutor_role_id = 3;

        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.',
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
        if (count($tutor_info) == 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Tutor not found.',
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

        $tutor_rating_info = DB::table('ratings')
                ->select('id', 'module', 'student_id', 'course_id')
                ->where('student_id', '=', $student_id)
                ->where('user_id', '=', $tutor_id)
                ->where('module', '=', 'tutor')
                ->first();
        if (count($tutor_rating_info) == 0) {
            $rating = new Rating();
            $rating->module = "tutor";
            $rating->user_id = $tutor_id;
            $rating->point = $point;
            $rating->comment = $comment;
            $rating->student_id = $student_id;
            $rating->save();
            if ($rating) {
                $response = array(
                    'status' => 'success',
                    'message' => 'Thank you for tutor rating.',
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
