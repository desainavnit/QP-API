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

class TutorProfileApiController extends APIController {

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

    public function get_tutor_profile(Request $request) {

        $role_tutor = 3;
        $tutor_id = trim($request->tutor_id);

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
                    ->where('users.deleted_at', '=', NULL)
                    ->where("users.id", $tutor_id)
                    ->first();

            if (count($get_one_tutor) > 0) {

                $tutor_detail_array = array();

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
                    'email'=>$get_one_tutor->email,
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


                $response = array(
                    'status' => 'success',
                    'message' => 'Tutor found.',
                    'tutor_detail_array' => $tutor_detail_array,
                    'tutor_experties_category_array' => $tutor_experties_category_array,
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Tutor not found.',
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}
