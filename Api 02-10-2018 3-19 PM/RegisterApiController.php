<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Redirect;
//use Illuminate\Support\Facades\URL;
use App\Models\Rating;
use Sentinel;
use Activation;
use Datatables;
use DB;
//use Session;
use Response;
use Validator;
use App\Models\User;
use App\Notifications\RegisterSendMail;
//use Socialite;
use File;

class RegisterApiController extends APIController {

    protected $users;

    public function __construct(User $users) {
        $this->users = $users;
    }

    function student_register(Request $request) {

        $email = trim($request->email);
        $linkedin_id = trim($request->linkedin_id);
        $role_id = 2;
        if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add email below.'
            );
            return $this->respondWithStatus($response);
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = array(
                'status' => 'failed',
                'message' => 'The email must be a valid email address.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->first_name) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add first name below.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->last_name) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add your last name below.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->password)) < "5") {
            $response = array(
                'status' => 'failed',
                'message' => 'password must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->confirmation_password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please confirm your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->confirmation_password)) < "5") {
            $response = array(
                'status' => 'failed',
                'message' => 'Confirm password must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) != trim($request->confirmation_password)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Password and Confirm Password does not match.'
            );
            return $this->respondWithStatus($response);
        } else {
            $check_user_email_duplicate = DB::table('users')
                            ->where('email', '=', $email)
                            ->where('deleted_at', '=', NULL)->exists();

            if ($linkedin_id != "") {
                $check_user_linkedin_id = DB::table('users')
                                ->where('linkedin_id', '=', $linkedin_id)
                                ->where('deleted_at', '=', NULL)->exists();

                if ($check_user_linkedin_id >= 1) {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Linkedin id already exist.'
                    );
                    return $this->respondWithStatus($response);
                }
            }


            if ($check_user_email_duplicate >= 1) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The email has already been taken.'
                );
                return $this->respondWithStatus($response);
            } else {

                $data = $request->input();
                $student_register = Sentinel::register($data);
                if ($student_register) {
                    $user = Sentinel::findByCredentials($data);
                    $activationCreated = Activation::create($user);
                    $id = $user->id;
                    $getCodeActivated = Activation::exists($user);

                    $getUser = $this->users->find($id);
                    $getUser->notify(new RegisterSendMail($getUser, $getCodeActivated['code'], $role_id));

                    $slug = title_based_url($user->first_name . '-' . $user->last_name);
                    $find_slug = User::where("slug", "=", $slug)->first();
                    if ($find_slug) {
                        $slug .= "-" . $id;
                    }
                    $updateUser = User::where('id', $id)->first();
                    $updateUser->slug = $slug;
                    if ($linkedin_id != "") {
                        $updateUser->linkedin_id = $linkedin_id;
                    }
                    $updateUser->accept_terms = 1;
                    $updateUser->accept_privacy = 1;
                    $updateUser->update();

                    if ($user) {
                        $user->roles()->attach($role_id);
                    }
                    if ($student_register) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Success. To finalise your registration, please click on the confirmation link sent to your email address.'
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Error. Something went wrong.'
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Error. Something went wrong.'
                    );
                    return $this->respondWithStatus($response);
                }
            }
        }
    }

    function tutor_register(Request $request) {

        $email = trim($request->email);
        $role_id = 3;
        $profile_image = $request->file('image_profile');
        $profile_image_ext = "";
        $profile_image_size = 0;
        if (!empty($profile_image)) {
            $profile_image_ext = strtolower($profile_image->getClientOriginalExtension());
            $profile_image_size_ori = $profile_image->getSize();
        }
        echo $profile_image_size;
        return;

        $file_cv = $request->file('file_cv');
        $file_cv_ext = '';
        if (!empty($file_cv)) {
            $file_cv_ext = strtolower($file_cv->getClientOriginalExtension());
        }
        echo $file_cv_ext;
        return;
//        echo $file_cv_ext;
//         $imageFileType = pathinfo(basename($file_cv), PATHINFO_EXTENSION);
//        return;
        //$extension = $file_cv->getClientOriginalExtension();
        //dd($extension);
//        echo $file_cv_ext;
        //$file = $request->file('file_cv');
//        $file_cv_ext = strtolower(pathinfo($file_cv, PATHINFO_EXTENSION))originalName;
//        dd($file);
        //$path = $request->file_cv->getClientOriginalName();
//        echo $path;
        //return; 

        $allow_image_ext = array("jpeg", "png", "jpg", "gif");

        if (trim($request->first_name) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add first name below.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->last_name) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add your last name below.'
            );
            return $this->respondWithStatus($response);
        } else if ($email == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please add email below.'
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
                'message' => 'Please add your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->password)) < "5") {
            $response = array(
                'status' => 'failed',
                'message' => 'password must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->confirmation_password) == "") {
            $response = array(
                'status' => 'failed',
                'message' => 'Please confirm your password below.'
            );
            return $this->respondWithStatus($response);
        } else if (strlen(trim($request->confirmation_password) < "5")) {
            $response = array(
                'status' => 'failed',
                'message' => 'Confirm must be 5 character.'
            );
            return $this->respondWithStatus($response);
        } else if (trim($request->password) != trim($request->confirmation_password)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Password and Confirm Password does not match.'
            );
            return $this->respondWithStatus($response);
        } else if (empty($request->image_profile)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Please upload profile picture below.'
            );
            return $this->respondWithStatus($response);
        } else if (empty($request->file_cv)) {
            $response = array(
                'status' => 'failed',
                'message' => 'Please upload your CV below.'
            );
            return $this->respondWithStatus($response);
        } else if ($file_cv_ext != "pdf") {
            $response = array(
                'status' => 'failed',
                'message' => 'The file cv must be a file of type: pdf.'
            );
            return $this->respondWithStatus($response);
        } else {

            $check_user_email_duplicate = DB::table('users')
                            ->where('email', '=', $email)
                            ->where('deleted_at', '=', NULL)->exists();
            if ($check_user_email_duplicate >= 1) {
                $response = array(
                    'status' => 'failed',
                    'message' => 'The email has already been taken.'
                );
                return $this->respondWithStatus($response);
            } else {
                $data = $request->input();
                $tutor_register = Sentinel::register($data);
                if ($tutor_register) {
                    if (!empty($request->file_cv)) {
                        $upload = upload_file($request->file_cv, 'file/tutor/', 'file');
                        $data['file_cv'] = '/' . $upload['original'];
                    }
//                if (!empty($request->image_profile)) {
//                    $this->validate($request, [
//                        'image_profile' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
//                    ]);
//                    $upload_profile = $upload_profile = upload_profile_image($request->image_profile, 'images/profiles/', $x1, $y1, $w1, $h1, $div_w, $div_h);
//                    $data['image_profile'] = '/' . $upload_profile['thumbnail'];
//                }
                    //$data['accept_privacy'] = ($data['accept_privacy']) ? 1 : 0;
                    //$data['accept_terms'] = ($data['accept_privacy']) ? 1 : 0;
                    $user = Sentinel::findByCredentials($data);
                    $activationCreated = Activation::create($user);
                    $id = $user->id;
                    $getCodeActivated = Activation::exists($user);

                    if ($role_id == 3) {
                        $rating = new Rating();
                        $countRating = 4;
                        $data_ratings = new Rating();
                        $data_ratings->user_id = $user->id;
                        $data_ratings->comment = '';
                        $data_ratings->student_id = 0;
                        $data_ratings->module = 'tutor';
                        $data_ratings->point = $countRating;
                        $data_ratings->save();
                    }


                    $slug = title_based_url($user->first_name . '-' . $user->last_name);
                    $find_slug = User::where("slug", "=", $slug)->first();
                    if ($find_slug) {
                        $slug .= "-" . $id;
                    }
                    $updateUser = User::where('id', $id)->first();
                    $updateUser->slug = $slug;
//                if ($request->type_account == 'tutor') {
                    $updateUser->file_cv = $data['file_cv'];
//                    $updateUser->image_profile = $data['image_profile'];
//                }
                    $updateUser->accept_terms = 1;
                    $updateUser->accept_privacy = 1;
                    $updateUser->update();

                    if ($user) {
                        $user->roles()->attach($role_id);
                    }
                    if ($tutor_register) {
                        $response = array(
                            'status' => 'success',
                            'message' => 'Registration successful. You will be notified by email once your application has been approved.'
                        );
                        return $this->respondWithStatus($response);
                    } else {
                        $response = array(
                            'status' => 'failed',
                            'message' => 'Error. Something went wrong.'
                        );
                        return $this->respondWithStatus($response);
                    }
                } else {
                    $response = array(
                        'status' => 'failed',
                        'message' => 'Error. Something went wrong.'
                    );
                    return $this->respondWithStatus($response);
                }
            }
        }
    }

}
