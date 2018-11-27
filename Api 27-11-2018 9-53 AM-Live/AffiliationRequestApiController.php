<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use DB;
use Sentinel;
use Activation;
use Carbon\Carbon;
use App\Notifications\AffiliationRequestMail;
use App\Notifications\AffiliationStatusMail;

class AffiliationRequestApiController extends APIController {

    protected $privatetuition;

    public function __construct(User $user) {

        $this->user = $user;
    }

    public function send_affiliation_request(Request $request) {

        $sender_tutor_id = trim($request->sender_tutor_id);
        $receiver_tutor_id = trim($request->receiver_tutor_id);
        $tutor_role_id = 3;


        if ($sender_tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide sender request tutor id.'
            );
            return $this->respondWithStatus($response);
        }

        if ($receiver_tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide receiver request tutor id.'
            );
            return $this->respondWithStatus($response);
        }
        $sender_tutor_info = User::where('users.id', '=', $sender_tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.*')
                ->first();

        $receiver_tutor_info = User::where('users.id', '=', $receiver_tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.*')
                ->first();




        if (empty($sender_tutor_info)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Sender request tutor not found.'
            );
            return $this->respondWithStatus($response);
        }
        if (empty($receiver_tutor_info)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Receiver request tutor not found.'
            );
            return $this->respondWithStatus($response);
        }

        $find_partner = DB::table('affiliate_requests')
                ->select('id')
                ->where('affliate_partner_id', '=', $receiver_tutor_id)
                ->where('user_id', '=', $sender_tutor_id)
                ->get();

        $find_me = DB::table('affiliate_requests')
                ->select('id')
                ->where('user_id', '=', $receiver_tutor_id)
                ->where('affliate_partner_id', '=', $sender_tutor_id)
                ->get();

        if (count($find_partner) > 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Request has already sent.'
            );
            return $this->respondWithStatus($response);
        } elseif (count($find_me) > 0) {
            $response = array(
                'status' => 'failed',
                'message' => 'Request has already received.'
            );
            return $this->respondWithStatus($response);
        } else {
            $insert_record = DB::table('affiliate_requests')->insert(
                    array(
                        'user_id' => $sender_tutor_info->id,
                        'affliate_partner_id' => $receiver_tutor_info->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => 0,
                    )
            );
            if ($insert_record) {
                $receiver_tutor_info->notify(new AffiliationRequestMail($sender_tutor_info, $receiver_tutor_info));
                $response = array(
                    'status' => 'success',
                    'message' => 'Affiliation request send successfully.'
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'Error in send affiliation request.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    public function get_affiliation_request_receive(Request $request) {
        $tutor_id = trim($request->tutor_id);
        $tutor_role_id = 3;

        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        }
        $tutor_info = User::where('users.id', '=', $tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->first();
        if ($tutor_info == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Tutor not found.'
            );
            return $this->respondWithStatus($response);
        }

        $request_list = DB::table('affiliate_requests')
                ->where('affiliate_requests.status', '=', 0)
                ->where('affliate_partner_id', '=', $tutor_id)
                ->join('users', 'users.id', '=', 'affiliate_requests.user_id')
                ->select('users.email', 'affiliate_requests.id', 'affiliate_requests.status', 'users.first_name', 'users.last_name', 'users.qualifications', 'users.image_profile')
                ->get();

        if (count($request_list) > 0) {
            $affiliation_request_array = array();
            foreach ($request_list as $row) {
                $tutor_image = url("assets/img/download.png");
                if ($row->image_profile != null) {
                    $tutor_image = url($row->image_profile); //"assets/userImage" . $user->image_profile;
                }

                $status_name = "";
                if ($row->status == 1) {
                    $status_name = "Approved";
                } else if ($row->status == 2) {
                    $status_name = "Rejected";
                } else {
                    $status_name = "Waiting To Response";
                }
                $qualifications = "";
                if ($row->qualifications != NULL) {
                    $qualifications = $row->qualifications;
                }
                $affiliation_request_array [] = array(
                    'affiliation_id' => $row->id,
                    'tutor_name' => $row->first_name . ' ' . $row->last_name,
                    'email' => $row->email,
                    'qualifications' => $qualifications,
                    'profile_image' => $tutor_image,
                    'status' => $row->status,
                    'status_name' => $status_name,
                );
                $response = array(
                    'status' => 'success',
                    'message' => 'Affiliation request receive found.',
                    'affiliation_request_array' => $affiliation_request_array
                );
                return $this->respondWithStatus($response);
            }
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Affiliation request not found.'
            );
            return $this->respondWithStatus($response);
        }
    }

    public function get_affiliation_sent_request(Request $request) {
        $tutor_id = trim($request->tutor_id);
        $tutor_role_id = 3;

        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        }
        $tutor_info = User::where('users.id', '=', $tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->first();
        if ($tutor_info == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Tutor not found.'
            );
            return $this->respondWithStatus($response);
        }
        $request_list = DB::table('affiliate_requests')
                ->where('affiliate_requests.status', '=', 0)
                ->where('user_id', '=', $tutor_id)
                ->join('users', 'users.id', '=', 'affiliate_requests.affliate_partner_id')
                ->select('users.email', 'affiliate_requests.id', 'affiliate_requests.status', 'users.first_name', 'users.last_name', 'users.qualifications', 'users.image_profile')
                ->get();

        if (count($request_list) > 0) {
            $affiliation_request_array = array();
            foreach ($request_list as $row) {
                $tutor_image = url("assets/img/download.png");
                if ($row->image_profile != null) {
                    $tutor_image = url($row->image_profile); //"assets/userImage" . $user->image_profile;
                }

                $status_name = "";
                if ($row->status == 1) {
                    $status_name = "Approved";
                } else if ($row->status == 2) {
                    $status_name = "Rejected";
                } else {
                    $status_name = "Pending";
                }
                $qualifications = "";
                if ($row->qualifications != NULL) {
                    $qualifications = $row->qualifications;
                }
                $affiliation_request_array [] = array(
                    'affiliation_id' => $row->id,
                    'tutor_name' => $row->first_name . ' ' . $row->last_name,
                    'email' => $row->email,
                    'qualifications' => $qualifications,
                    'profile_image' => $tutor_image,
                    'status' => $row->status,
                    'status_name' => $status_name,
                );
                $response = array(
                    'status' => 'success',
                    'message' => 'Affiliation sent request found.',
                    'affiliation_request_array' => $affiliation_request_array
                );
                return $this->respondWithStatus($response);
            }
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Affiliation sent request not found.'
            );
            return $this->respondWithStatus($response);
        }
    }

    public function get_affiliate_tutor(Request $request) {
        $tutor_id = trim($request->tutor_id);
        $tutor_role_id = 3;


        if ($tutor_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide tutor id.'
            );
            return $this->respondWithStatus($response);
        }
        $tutor_info = User::where('users.id', '=', $tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->first();
        if ($tutor_info == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Tutor not found.'
            );
            return $this->respondWithStatus($response);
        }
        $receive_request_list = DB::table('affiliate_requests')
                ->where('affiliate_requests.status', '=', 1)
                ->where('affliate_partner_id', '=', $tutor_id)
                ->join('users', 'users.id', '=', 'affiliate_requests.user_id')
                ->select('users.email', 'users.id as tutor_id', 'users.experties_categories', 'users.tutor_experties', 'users.price_per_h', 'affiliate_requests.id', 'affiliate_requests.user_id', 'affiliate_requests.affliate_partner_id', 'affiliate_requests.status', 'users.first_name', 'users.last_name', 'users.qualifications', 'users.image_profile')
                ->get();
        $receive_request_list = json_decode(json_encode($receive_request_list));

        $send_request_list = DB::table('affiliate_requests')
                ->where('affiliate_requests.status', '=', 1)
                ->where('user_id', '=', $tutor_id)
                ->join('users', 'users.id', '=', 'affiliate_requests.affliate_partner_id')
                ->select('users.email', 'users.id as tutor_id', 'users.experties_categories', 'users.tutor_experties', 'users.price_per_h', 'affiliate_requests.id', 'affiliate_requests.user_id', 'affiliate_requests.affliate_partner_id', 'affiliate_requests.status', 'users.first_name', 'users.last_name', 'users.qualifications', 'users.image_profile')
                ->get();
        $send_request_list = json_decode(json_encode($send_request_list));

        $affiliate_array = array_merge($receive_request_list, $send_request_list);

        if (count($affiliate_array) > 0) {
            $affiliate_tutor_array = array();
            foreach ($affiliate_array as $row) {
                $tutor_image = url("assets/img/download.png");
                if ($row->image_profile != null) {
                    $tutor_image = url($row->image_profile); //"assets/userImage" . $user->image_profile;
                }
                $qualifications = "";
                if ($row->qualifications != NULL) {
                    $qualifications = $row->qualifications;
                }

                $tutor_experties_category = "";
                if ($row->experties_categories != null || $row->experties_categories != "") {
                    $all_experties = explode(",", $row->experties_categories);
                    $categorie_details = DB::table('category_details')
                            ->join('categories', 'category_details.category_id', '=', 'categories.id')
                            ->select('category_details.id as category_detail_id', 'category_details.category_id', 'category_details.name as category_detail_name', 'categories.name as category_name')
                            ->whereIn('category_details.id', $all_experties)
                            ->orderBy('category_id', 'ASC')
                            ->get();
                    $old_cat_id = "";
                    $i = 0;
                    foreach ($categorie_details as $cat_detail_row) {
                        if ($old_cat_id == "" || $old_cat_id != $cat_detail_row->category_id) {
                            if ($i == 0) {
                                $tutor_experties_category = $cat_detail_row->category_name;
                                $i++;
                            } else {
                                $tutor_experties_category .= ',' . $cat_detail_row->category_name;
                            }
                        }
                        $old_cat_id = $cat_detail_row->category_id;
                    }
                }
                $tutor_experties = "";
                if ($row->tutor_experties != null || $row->tutor_experties != "") {
                    $tutor_experties = $row->tutor_experties;
                }
                $price_per_h = 0;
                if ($row->price_per_h != null || $row->price_per_h != "") {
                    $price_per_h = $row->price_per_h;
                }
                $affiliate_tutor_array [] = array(
                    'affiliation_id' => $row->id,
                    'tutor_id' => $row->tutor_id,
                    'tutor_name' => $row->first_name . ' ' . $row->last_name,
                    'email' => $row->email,
                    'qualifications' => $qualifications,
                    'profile_image' => $tutor_image,
                    'tutor_experties_category' => $tutor_experties_category,
                    'tutor_experties' => $tutor_experties,
                    'price_per_h' => $price_per_h
                );
            }
            $response = array(
                'status' => 'success',
                'message' => 'Affiliate tutor list found.',
                'affiliate_tutor_array' => $affiliate_tutor_array
            );
            return $this->respondWithStatus($response);
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Affiliate tutor list not found.'
            );
            return $this->respondWithStatus($response);
        }
    }

    public function affiliation_request_confirm(Request $request) {
        $affiliation_id = trim($request->affiliation_id);
        $status = trim($request->status);


        if ($affiliation_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide affiliation id.'
            );
            return $this->respondWithStatus($response);
        }
        if ($status == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide status.'
            );
            return $this->respondWithStatus($response);
        }
        $request_data = DB::table('affiliate_requests as ar')
                ->where('ar.id', '=', $affiliation_id)
                ->join('users as partner', 'partner.id', '=', 'ar.affliate_partner_id')
                ->join('users as sender', 'sender.id', '=', 'ar.user_id')
                ->select('sender.email as sender_email', 'partner.email as partner_email', 'ar.id', 'ar.user_id', 'ar.affliate_partner_id', 'ar.status', 'sender.first_name as sfn', 'sender.last_name as sln', 'partner.first_name as pfn', 'partner.last_name as pln')
                ->first();

        if (!empty($request_data)) {

            $update_rec = DB::table('affiliate_requests')
                    ->where('id', $affiliation_id)
                    ->update(['status' => $status]);

            $user = $this->user->find($request_data->user_id);
            $status_message = "";
            if ($status == 1) {
                $status_message = "Affiliation request approved successfully.";
                $user->notify(new AffiliationStatusMail($request_data, 'Approved'));
            } else if ($status == 2) {
                $status_message = "Affiliation request Rejected successfully.";
                $user->notify(new AffiliationStatusMail($request_data, 'Rejected'));
            }

            $response = array(
                'status' => 'success',
                'message' => $status_message
            );
            return $this->respondWithStatus($response);
        } else {
            $response = array(
                'status' => 'failed',
                'message' => 'Affiliation request not found.'
            );
            return $this->respondWithStatus($response);
        }
    }

}
