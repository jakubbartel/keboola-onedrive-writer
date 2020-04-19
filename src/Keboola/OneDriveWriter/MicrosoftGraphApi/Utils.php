<?php

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use GuzzleHttp\Exception\BadResponseException;

class Utils {

    public static function parseGraphApiErrorMessage(BadResponseException $e): string
    {
        $respBody = $e->getResponse()->getBody()->getContents();

        $resp = json_decode($respBody, true);
        if($resp === null) {
            return $respBody;
        }

        if(!isset($resp['error']['message'])) {
            return json_encode($resp);
        }

        return $resp['error']['message'];
    }

}
