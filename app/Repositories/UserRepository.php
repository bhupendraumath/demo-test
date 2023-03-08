<?php

namespace App\Repositories;

use Illuminate\Http\Request;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Models\User;
use Exception;
use Illuminate\Console\Application;
use Illuminate\Support\Facades\DB;

/**
 * Interface CmsPageRepository.
 *
 * @package namespace App\Repositories;
 */
class UserRepository  extends BaseRepository
{

    public $user;


    function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return User::class;
    }

    /**
     * Function search
     *
     * @param $request $request [explicite description]
     *
     * @return void
     */
    public function search($request)
    {
        $columns = [
            'id', 'name', 'email', 'phone', 'country', 'state', 'city'
        ];
        $query = $this->user->select($columns);
        if ($request->filled('search.value')) {
            $searchValue = $request->query('search')['value'];
            $query->where(
                function ($orQuery) use ($searchValue) {
                    $orQuery->orWhere('name', 'like', '%' . $searchValue . '%')
                        ->orWhere('email', 'like', '%' . $searchValue . '%')
                        ->orWhere('phone', 'like', '%' . $searchValue . '%');
                }
            );
        }

        if ($request['draw'] == 1) {
            $query->orderBy('id', 'desc');
        } else if ($request->filled('order')) {
            $sortDirection = $request->query('order')[0]['dir'];
            $column = $columns[$request->input('order.0.column')];
            $query->orderBy($column, $sortDirection);
        } else {
            $query->orderBy('id', 'DESC');
        }
        return $query->paginate(10);
    }



    /**
     * Function processVerification
     *
     * @param $request $request [explicite description]
     *
     * @return void
     */
    public function processVerification($request)
    {
        try {
            $post = $request->all();
            switch ($post['action']) {
                case 'send_otp':
                    return $this->sendOtp($request);
                    break;

                case 'verify_otp':
                    return $this->verifyOtp($request);
                    break;

                default:
                    throw new Exception(trans('api.something_went_wrong'));
                    break;
            }
        } catch (Exception $ex) {
            DB::rollback();
            throw $ex;
        }
    }

    /**
     * Method verifyOtp
     *
     * @param $request $request [explicite description]
     *
     * @return mixed
     */
    public function verifyOtp($request)
    {

        DB::beginTransaction();
        $userData = $this->user->findWhere(
            [
                'otp' => $request->otp,
                'id' => $request->user_id
            ]
        )->first();
        if (!empty($userData)) {
            if (date('Y-m-d H:i:s') > $userData->otp_expiry_datetime) {
                throw new Exception(trans('api.otp_expired'));
            }
            if ($request->type == 'forgot_password') {
                return true;
            } else if ($request->type == 'profile') {
                return true;
            } else {
                //Update is verified and change status pending to inactive
                $update = $this->user->update(['is_verified' => '1'], $request->user_id);
                if ($update) {
                    DB::commit();
                    return $userData;
                }
                DB::rollback();
                throw new Exception(trans('api.something_went_wrong'));
            }
        }
        throw new Exception(trans('api.invalid_otp'));
    }
    /**
     * Function sendOtp
     *
     * @param $request $request [explicite description]
     *
     * @return void
     */
    public function sendOtp($request)
    {

        DB::beginTransaction();
        $userData = $this->user->findWhere(['id' => $request->user_id])->first();
        if (!empty($userData)) {
            try {
                //Send Otp
                $start = date('Y-m-d H:i:s');
                $otp_expiry_datetime = date(
                    'Y-m-d H:i:s',
                    strtotime('+1 minutes', strtotime($start))
                );
                $otpCode = generateOtp(); //OTP generate
                $message = trans(
                    'api.notification.sms.signup',
                    [
                        'otp' => $otpCode
                    ]
                );
                if ($request->type == 'profile') {
                    $this->twilioService->sendSMS($request->phone_number, $message);
                } else {
                    $this->twilioService->sendSMS($userData->phone_number, $message);
                }

                $this->this->update(['otp' => $otpCode, 'otp_expiry_datetime' => $otp_expiry_datetime], $userData->id);
                DB::commit();
                return 'send_otp';
            } catch (Exception $e) {
                DB::rollback();
                throw new Exception(trans($e->getMessage()));
            }
        } else {
            DB::rollback();
            throw new Exception(trans('api.user_not_found'));
        }
    }


    /**
     * Function forgotPassword
     *
     * @param $request $request [explicite]
     *
     * @return void
     */
    public function forgotPassword($request)
    {
        try {
            DB::beginTransaction();
            $start = date('Y-m-d H:i:s');
            $post = $request->all();
            $userData = $this->user->findWhere(
                [
                    'access_token' => $post['access_token']
                ]
            )->first();
            if (!empty($userData)) {
                $otpCode = rand(1000, 9999); //OTP generate
                $update = $this->user->this->update(
                    [
                        'otp' => $otpCode,
                        'otp_expiry_datetime' => date(
                            'Y-m-d H:i:s',
                            strtotime('+1 minutes', strtotime($start))
                        )
                    ],
                    $userData->id
                );
                if ($update) {
                    $message = trans(
                        'api.notification.sms.forgot',
                        [
                            'otp' => $otpCode
                        ]
                    );
                    // $this->twilioService->sendSMS($userData->phone_code . '' . $userData->phone_number, $message);
                    DB::commit();
                    return array('user_id' => $userData->id);
                }
            }
            return false;
        } catch (\Exception $ex) {
            DB::rollback();
            throw $ex;
        }
    }
}
