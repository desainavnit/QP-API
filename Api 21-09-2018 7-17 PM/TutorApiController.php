<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Category;
use App\Models\User;
use App\Models\RoleUser;
use App\Models\CourseCategory;
use App\Models\CategoryDetail;
use App\Models\Rating;
use App\Models\Course;
use App\Models\PrivateTuition;
use App\Models\ScheduleUnavailable;
use Sentinel;
use DB;

class TutorApiController extends APIController {

    public function get_tutor_list(Request $request) {
        $role_tutor = 3;
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
                ->skip($page * $limit)
                ->take($limit)
//                ->paginate(5);
                ->get();
        //Here take(5)  Perpage 5 record
        
//        $categories = DB::table('categories')
//                ->select('categories.id', 'categories.name')
//                ->orderBy('order', 'asc')
//                ->get();        
//        print_r($get_all_tutor);

        if (!empty($get_all_tutor)) {
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

                $profile_image_url = "";
                if ($row->image_profile != null) {
                    $profile_image_url = url($row->image_profile);
                } else {
                    $profile_image_url = url("assets/img/download.png");
                }
                $tutor_data = array(
                    'user_id' => $row->id,
                    'first_name' => $row->first_name,
                    'last_name' => $row->last_name,
                    'profile_image' => $profile_image_url,
                    'price_per_h' => $row->price_per_h,
                    'tutor_rating' => $tutor_rating_count,
                    'tutor_experties' => $row->tutor_experties,
                    'tutor_profile'=>$row->profile,
                    'qualifications' => $row->qualifications,
                    'tutor_experties_category_array' => array(),
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
                    foreach ($categorie_details as $cat_detail_row) {
                        if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                            $tutor_data['tutor_experties_category_array'][] = array(
                                'category_id' => $cat_detail_row->category_id,
                                'category_name' => $cat_detail_row->category_name,
                                'category_detail_array' => array(),
                            );
                        }
                        $key2 = array_search($cat_detail_row->category_id, array_column($tutor_data['tutor_experties_category_array'], 'category_id'));
                        $tutor_data['tutor_experties_category_array'][$key2]['category_detail_array'][] = array(
                            'category_detail_id' => $cat_detail_row->category_detail_id,
                            'category_detail_name' => $cat_detail_row->category_detail_name,
//                  'category_name' => $cat_detail_row->category_name,
                        );
                        $old_cat_id = $cat_detail_row->category_id;
                    }
                }
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


            if (!empty($get_one_tutor)) {
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

                $profile_image_url = "";
                if ($get_one_tutor->image_profile != null) {
                    $profile_image_url = url($get_one_tutor->image_profile);
                } else {
                    $profile_image_url = url("assets/img/download.png");
                }
                $tutor_data = array(
                    'user_id' => $get_one_tutor->id,
                    'first_name' => $get_one_tutor->first_name,
                    'last_name' => $get_one_tutor->last_name,
                    'profile_image' => $profile_image_url,
                    'price_per_h' => $get_one_tutor->price_per_h,
                    'tutor_rating' => $tutor_rating_count,
                    'tutor_experties' => $get_one_tutor->tutor_experties,
                    'tutor_profile'=>$get_one_tutor->profile,
                    'qualifications' => $get_one_tutor->qualifications,
                    'tutor_experties_category_array' => array(),
                    'tutor_courses' => $tutor_course_array
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
                    foreach ($categorie_details as $cat_detail_row) {
                        if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                            $tutor_data['tutor_experties_category_array'][] = array(
                                'category_id' => $cat_detail_row->category_id,
                                'category_name' => $cat_detail_row->category_name,
                                'category_detail_array' => array(),
                            );
                        }
                        $key2 = array_search($cat_detail_row->category_id, array_column($tutor_data['tutor_experties_category_array'], 'category_id'));
                        $tutor_data['tutor_experties_category_array'][$key2]['category_detail_array'][] = array(
                            'category_detail_id' => $cat_detail_row->category_detail_id,
                            'category_detail_name' => $cat_detail_row->category_detail_name,
//                  'category_name' => $cat_detail_row->category_name,
                        );
                        $old_cat_id = $cat_detail_row->category_id;
                    }
                }
                $tutor_detail_array[] = $tutor_data;
                /* code end Tutor experties category   */
                $response = array(
                    'status' => 'success',
                    'message' => 'Tutor found.',
                    'tutor_detail_array' => $tutor_detail_array
                );
                return $this->respondWithStatus($response);
//            print_r($tutor_list_array);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                    'tutor_list_array' => array()
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}
