<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\APIController;
use Illuminate\Http\Request;
use App\Notifications\ForgotPassword;
use App\Notifications\NewPassword;
use App\User;
use App\Models\NotificationUser;
use Sentinel;
use Activation;
use Datatables;
use DB;
use Session;
use Response;
use Socialite;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;

class LoginApiController extends APIController {

    public function login(Request $request) {

        $email = trim($request->email);
        $device_id = trim($request->device_id);
        $device_type = trim($request->device_type);
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
        } else if ($device_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide device id.'
            );
            return $this->respondWithStatus($response);
        } else if ($device_type == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide device type.'
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
                            $notification_data_exist = DB::table('notification_users')
                                    ->where('user_id', '=', $getUser->id)
                                    ->where('device_id', '=', $device_id)
                                    ->exists();
                            if ($notification_data_exist == 0) {
                                $ins_notification_data = new NotificationUser();
                                $ins_notification_data->user_id = $getUser->id;
                                $ins_notification_data->device_id = $device_id;
                                $ins_notification_data->device_type = $device_type;
                                $ins_notification_data->save();
                            }


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
        $device_id = trim($request->device_id);

        if ($user_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide user id.'
            );
            return $this->respondWithStatus($response);
        } else if ($device_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide device id.'
            );
            return $this->respondWithStatus($response);
        } else {
            $user = Sentinel::findById($user_id);
            if (!empty($user)) {
                if ($user) {
                    $user->chat_active = 0;
                    $user->save();
                    DB::table('im_usersocket')->where('userid', '=', $user->id)->delete();
                    DB::table('notification_users')->where('user_id', '=', $user->id)->where('device_id', '=', $device_id)->delete();
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

    public function linkedin_login(Request $request) {
        $email = trim($request->email);
        $linkedin_id = trim($request->linkedin_id);
        if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide email id.'
            );
            return $this->respondWithStatus($response);
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = array(
                'status' => 'failed',
                'message' => 'The email must be a valid email address.'
            );
            return $this->respondWithStatus($response);
        } else if ($linkedin_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide linked in id.'
            );
            return $this->respondWithStatus($response);
        }

        try {

            $user_data = User::where('email', '=', $email)
//                    ->orWhere('linkedin_id', '=', $linkedin_id)
                    ->first();
            if ($user_data) {
                $found_linkedin_data = User::where('linkedin_id', '=', $linkedin_id)->first();
                if (!$found_linkedin_data) {
                    User::where('id', $user_data->id)->update(array('linkedin_id' => $linkedin_id));
                }
                $getUser = Sentinel::findById($user_data->id);
                $getRoles = Sentinel::findById($user_data->id)->roles()->first();

                if ($getUser) {
                    $checkctivated = Activation::completed($getUser);
                    if ($checkctivated) {

                        $user = User::find($getUser->id);
                        $user->chat_active = 1;
                        $user->save();

                        DB::table('im_group_members')
                                ->where("u_id", '=', $getUser->id)
                                ->update(['email_sent' => 0]);

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
                            'user_role_id' => $getRoles->id,
                            'user_role_name' => $getRoles->name
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
                            'message' => 'Your Qualpros account is not yet activated, Please check your email to activate your account.if you have sign up as tutor and activated your account, Please wait for Qualpros to approve your tutor account.'
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
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => "I'm sorry we could not confirm your registration, in order to continue with LinkedIn registration and login please make sure you use the same email address associated with your LinkedIn account."
                );
                return $this->respondWithStatus($response);
            }
        } catch (Exception $e) {
            $response = array(
                'status' => 'failed',
                'message' => 'Error something went wrong.'
            );
            return $this->respondWithStatus($response);
        }
    }

    function update_notification_setting(Request $request) {
        $user_id = trim($request->user_id);
        $notification_setting = trim($request->notification_setting);
        if ($user_id == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide user id.'
            );
            return $this->respondWithStatus($response);
        } else if ($notification_setting == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please provide notification setting.'
            );
            return $this->respondWithStatus($response);
        } else {
            $user_info = DB::table('users')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->where('users.id', '=', $user_id)
                    ->where('users.deleted_at', '=', NULL)
                    ->first();
            if (!empty($user_info)) {

                $notification_setting_result = DB::table('notification_users')
                        ->select('notification_users.*')
                        ->where('user_id', '=', $user_id)
                        ->first();
                if (!empty($notification_setting_result)) {
                    $update_notification_setting = DB::table('notification_users')
                            ->where('user_id', $user_id)
                            ->update([
                        'notification_setting' => $notification_setting,
                        'updated_at' => date("Y-m-d H:i:s")
                    ]);

                    if ($update_notification_setting) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Notification setting updated successfully.'
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Sorry error in notification setting update.'
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Notification setting not found.'
                    );
                    return $this->respondWithStatus($response);
                }
            } else {
                $response = array(
                    'status' => 'failed',
                    'message' => 'user not found.'
                );
                return $this->respondWithStatus($response);
            }
        }
    }

}

?>