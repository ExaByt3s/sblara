<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Classes\Screener as Scanner;
class Screener extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    	
    public function resultCount()
    {
		$value = \Cache::remember('screener_count_'.$this->id, 1, function () {
    		$screener = new Scanner($this->query);
    		return $screener->count();
		});    	
    	return $value;
    }
}
