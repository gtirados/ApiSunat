<?php
/*
BICATORA DE CAMBIOS
===================================================================
CODIGO      RESPONSABLE     FECHA           MOTIVO
@#(1-A)     JMENDOZA        2023-08-24      AGREGAR FECHA DE EMISIÓN DEL DOCUMENTO DE REFERENCIA
===================================================================
*/
header('Access-Control-Allow-Origin: *');
header("HTTP/1.1");
date_default_timezone_set("America/Lima");

require 'libraries/Numletras.php';
require 'libraries/Variables_diversas_model.php';
require 'libraries/efactura.php';

require_once 'config.php';

$datos = file_get_contents("php://input");
$obj = json_decode($datos, true);

// echo $datos;exit;
// var_dump($datos);exit;
// echo $obj;exit;
// var_dump($obj);exit;

$empresa = $obj['empresa'];
$baja = $obj['anulacion'];

// echo var_dump($baja);exit;

$fechaYmd = new Datetime($baja['fecha_anulacion']);

$anulaciones_dia = $baja['anulacion_por_dia'];
// $nombre_archivo = $empresa['ruc'].'-RA-'.date("Ymd").'-'.($anulaciones_dia);
$nombre_archivo = $empresa['ruc'].'-RA-'.date_format($fechaYmd,'Ymd').'-'.($anulaciones_dia);
//$anulacion_previa = $this->anulaciones_model->select(2, array('numero', 'fecha'), array('venta_id' => $venta_id ));        

////////CREO XML
// $baja['fecha_emision_sf']   = $baja['fecha_anulacion']; //@#(1-A)
$empresa['empresa']         = $empresa['razon_social'];
$xml = desarrollo_xml_baja($empresa, $baja, ($anulaciones_dia));

$nombre = "files/facturacion_electronica/BAJA/XML/".$nombre_archivo.".xml"; 
$archivo = fopen($nombre, "w+");
fwrite($archivo, $xml);
fclose($archivo);

firmar_xml($nombre_archivo.".xml", $empresa['modo'], 1);


//enviar a Sunat       
//cod_1: Select web Service: 1 factura, boletas --- 9 es para guias
//cod_2: Entorno:  0 Beta, 1 Produccion
//cod_3: ruc
//cod_4: usuario secundario USU(segun seha beta o producci贸n)
//cod_5: usuario secundario PASSWORD(segun seha beta o producci贸n)
//cod_6: Accion:   1 enviar documento a Sunat --  2 enviar a anular  --  3 enviar ticket
//cod_7: serie de documento
//cod_8: numero ticket

// $ruta_dominio = "http://localhost/API_SUNAT";
// $ruta_dominio = "http://".$_SERVER["SERVER_NAME"]."/api_sunat";
$ruta_dominio = BASE_URL;
$user_sec_usu = ($empresa['modo'] == 0) ? 'MODDATOS' : $empresa['usu_secundario_produccion_user'];
$user_sec_pass = ($empresa['modo'] == 0) ? 'moddatos' : $empresa['usu_secundario_produccion_password'];        
$ws = $ruta_dominio."/ws_sunat/index_baja.php?numero_documento=".$nombre_archivo."&cod_1=1&cod_2=".$empresa['modo']."&cod_3=".$empresa['ruc']."&cod_4=".$user_sec_usu."&cod_5=".$user_sec_pass."&cod_6=2";
//echo $ws;exit;

$data = file_get_contents($ws);
$info = json_decode($data, TRUE);

/////////GUARDO EN BBDD

//var_dump($info['ticket']);
 echo json_encode(array('data'=> array('ticket' => $info['ticket'][0])) , JSON_UNESCAPED_UNICODE);
// echo json_encode($info , JSON_UNESCAPED_UNICODE);

function desarrollo_xml_baja($empresa, $venta, $identificador){
    $dateanula = new Datetime($venta['fecha_anulacion']);
    $dateemision = new Datetime($venta['fecha_emision']); //@#(1-A)
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?><VoidedDocuments xmlns="urn:sunat:names:specification:ubl:peru:schema:xsd:VoidedDocuments-1" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:sac="urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ext:UBLExtensions>
                    <ext:UBLExtension>
                        <ext:ExtensionContent></ext:ExtensionContent>
                    </ext:UBLExtension>
                </ext:UBLExtensions>
                <cbc:UBLVersionID>2.0</cbc:UBLVersionID>
                <cbc:CustomizationID>1.0</cbc:CustomizationID>
                <cbc:ID>RA-'.date_format($dateanula,'Ymd').'-'.$identificador.'</cbc:ID>
                <cbc:ReferenceDate>'.date_format($dateemision,'Y-m-d').'</cbc:ReferenceDate>
                <cbc:IssueDate>'.date_format($dateanula,'Y-m-d').'</cbc:IssueDate>
                <cac:Signature>
                    <cbc:ID>'.$empresa['ruc'].'</cbc:ID>
                    <cac:SignatoryParty>
                        <cac:PartyIdentification>
                            <cbc:ID>'.$empresa['ruc'].'</cbc:ID>
                        </cac:PartyIdentification>
                        <cac:PartyName>
                            <cbc:Name><![CDATA['.$empresa['empresa'].']]></cbc:Name>
                        </cac:PartyName>
                    </cac:SignatoryParty>
                    <cac:DigitalSignatureAttachment>
                        <cac:ExternalReference>
                            <cbc:URI>'.$empresa['ruc'].'</cbc:URI>
                        </cac:ExternalReference>
                    </cac:DigitalSignatureAttachment>
                </cac:Signature>
                <cac:AccountingSupplierParty>
                    <cbc:CustomerAssignedAccountID>'.$empresa['ruc'].'</cbc:CustomerAssignedAccountID>
                    <cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
                    <cac:Party>
                        <cac:PartyLegalEntity>
                            <cbc:RegistrationName><![CDATA['.$empresa['empresa'].']]></cbc:RegistrationName>
                        </cac:PartyLegalEntity>
                    </cac:Party>
                </cac:AccountingSupplierParty>
                <sac:VoidedDocumentsLine>
                    <cbc:LineID>1</cbc:LineID>
                    <cbc:DocumentTypeCode>'.$venta['tipo_documento_codigo'].'</cbc:DocumentTypeCode>
                    <sac:DocumentSerialID>'.$venta['serie'].'</sac:DocumentSerialID>
                    <sac:DocumentNumberID>'.$venta['numero'].'</sac:DocumentNumberID>
                    <sac:VoidReasonDescription>Anulacion de la Operacion</sac:VoidReasonDescription>
                </sac:VoidedDocumentsLine>
            </VoidedDocuments>';
        return $xml;
    }
    
function firmar_xml($name_file, $entorno, $baja = ''){
    $carpeta_baja = ($baja != '') ? 'BAJA/':'';
    $carpeta = "files/facturacion_electronica/$carpeta_baja";
    $dir = $carpeta."XML/".$name_file;
    //$dir = $name_file;
    $xmlstr = file_get_contents($dir);    

    $domDocument = new \DOMDocument();
    $domDocument->loadXML($xmlstr);
    $factura  = new Factura();    
    $xml = $factura->firmar($domDocument, '', $entorno);
    $content = $xml->saveXML();
    file_put_contents($carpeta."FIRMA/".$name_file, $content);
    //file_put_contents("xxxxarchivo_firmado_con_certificado".$name_file, $content);
}    