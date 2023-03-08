<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\Request;
use Hash;
use Session;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;

class CustomAuthController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @param UserRepository $userRepository
     *
     * @return void
     */
    public function __construct(
        UserRepository $userRepository,
    ) {
        $this->userRepository = $userRepository;
    }

    public function index()
    {
        return view('auth.login');
    }

    public function customLogin(Request $request)
    {
        $request->validate(
            [
                'email' => 'required',
                'password' => 'required',
            ]
        );

        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            return redirect()->intended('dashboard')
                ->withSuccess('Signed in');
        }

        return redirect("login")->withSuccess('Login details are not valid');
    }

    public function registration()
    {
        return view('auth.registration');
    }

    public function customRegistration(Request $request)
    {
        $request->validate(
            [
                'name' => 'required',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
            ]
        );

        $data = $request->all();
        $check = $this->create($data);

        return redirect("dashboard")->withSuccess('You have signed-in');
    }

    public function create(array $data)
    {
        return User::create(
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'])
            ]
        );
    }

    public function dashboard()
    {
        if (Auth::check()) {
            return view('auth/dashboard');
        }

        return redirect("login")->withSuccess('You are not allowed to access');
    }

    public function signOut()
    {
        Session::flush();
        Auth::logout();

        return Redirect('login');
    }


    /**
     * Method list
     * 
     * @param Request $request 
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function userList(Request $request)
    {
        $data = $this->userRepository->search($request);
        $userData = [];
        if (!empty($data)) {
            foreach ($data as $result) {
                $userData[] = [
                    'id' => $result->id,
                    'name' => $result->name,
                    'email' => $result->email,
                    'phone' => $result->phone,
                    'country' => $result->country,
                    'state' => $result->state,
                    'city' => $result->city,
                    'action' => '-',
                ];
            }
        }
        return response()->json(
            ['data' => $userData, 'meta' => ['total' => $data->total()]]
        );
    }

    /**
     * Method forgotPassword
     * 
     * @param ForgotPasswordRequest $request forgot password
     * 
     * @return type
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $result = $this->userRepository->forgotPassword($request);
            if ($result) {
                return response()->json(
                    [
                        'success' => true,
                        'data' => $result,
                        'message' => Lang::get(trans('api.sent_otp'))
                    ]
                );
            } else {
                return response()->json(
                    [
                        'success' => false,
                        'data' => [],
                        'error' => [
                            'message' => Lang::get(trans('api.forgot_phone_number'))
                        ]
                    ],
                    422
                );
            }
        } catch (\Exception $ex) {
            return response()->json(
                [
                    'success' => false,
                    'data' => [],
                    'error' => [
                        'message' => $ex->getMessage()
                    ]
                ],
                422
            );
        }
    }
}
