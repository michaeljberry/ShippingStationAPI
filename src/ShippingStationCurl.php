<?php

namespace ShippingStation;

use Exception;

class ShippingStationCurl
{
    /**
     * Define method constants
     */
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';
    const METHOD_DELETE = 'DELETE';

    /**
     * Api key
     * @var
     */
    protected $api_key;

    /**
     * No. remaining requests
     * @var
     */
    private $remainingRequests;

    /**
     * Reset time
     * @var
     */
    private $resetTime;

    /**
     * Last request time
     * @var
     */
    private $lastRequestTime;

    /**
     * Http code
     * @var
     */
    protected $http_code = 200;

    /**
     * Api secret
     * @var
     */
    protected $api_secret;

    /**
     * Error
     * @var string
     **/
    protected $errors = null;

    /**
     * Auth Token
     * @var
     */
    protected $token;

    /**
     * End point URL
     * @var string
     */
    private $endpoint = 'https://ssapi.shipstation.com/';

    /**
     * Array containing headers from last performed request.
     * @var array
     */
    private $headers = [
        //'Accept' => 'Accept: application/json',
        'Content-Type' => 'Content-Type: application/json'
    ];

    /**
     * @param string $key
     * @param string $value
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value ? "{$key}: {$value}": $value;
    }

    /**
     * Constructor
     * @param Repository $config
     * @throws Exception
     */
    public function __construct($api_key, $api_secret)
    {
        /*if(empty($api_key) or empty($api_secret)) {
            throw new Exception('API Key/Secret is empty.');
        }*/

        $this->api_key = $api_key;
        $this->api_secret = $api_secret;

        // create tokens
        $this->token = base64_encode(trim($this->api_key . ':' . $this->api_secret));
        $this->setHeader('Authorization','Basic '. $this->token);

        // Requests cap handling //
        $this->remainingRequests    = 40; // Current SS per-minute limit //
        $this->resetTime            = 0;
        $this->lastRequestTime      = null;
    }

    /**
     * Request method
     * @param $method
     * @param $url
     * @param array $parameters
     * @param array $headers
     * @return mixed
     * @throws Exception
     */
    private function request($method, $url, array $data = [], array $headers = [])
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->endpoint . $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 1,
            CURLINFO_HEADER_OUT => 1,
            CURLOPT_VERBOSE => 1
        ]);

        /**
         * Added this to accommodate the ugly design of the Shipstation API
         */
        $new_data = '';
        foreach($data as $key => $value) {
           $new_data .=  " \"$key\" : $value " . ",";
        }
        $new_data = "{" . rtrim($new_data,",") . "}";

        switch ($method) {
            case 'PUT':
            case 'PATCH':
            case 'POST':
                curl_setopt_array($curl, [
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS =>  $new_data,
                ]);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array_filter(array_values($this->headers)));

        $response = curl_exec($curl);
        $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if(curl_error($curl)){
            $this->errors = curl_error($curl);
        }

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        if(isset($this->errors)){
            throw new Exception($this->errors,$this->http_code);
        }

        $json = json_decode(substr($response, $header_size), false, 512, JSON_BIGINT_AS_STRING);

        return $json;
    }

    /**
     * GET
     * @param $url
     * @return mixed
     */
    public function get($url)
    {
        // add the api limiter
        $this->enforceApiRateLimit();

        return $this->request(self::METHOD_GET, $url);
    }

    /**
     * POST
     * @param $url
     * @param array $data
     * @return mixed
     */
    public function post($url, $data = [])
    {
        // add the api limiter
        $this->enforceApiRateLimit();

        return $this->request(self::METHOD_POST, $url, $data);
    }

    /**
     * PUT
     * @param $url
     * @param array $data
     * @return mixed
     */
    public function put($url, $data = [])
    {
        // add the api limiter
        $this->enforceApiRateLimit();

        return $this->request(self::METHOD_PUT, $url, $data);
    }

    /**
     * DELETE
     * @param $url
     * @param array $data
     * @return mixed
     */
    public function delete($url, $data = [])
    {
        // add the api limiter
        $this->enforceApiRateLimit();

        return $this->request(self::METHOD_DELETE, $url, $data);
    }

    /**
     * Enforces ShipStation API limiter
     */
    private function enforceApiRateLimit()
    {

        if($this->remainingRequests > 0)
        {
            return;
        } else {
            if(!empty($this->lastRequestTime))
            {
                $elapsedTime = (time() - $this->lastRequestTime);
                if($elapsedTime > $this->resetTime)
                {
                    return;
                } else {
                    $waitingTime =  ($this->resetTime - $elapsedTime);
                    sleep($waitingTime);
                }
            } else {
                return;
            }

        }
    }
}