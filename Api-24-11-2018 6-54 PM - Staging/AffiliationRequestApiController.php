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
        $sender_tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $sender_tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
                ->first();
        $receiver_tutor_info = DB::table('users')
                ->join("role_users", "users.id", '=', 'role_users.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->where('users.id', '=', $receiver_tutor_id)
                ->where('role_users.role_id', '=', $tutor_role_id)
                ->where('users.deleted_at', '=', NULL)
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
            echo '1';
        } elseif (count($find_me) > 0) {
            echo '2';
        } else {
            DB::table('affiliate_requests')->insert(
                    array(
                        'user_id' => $sender_tutor_info->id,
                        'affliate_partner_id' => $receiver_tutor_info->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'status' => 0,
                    )
            );

            $receiver->notify(new AffiliationRequestMail($sender_tutor_info, $receiver_tutor_info));
            
            
        }
    }

}
