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
use Socialite;
use File;

class RegisterApiController extends APIController {

    protected $users;

    public function __construct(User $users) {
        $this->users = $users;
    }

    function student_register(Request $request) {

        $email = trim($request->email);
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

    function human_filesize($size, $precision = 2) {
        for ($i = 0; ($size / 1024) > 0.9; $i++, $size /= 1024) {
            
        }
        return round($size, $precision) . ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][$i];
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
            $profile_image_size = $this->human_filesize($profile_image_size_ori);
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

    public function post_register(Request $request) {
        $role_id = 2;
        $file_cv = '';
        $image_profile = '';

        $x1 = $request->x1;
        $y1 = $request->y1;
        $w1 = $request->w1;
        $h1 = $request->h1;
        $div_w = $request->div_w;
        $div_h = $request->div_h;


        if ($request->type_account == 'tutor') {
            $role_id = 3;
            $file_cv = 'required|mimes:pdf|max:10240';
            $image_profile = 'required|image|mimes:jpeg,png,jpg,gif|max:2048';
        }
        $rules = array(
            'email' => 'required|string|max:100|unique:users|email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|min:5',
            'accept_terms' => 'required',
            'accept_privacy' => 'required',
            'confirmation_password' => 'required|same:password',
            'file_cv' => $file_cv,
            'image_profile' => $image_profile,
        );
        $messages = array(
            'email.required' => 'Please add your email below.',
            'first_name.required' => 'Please add first name below.',
            'last_name.required' => 'Please add your last name below.',
            'password.required' => 'Please add your password below.',
            'confirmation_password.required' => 'Please confirm your password below.',
            'accept_terms.required' => 'Please confirm that you accept our Terms and Conditions below.',
            'accept_privacy.required' => 'Please confirm that you accept our Privacy Policy below.',
            'file_cv.required' => 'Please upload your CV below.',
            'image_profile.required' => 'Please upload your Profile picture below.',
            'required' => 'The :attribute is really really really important.',
            'same' => 'The :others must match.'
        );

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {

            if ($errors->has('email')) {
                //
            }
            Session::put('email', $request->email);
            Session::put('first_name', $request->first_name);
            Session::put('last_name', $request->last_name);


//            $messages = $validator->messages();
            return redirect()->back()
                            ->withErrors($validator);
        } else {
            $data = $request->input();
            if (Sentinel::register($data)) {
                if (!empty($request->file_cv)) {
                    $upload = upload_file($request->file_cv, 'file/tutor/', 'file');
                    $data['file_cv'] = '/' . $upload['original'];
                }
                if (!empty($request->image_profile)) {
                    $this->validate($request, [
                        'image_profile' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    ]);
                    $upload_profile = $upload_profile = upload_profile_image($request->image_profile, 'images/profiles/', $x1, $y1, $w1, $h1, $div_w, $div_h);
                    $data['image_profile'] = '/' . $upload_profile['thumbnail'];
                }
                $data['accept_privacy'] = ($data['accept_privacy']) ? 1 : 0;
                $data['accept_terms'] = ($data['accept_privacy']) ? 1 : 0;
                $user = Sentinel::findByCredentials($data);
                $activationCreated = Activation::create($user);
                $id = $user->id;
                $getCodeActivated = Activation::exists($user);

                if ($role_id == 3) {
                    $rating = new Rating;

                    $countRating = 4;

                    DB::beginTransaction();
                    $data_ratings = array(
                        'user_id' => $user->id,
                        'comment' => '',
                        'student_id' => 0,
                        'module' => 'tutor',
                        'point' => $countRating
                    );
                    DB::commit();
                    $rating->create($data_ratings);
                }
                if ($role_id == 2) {
                    $getUser = $this->users->find($id);
                    $getUser->notify(new RegisterSendMail($getUser, $getCodeActivated['code'], $role_id));
                }

                $slug = title_based_url($user->first_name . '-' . $user->last_name);
                $find_slug = User::where("slug", "=", $slug)->first();
                if ($find_slug) {
                    $slug .= "-" . $id;
                }
                $updateUser = User::where('id', $id)->first();
                $updateUser->slug = $slug;
                if ($request->type_account == 'tutor') {
                    $updateUser->file_cv = $data['file_cv'];
                    $updateUser->image_profile = $data['image_profile'];
                }
                $updateUser->accept_terms = $data['accept_terms'];
                $updateUser->accept_privacy = $data['accept_privacy'];
                $updateUser->update();

                if ($user) {
                    $user->roles()->attach($role_id);
                }
                /* $getCodeActivated = Activation::exists($user);
                  if (Activation::complete($user, $getCodeActivated['code'])){
                  Activation::completed($user);
                  } */
            }

            if ($request->type_account == 'student') {

                flash()->success('Success. To finalise your registration, please click on the confirmation link sent to your email address.');
                if ($request->has('red')) {
                    $redirect = route('login.suggestion') . '?red=' . $request->input('red');
                    if ($request->has('order')) {
                        $redirect = $redirect . '&order=' . $request->input('order');
                        return redirect()->to($redirect);
                    }
                }
                return redirect()->route('register_confirm_student');
            } else {
                flash()->success('Registration successful. You will be notified by email once your application has been approved.');
                return redirect()->route('register_confirm_tutor');
            }
        }
    }

}
