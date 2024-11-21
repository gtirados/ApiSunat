<?php
header('Access-Control-Allow-Origin: *');
header("HTTP/1.1");
date_default_timezone_set("America/Lima");

require_once 'config.php';

$datos = file_get_contents("php://input");
$obj = json_decode($datos, true);

$empresa        = $obj['empresa'];
$resumen    = $obj['resumen'];

$numero_correlativo = $resumen['numero_correlativo'];

$fecha_resumen = substr($resumen['fecha'], 0, 4).substr($resumen['fecha'], 5, 2).substr($resumen['fecha'], 8, 2);
$nombre_archivo = $empresa['ruc'].'-RC-'.$fecha_resumen.'-'.substr('000'.$numero_correlativo,-3,3);

// echo $numero_correlativo;
// echo "\n";
// echo $nombre_archivo;
// exit;

//enviar a Sunat       
//cod_1: Select web Service: 1 factura, boletas --- 9 es para guias
//cod_2: Entorno:  0 Beta, 1 Produccion
//cod_3: ruc
//cod_4: usuario secundario USU(segun seha beta o producción)
//cod_5: usuario secundario PASSWORD(segun seha beta o producción)
//cod_6: Accion:   1 enviar documento a Sunat --  2 enviar a anular  --  3 enviar ticket
//cod_7: serie de documento
//cod_8: numero ticket

// echo $numero_correlativo;

//   echo $nombre_archivo;
// echo $resumen['ticket'];
//   exit;

// $ruta_dominio = "http://localhost/API_SUNAT";
// $ruta_dominio = "http://".$_SERVER["SERVER_NAME"]."/api_sunat";
$ruta_dominio = BASE_URL;
$user_sec_usu = ($empresa['modo'] == 0) ? 'MODDATOS' : $empresa['usu_secundario_produccion_user'];
$user_sec_pass = ($empresa['modo'] == 0) ? 'moddatos' : $empresa['usu_secundario_produccion_password'];
$url = $ruta_dominio."/ws_sunat/index_summary.php?numero_documento=".$nombre_archivo."&cod_1=1&cod_2=".$empresa['modo']."&cod_3=".$empresa['ruc']."&cod_4=".$user_sec_usu."&cod_5=".$user_sec_pass."&cod_6=3&cod_7=ABC&cod_8=".$resumen['ticket'];

// echo $url;exit;

$data = file_get_contents($url);


// $caleta = json_decode($data);

// if ($caleta === null){
//     echo "Error al decodificar el JSON";
// }
// else {
//     print_r($caleta);
// }
// exit;



// echo $data;exit;  
$info = json_decode($data, TRUE);
// var_dump($info);exit;

$respuesta_codigo = '';
$respuesta_mensaje = '';

$jsondata = array(
    'success'       =>  true,
    'codigo'        =>  $respuesta_codigo,
    'error_existe'  =>  $info['error_existe'],
    'message'       =>  $info['param_ver'],
    'mensaje'       =>  $info['mensaje']
);
echo json_encode(array('data'=> $jsondata), JSON_UNESCAPED_UNICODE);
// echo $jsondata;