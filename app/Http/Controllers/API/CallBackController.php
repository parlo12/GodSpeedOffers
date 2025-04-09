<?php

    namespace App\Http\Controllers\API;

    use App\Http\Controllers\Controller;
    use App\Jobs\HandleSmsEventJob;
    use App\Models\Traits\ApiResponser;
    use Illuminate\Http\Request;

    class CallBackController extends Controller
    {
        use ApiResponser;

        public function __construct()
        {
            $this->middleware('auth:sanctum')->except('receiveSmsCallback');
        }

            /**
         * callback for reveiving sms
         */
        public function receiveSmsCallback(Request $request)
        {
            // Validate and queue the job
            dispatch(new HandleSmsEventJob($request->all()));

            return response()->json(['status' => 'accepted'], 202);
        }


    }
