<?php
class OpenAI{

    private function API_KEY(){

    // put in your API key.

        return $secret_key = 'sk-YOUROPENAI-SECRETKEY';
          }


    private function configuration($prompt,$max_tokens){

    // configuration of OpenAI here

        $request_body = [
        "prompt" => $prompt,
        "max_tokens" => $max_tokens,
        "temperature" => 0.7,
        "top_p" => 1,
        "presence_penalty" => 0,
        "frequency_penalty"=> 0,
        "best_of"=> 1,
        "stream" => false,
        ];

   return $request_body;

    }



    public function completion($engine, $prompt, $max_tokens){

        $request_body = $this->configuration($prompt,$max_tokens);
        $postfields = json_encode($request_body);
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.openai.com/v1/engines/" . $engine . "/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postfields,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->API_KEY()
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

print_r($response);

        if ($err) {
            echo "Error #:" . $err;
            die();
        } else {
            return $response;
        }

    }

}
