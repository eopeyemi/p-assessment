<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscribeController extends BaseController
{

    public function subscribe(Request $request, $topic)
    {
        $isValidTopic = Topic::where(['topic' => $topic])->first();
        if($isValidTopic == null){ 
            $error['message'] = 'Topic not found';
            return $this->sendError($error, 'Topic not found.', 401);
        }
        $validator = Validator::make($request->all(), [
            'url' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }
        $data['topic_id'] = $isValidTopic->id;
        $data['subscriber'] = $request->url;
        $created = Subscription::updateOrCreate($data, $data);
        if($created){
            return response()->json($data, 200);
        }
    }
}
