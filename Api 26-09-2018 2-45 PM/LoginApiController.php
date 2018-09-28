<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Redirect;
//use Illuminate\Support\Facades\URL;
use App\Notifications\ForgotPassword;
use App\Notifications\NewPassword;
use App\User;
use Sentinel;
use Activation;
use Datatables;
use DB;
use Session;
use Response;
use Socialite;

class LoginApiController extends APIController {

    public function login(Request $request) {

        $email = trim($request->email);
        if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'The email field is required.'
            );
            return $this->respondWithStatus($response);
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = array(
                'status' => 'failed',
                'message' => 'The email must be a valid email address.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'The password field is required.'
            );
            return $this->respondWithStatus($response);
        } else {

            try {
                $params = $request->all();
                $getUser = Sentinel::findByCredentials($params);
                if ($getUser) {
                    $checkctivated = Activation::completed($getUser);
                    if ($checkctivated) {
                        if (Sentinel::authenticate($params)) {
                            $roles = Sentinel::findById($getUser->id)->roles[0];

                            $user = User::find($getUser->id);
                            $user->chat_active = 1;
                            $user->save();
 
//                            DB::table('chat_group_members')
//                                    ->where("user_id", '=', $getUser->id)
//                                    ->update(['email_sent' => 0]);

                            $profile_image_url = "";
                            if ($getUser->image_profile != null) {
                                $profile_image_url = url($getUser->image_profile);
                            } else {
                                $profile_image_url = url("assets/img/download.png");
                            }

                            $user_info = array(
                                'user_id' => $getUser->id,
                                'email' => $getUser->email,
                                'first_name' => $getUser->first_name,
                                'last_name' => $getUser->last_name,
                                'username' => $getUser->first_name . " " . $getUser->last_name,
                                'profile_picture' => $profile_image_url,
                                'user_role_id' => $roles->id,
                                'user_role_name' => $roles->name
                            );
                            $response = array(
                                'status' => 'success',
                                'message' => 'Login success.',
                                'user_info' => $user_info,
                            );
                            return $this->respondWithStatus($response);
                        } else {
                            $response = array(
                                'status' => 'failed',
                                'message' => 'Wrong username or password.'
                            );
                            return $this->respondWithStatus($response);
                        }
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Your registration is still being reviewed.You will be notified by email once your application has been approved.'
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'User not found.'
                    );
                    return $this->respondWithStatus($response);
                }
            } catch (ThrottlingException $e) {
                $minutes = $e->getDelay() / 60 % 60;
                $second = $e->getDelay() % 60;
                return $this->respondWithStatus(['status' => "failed",
                            'message' => 'Too many unsuccessful login attempts your account blocked for ' . $minutes . " Minutes " . $second . " Seconds"
                ]);
            }
        }
    }

    function logout(Request $request) {
        $user_id = $request->user_id;
        if ($user_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide user id.'
            );
            return $this->respondWithStatus($response);
        } else {
            $user = Sentinel::findById($user_id);
            if (!empty($user)) {
                if ($user) {
                    $user->chat_active = 0;
                    $user->save();
                    DB::table('chat_user_sockets')->where('user_id', '=', $user->id)->delete();
                }
                Sentinel::logout();
                $response = array(
                    'status' => 'success',
                    'message' => 'Logout success'
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'User id not found'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function forgot_password(Request $request) {

        $email = trim($request->email);
        if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'The email field is required.'
            );
            return $this->respondWithStatus($response);
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = array(
                'status' => 'failed',
                'message' => 'The email must be a valid email address.'
            );
            return $this->respondWithStatus($response);
        } else {

            $getUser = Sentinel::findByCredentials(['login' => $request->email]);
            if ($getUser) {

                $randString = $this->generateRandomString(6);
                $user = User::find($getUser->id);
                $user->reset_code = '';
                $user->save();

                Sentinel::update($getUser, ['password' => $randString]);
                $user->notify(new NewPassword($user, $randString));
                $response = array(
                    'status' => 'success',
                    'message' => 'New password has been sent; please check your email.'
                );
                return $this->respondWithStatus($response);
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'User not found.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}

?>