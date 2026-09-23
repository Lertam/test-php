<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Services\Referral\ReferralService;
use App\Models\Referral;
use App\Models\ReferralEarning;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Текущий мастер приходит в заголовке X-Master-Id и уже разложен
| в атрибуты запроса middleware'ом ResolveCurrentMaster:
|
|     $master = $request->attributes->get('current_master');
|
| Здесь нужно написать три роута — см. README.md.
|
*/

Route::get('/ping', fn () => ['ok' => true]);

// принимает { "code": "MASHA10" } и закрепляет текущего мастера за владельцем кода. Повторный вызов не создаёт вторую привязку, за себя закрепиться нельзя
Route::post("/referrals/attach", function (Request $request, ReferralService $referralService){
    $master = $request->attributes->get('current_master');
    $code = $request->input('code');
    return $referralService->registerReferral($master, $code);
});

// список приведённых мной мастеров: имя, дата привязки, засчитан или нет, сколько по нему начислено
Route::get('/referrals/my', function(Request $request){
    $master = $request->attributes->get('current_master');
    $referrals = $master->referrals()->get();
    $result = [];

    foreach($referrals as $referral) {
        $result[] = [
            'name' => $referral->referredMaster()->first()->name,
            'attached' => $referral->created_at,
            'rewarded' => $referral->status === Referral::STATUS_REWARDED,
            'amount' => ReferralEarning::where('referred_master_id', $referral->referred_master_id)->sum("amount")
        ];
    }
    return $result;
});

// сводка по деньгам: всего начислено, в ожидании, выплачено, сколько рефералов засчитано
Route::get('/referrals/earnings', function(Request $request){
    $master = $request->attributes->get('current_master');

    $pending = $master->referralEarnings()->where('status', ReferralEarning::STATUS_PENDING)->sum('amount');
    $paid = $master->referralEarnings()->where('status', ReferralEarning::STATUS_PAID)->sum('amount');

    $referrals = $master->referrals()->where('status', Referral::STATUS_REWARDED)->count();

    return [
        'total' => $pending + $paid,
        'pending' => $pending,
        'paid' => $paid,
        'referrals' => $referrals
    ];
});
