<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavouriteTutor extends Model
{
    protected $table = 'favourite_tutors';
    protected $fillable = ['user_id','tutor_id'];


//    public function user(){
//    	return $this->belongsTo('App\User');
//    }

  
}
