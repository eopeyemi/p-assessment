<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Subscription;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublishController extends BaseController
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $save = Topic::updateOrCreate($request->all(), $request->all());
        return $this->sendResponse($save, 'Topic created successfully.');
    }
    public function resp(Request $request)
    {
        return json_encode('jjj');
    }
    public function publish(Request $request, $topic)
    {
        $isValidTopic = Topic::where(['topic' => $topic])->first();
        if ($isValidTopic == null) {
            $error['message'] = 'Topic not found';
            return $this->sendError($error, 'Topic not found.', 401);
        }

        $data['topic_id'] = $isValidTopic->id;
        $data['message'] = $request->message;
        $created = Message::create($data);
        if ($created) {
            $allSubscribers = Subscription::where(['topic_id' => $isValidTopic->id])->get();
            if (count($allSubscribers) > 0) {
                foreach($allSubscribers as $subscribers){
                    $urls[] = $subscribers->subscriber;
                }   
                
                //array of cURL handles
                $chs = [];
                //POST content
                $request_contents = [
                    'topic' => $isValidTopic->topic,
                    'data' => $request->message
                ];

                //create the array of cURL handles and add to a multi_curl
                $mh = curl_multi_init();
                foreach ($urls as $key => $url) {
                    $chs[$key] = curl_init($url);
                    curl_setopt($chs[$key], CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chs[$key], CURLOPT_POST, true);
                    curl_setopt($chs[$key], CURLOPT_POSTFIELDS, $request_contents);
                    curl_multi_add_handle($mh, $chs[$key]);
                }
                //running the requests
                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running);

                //getting the responses
                foreach (array_keys($chs) as $key) {
                    $error = curl_error($chs[$key]);
                    $last_effective_URL = curl_getinfo($chs[$key], CURLINFO_EFFECTIVE_URL);
                    $time = curl_getinfo($chs[$key], CURLINFO_TOTAL_TIME);
                    $response = curl_multi_getcontent($chs[$key]);  // get results
                    if (!empty($error)) {
                        echo "The request $key return a error: $error" . "\n";
                    } else {
                        echo "The request to '$last_effective_URL' returned '$response' in $time seconds." . "\n";
                    }

                    curl_multi_remove_handle($mh, $chs[$key]);
                }

                // close current handler
                curl_multi_close($mh);
            }
            return $this->sendResponse($data, 'Article published successfully.');
        }
    }
}
