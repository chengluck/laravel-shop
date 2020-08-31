<?php

namespace App\Http\Controllers;

use App\Exceptions\CouponcodeUnavailableException;
use App\Models\CouponCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mockery\Exception\InvalidCountException;

class CouponCodesController extends Controller
{
    public function show($code)
    {
        if(!$record = CouponCode::where('code', $code)->first()){
            throw new CouponcodeUnavailableException('优惠券不存在');
        }

        $record->checkAvailable();

        return $record;
    }
}
