<?php
/**
 * Created by PhpStorm.
 * User: felip
 * Date: 24/05/2018
 * Time: 17:59
 * jira#
 */

//################# CONFIGURACAO PARAMETROS OBRIGATORIOS ###########################

/*INSTANCIA ORIGEM*/
$url_origem_relatorios = "https://";
$user_origem_relatorios = "";
$pass_origem_relatorios = "";

/*INSTANCIA DESTINO*/
$url_destino_relatorios = "https://";
$user_destino_relatorios = "";
$pass_destino_relatorios = "";

//array('id_1','id_2'...)
$ids_relatorios = array('4ac8bff2-2738-11e8-947b-0273927739c9',
    'e86a8cb0-2745-11e8-a018-0273927739c9',
    '1839f864-261e-11e8-b50f-024b2e004070',
    '3b7e2a1e-208f-11e8-886a-024b2e004070',
    '03521f00-2090-11e8-91b7-06ac6cf6c781');

/*URI - MODULO*/
$uri_reports = "/rest/v10/Reports/"; //caso queria fazer o mesmo processo so que para outro modulo, basta alterar a uri
//################# CONFIGURACAO PARAMETROS OBRIGATORIOS ###########################


//NAO ALTERAR DAQUI PARA BAIXO
$num_relatorios = count($ids_relatorios);
if(empty($url_origem_relatorios) || empty($url_destino_relatorios) || empty($user_origem_relatorios) ||  empty($user_destino_relatorios) || $num_relatorios == 0){
    throw new Exception("Parametros invalidos ",500);
}

$access_token_origem = "";
$access_token_destino = "";

echo "Iniciando script....\n";

echo "Buscando relatorios de $url_origem_relatorios para $url_destino_relatorios ...\n";



$url_origem_access_token = $url_origem_relatorios."rest/v10/oauth2/token?grant_type=password&client_secret&username=$user_origem_relatorios&password=$pass_origem_relatorios&client_id=sugar";
$url_destino_access_token = $url_destino_relatorios."rest/v10/oauth2/token?grant_type=password&client_secret&username=$user_destino_relatorios&password=$pass_destino_relatorios&client_id=sugar";

$url_origem_ping = $url_origem_relatorios."/rest/v10/ping";
$url_destino_ping = $url_destino_relatorios."/rest/v10/ping";
$count = 0;
foreach ($ids_relatorios as $relatorio){

    echo "\nProcessando registro ".(++$count)." de $num_relatorios\n";
    $access_token_origem = checkAccessToken($url_origem_ping, $access_token_origem) ? $access_token_origem : getAccessToken($url_origem_access_token);
    $access_token_destino = checkAccessToken($url_destino_ping, $access_token_destino) ? $access_token_destino : getAccessToken($url_destino_access_token);
    if($access_token_origem && $access_token_destino){
        $response = postReport($url_destino_relatorios.$uri_reports,getReports($url_origem_relatorios.$uri_reports.$relatorio,$access_token_origem),$access_token_destino);
        echo "Response: $response->httpCode\n";
        if(isset($response->error_message)){
            echo $response->error_message."\n";
        }
    }
    else{
        throw new Exception("Erro ao recuperar token de acesso para ".($access_token_origem ? $url_origem_relatorios:$url_destino_relatorios),500);
    }

}

echo "Script finalizado.";


function postReport($url, $response, $access_token){
    $header = array(
        "Content-Type: application/json",
        "cache-control: no-cache",
        "oauth-token: $access_token"
    );

    if(isset($response->httpCode) && $response->httpCode == 200){
        echo "Criando registro: $url\n";
        return doCurl($url,"POST",$header, json_encode($response));
    }
    else{
        $ret = new stdClass();
        $ret->httpCode = $response->httpCode;
        $ret->error_message = $response->error_message;
        return $ret;
    }

}


function getReports($url, $acessToken){
    echo "Buscando registro: $url...\n";
    $header = array(
        "Cache-Control: no-cache",
        "oauth-token: $acessToken"
    );

    return doCurl($url,"GET",$header);
}


function checkAccessToken($url, $acessToken){
    $header = array(
        "Cache-Control: no-cache",
        "oauth-token: $acessToken"
    );

    $response = doCurl($url,"GET",$header);


    //retorna true se token e valido
    return $response == "pong";
}

function getAccessToken($url){
    echo "Autenticando em $url...\n";
    $response = doCurl($url,"POST");
    if(isset($response->access_token)){
        return $response->access_token;
    }
    else{
        return null;
    }

}

function doCurl($url, $requestType, $header = array("Cache-Control: no-cache"), $dataPost = null){
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_FOLLOWLOCATION, true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_POSTFIELDS => $dataPost,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $requestType,
        CURLOPT_HTTPHEADER => $header
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    }

    $response = json_decode($response);
    if(is_object($response)){
        $response -> httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

    curl_close($curl);
    return $response;
}
