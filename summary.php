<?php
/*
BICATORA DE CAMBIOS
===================================================================
CODIGO      RESPONSABLE     FECHA           MOTIVO
@#(1-A)     JMENDOZA        2023-08-25      AGREGAR FECHA DE EMISIÓN DEL DOCUMENTO DE REFERENCIA
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

$empresa = $obj['empresa']; //TODO: DATOS DE LA EMPRESA
$header_resumen = $obj['header_resumen'];     //TODO: HEADER DEL RESUME (FECHA DE RESUMEN Y NUMERO)
$resumen = array();         //TODO: LISTADO DE DOCUMENTOS PARA EL RESUMEN
foreach ($obj['resumen'] as $value){
    $resumen[] = ($value);
}

$fecha_resumen = new Datetime($header_resumen['fecha_resumen']);
$fecha_emision = new Datetime($header_resumen['fecha_emision']); //@#(1-A)
$correlativo_resumen = $header_resumen['correlativo_resumen'];
$numero_correlativo = substr('000'.$correlativo_resumen,-3,3);


$nombre_archivo = $empresa['ruc'].'-RC-'.date_format($fecha_resumen,'Ymd').'-'.$numero_correlativo;
$nombre = "files/facturacion_electronica/XML/".$nombre_archivo.".xml";

if(file_exists($nombre)){
    unlink($nombre);  
}

// echo $nombre_archivo;

// exit;


// // echo var_dump($resumen);exit;

// $fechaYmd = new Datetime($resumen['fecha_anulacion']);

// $anulaciones_dia = $resumen['anulacion_por_dia'];
// // $nombre_archivo = $empresa['ruc'].'-RA-'.date("Ymd").'-'.($anulaciones_dia);
// $nombre_archivo = $empresa['ruc'].'-RA-'.date_format($fechaYmd,'Ymd').'-'.($anulaciones_dia);
//$anulacion_previa = $this->anulaciones_model->select(2, array('numero', 'fecha'), array('venta_id' => $venta_id ));        

////////CREO XML
// $resumen['fecha_emision_sf']   = $resumen['fecha_anulacion'];
// $empresa['empresa']         = $empresa['razon_social'];

//$xml = desarrollo_xml_Summary($empresa, $resumen, $numero_correlativo, $fecha_resumen);     //@#(1-A)
$xml = desarrollo_xml_Summary($empresa, $resumen, $numero_correlativo, $fecha_resumen, $fecha_emision);     //@#(1-A)

$nombre = "files/facturacion_electronica/SUMMARY/XML/".$nombre_archivo.".xml"; 

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
$ws = $ruta_dominio."/ws_sunat/index_summary.php?numero_documento=".$nombre_archivo."&cod_1=1&cod_2=".$empresa['modo']."&cod_3=".$empresa['ruc']."&cod_4=".$user_sec_usu."&cod_5=".$user_sec_pass."&cod_6=2";
//echo $ws;exit;

$data = file_get_contents($ws);
$info = json_decode($data, TRUE);



/////////GUARDO EN BBDD

//var_dump($info['ticket']);
echo json_encode(array('data'=> array('ticket' => $info['ticket'][0])) , JSON_UNESCAPED_UNICODE);
// echo json_encode($info , JSON_UNESCAPED_UNICODE);

// function desarrollo_xml_Summary($empresa, $listado, $correlativo, $fecharesumen){     //@#(1-A)
function desarrollo_xml_Summary($empresa, $listado, $correlativo, $fecharesumen, $fechaemision){    //@#(1-A)
    // $dateanula = new Datetime($venta['fecha_anulacion']);
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>
                <SummaryDocuments xmlns="urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:sac="urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:sunat:names:specification:ubl:peru:schema:xsd:SummaryDocuments-1 UBLPE-SummaryDocuments-1.0.xsd">
                <ext:UBLExtensions>
                    <ext:UBLExtension>
                        <ext:ExtensionContent></ext:ExtensionContent>
                    </ext:UBLExtension>
                </ext:UBLExtensions>
                <cbc:UBLVersionID>2.0</cbc:UBLVersionID>
                <cbc:CustomizationID>1.1</cbc:CustomizationID>
                <cbc:ID>RC-'.date_format($fecharesumen,'Ymd').'-'.$correlativo.'</cbc:ID>
                <cbc:ReferenceDate>'.date_format($fechaemision,'Y-m-d').'</cbc:ReferenceDate>
                <cbc:IssueDate>'.date_format($fecharesumen,'Y-m-d').'</cbc:IssueDate>
                <cac:Signature>
                    <cbc:ID>'.$empresa['ruc'].'</cbc:ID>
                    <cac:SignatoryParty>
                        <cac:PartyIdentification>
                            <cbc:ID>'.$empresa['ruc'].'</cbc:ID>
                        </cac:PartyIdentification>
                        <cac:PartyName>
                            <cbc:Name><![CDATA['.$empresa['nombre_comercial'].']]></cbc:Name>
                        </cac:PartyName>
                        <cac:AgentParty>
                            <cac:PartyIdentification>
                                <cbc:ID>'.$empresa['ruc'].'</cbc:ID>
                            </cac:PartyIdentification>
                            <cac:PartyName>
                                <cbc:Name><![CDATA['.$empresa['razon_social'].']]></cbc:Name>
                            </cac:PartyName>
                            <cac:PartyLegalEntity>
                                <cbc:RegistrationName>
                                    <![CDATA['.$empresa['razon_social'].' ]]>
                                </cbc:RegistrationName>
                            </cac:PartyLegalEntity>
                        </cac:AgentParty>
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
                            <cbc:RegistrationName><![CDATA['.$empresa['razon_social'].']]></cbc:RegistrationName>
                        </cac:PartyLegalEntity>
                    </cac:Party>
                </cac:AccountingSupplierParty>';

            foreach($listado as $value){
                $xml .= '<sac:SummaryDocumentsLine>
                            <cbc:LineID>'.$value['numero_orden'].'</cbc:LineID>
                            <cbc:DocumentTypeCode>'.$value['tipo_documento'].'</cbc:DocumentTypeCode>
                            <cbc:ID>'.$value['serie_documento'].'-'.$value['numero_documento'].'</cbc:ID>
                            <cac:AccountingCustomerParty>
                                <cbc:CustomerAssignedAccountID>'.$value['cliente_numero_documento'].'</cbc:CustomerAssignedAccountID>
                                <cbc:AdditionalAccountID>'.$value['cliente_tipo_documento'].'</cbc:AdditionalAccountID>                            
                            </cac:AccountingCustomerParty>
                            <cac:Status>
                                <cbc:ConditionCode>'.$value['condicion'].'</cbc:ConditionCode>
                            </cac:Status>
                            <sac:TotalAmount currencyID="PEN">'.$value['monto_total'].'</sac:TotalAmount>
                            <sac:BillingPayment>
                                <cbc:PaidAmount currencyID="PEN">'.$value['monto_subtotal'].'</cbc:PaidAmount>
                                <cbc:InstructionID>01</cbc:InstructionID>
                            </sac:BillingPayment>';
                            //TODO: RECORRIENDO LOS IMPUESTOS POR ITEM
                           $impuestos = $value['impuestos'];
                           foreach ($impuestos as $impuesto) {
                                $xml .= '<cac:TaxTotal>
                                            <cbc:TaxAmount currencyID="PEN">'.$impuesto['monto'].'</cbc:TaxAmount>
                                            <cac:TaxSubtotal>
                                                <cbc:TaxAmount currencyID="PEN">'.$impuesto['monto'].'</cbc:TaxAmount>
                                                <cac:TaxCategory>
                                                    <cac:TaxScheme>
                                                        <cbc:ID>'.$impuesto['id'].'</cbc:ID>
                                                        <cbc:Name>'.$impuesto['name'].'</cbc:Name>
                                                        <cbc:TaxTypeCode>'.$impuesto['code'].'</cbc:TaxTypeCode>
                                                    </cac:TaxScheme>
                                                </cac:TaxCategory>
                                            </cac:TaxSubtotal>
                                </cac:TaxTotal>';
                           }


                $xml  .='</sac:SummaryDocumentsLine>';
            }
            $xml .= '</SummaryDocuments>';
        return $xml;
    }
    
function firmar_xml($name_file, $entorno, $resumen = ''){
    $carpeta_baja = ($resumen != '') ? 'SUMMARY/':'';
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