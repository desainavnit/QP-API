<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});
Route::group(['namespace' => 'Api'], function () {
    Route::post("login", "LoginApiController@login");      
    Route::post("logout", "LoginApiController@logout");
    Route::post("forgot_password", "LoginApiController@forgot_password");
    Route::post("student_register", "RegisterApiController@student_register");
    Route::post("get_tutor_list", "TutorApiController@get_tutor_list");
    Route::post("get_one_tutor", "TutorApiController@get_one_tutor");
    Route::post("get_favourite_tutor_list", "TutorApiController@get_favourite_tutor_list");
    Route::post("get_tutor_calendar", "TutorApiController@get_tutor_calendar");
    Route::post("make_as_favourite_tutor", "TutorApiController@make_as_favourite_tutor");
    Route::post("make_as_unfavourite_tutor", "TutorApiController@make_as_unfavourite_tutor");
    
    
    Route::post("book_private_tution", "PrivateTutionApiController@book_private_tution");
    Route::post("get_student_calendar", "StudentProfileApiController@get_student_calendar");
    Route::post("get_student_profile", "StudentProfileApiController@get_student_profile");
   
});  