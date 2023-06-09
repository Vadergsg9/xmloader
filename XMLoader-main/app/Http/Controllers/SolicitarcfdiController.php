<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SolicitarcfdiController extends Controller
{
    public static function soapRequest($cert, $keyPEM, $token, $rfc, $fechaInicial, $fechaFinal, $TipoSolicitud, $TipoConsulta)
    {
    $xmlString = Solicitar::getSoapBody($cert, $keyPEM, $rfc, $fechaInicial, $fechaFinal, $TipoSolicitud, $TipoConsulta);
    //print_r($xmlString);
    $headers = Complemento::headers($xmlString, 'http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescarga', $token);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_URL, 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 50000);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    set_time_limit(0);
    $soap = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
      throw new Exception("cURL Error #:" . $err);
    } else {
      return Solicitar::response(Complemento::xmlToArray($soap));
    }
  }

  public static function getSoapBody($cert, $keyPEM, $rfc, $fechaInicial, $fechaFinal, $TipoSolicitud, $TipoConsulta)
  {
    if ($TipoConsulta === 'Emitidos') {
      $dataToHash = '<des:SolicitaDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx"><des:solicitud FechaInicial="' . $fechaInicial . '" FechaFinal="' . $fechaFinal . '" RfcEmisor="' . $rfc . '" RfcSolicitante="' . $rfc . '" TipoSolicitud="' . $TipoSolicitud . '" ><des:RfcReceptores><des:RfcReceptor/></des:RfcReceptores></des:solicitud></des:SolicitaDescarga>';
    } elseif ($TipoConsulta === 'Recibidos') {
      $dataToHash = '<des:SolicitaDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx"><des:solicitud FechaInicial="' . $fechaInicial . '" FechaFinal="' . $fechaFinal . '" RfcEmisor="" RfcSolicitante="' . $rfc . '" TipoSolicitud="' . $TipoSolicitud . '"><des:RfcReceptores><des:RfcReceptor>' . $rfc . '</des:RfcReceptor></des:RfcReceptores></des:solicitud></des:SolicitaDescarga>';
    }

    $digestValue = base64_encode(sha1($dataToHash, true));
    $dataToSign = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#"><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></CanonicalizationMethod><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod><Reference URI=""><Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod><DigestValue>' . $digestValue . '</DigestValue></Reference></SignedInfo>';
    openssl_sign($dataToSign, $digs, $keyPEM, OPENSSL_ALGO_SHA1);
    $datosCer = openssl_x509_parse(Complemento::derToPem($cert));
    $serialNumber = $datosCer["serialNumber"];
    $datos = '';
    foreach ($datosCer["issuer"] as $key => $value) {
      $datos .= $key . '=' . $value . ',';
    }
    $datos = substr($datos, 0, -1);
    if ($TipoConsulta === 'Emitidos') {
      $xml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx" xmlns:xd="http://www.w3.org/2000/09/xmldsig#"><s:Header/><s:Body><des:SolicitaDescarga><des:solicitud FechaInicial="' . $fechaInicial . '" FechaFinal="' . $fechaFinal . '" RfcEmisor="' . $rfc . '" RfcSolicitante="' . $rfc . '" TipoSolicitud="' . $TipoSolicitud . '"><des:RfcReceptores><des:RfcReceptor/></des:RfcReceptores><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod><Reference URI="#_0"><Transforms><Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></Transform></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod><DigestValue>' . $digestValue . '</DigestValue></Reference></SignedInfo><SignatureValue>' . base64_encode($digs) . '</SignatureValue><KeyInfo><X509Data><X509IssuerSerial><X509IssuerName>' . $datos . '</X509IssuerName><X509SerialNumber>' . $serialNumber . '</X509SerialNumber></X509IssuerSerial><X509Certificate>' . base64_encode($cert) . '</X509Certificate></X509Data></KeyInfo></Signature></des:solicitud></des:SolicitaDescarga></s:Body></s:Envelope>';
    } elseif ($TipoConsulta === 'Recibidos') {
      $xml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx" xmlns:xd="http://www.w3.org/2000/09/xmldsig#"><s:Header/><s:Body><des:SolicitaDescarga><des:solicitud FechaInicial="' . $fechaInicial . '" FechaFinal="' . $fechaFinal . '" RfcEmisor="" RfcSolicitante="' . $rfc . '" TipoSolicitud="' . $TipoSolicitud . '"><des:RfcReceptores><des:RfcReceptor>' . $rfc . '</des:RfcReceptor></des:RfcReceptores><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod><Reference URI="#_0"><Transforms><Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></Transform></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod><DigestValue>' . $digestValue . '</DigestValue></Reference></SignedInfo><SignatureValue>' . base64_encode($digs) . '</SignatureValue><KeyInfo><X509Data><X509IssuerSerial><X509IssuerName>' . $datos . '</X509IssuerName><X509SerialNumber>' . $serialNumber . '</X509SerialNumber></X509IssuerSerial><X509Certificate>' . base64_encode($cert) . '</X509Certificate></X509Data></KeyInfo></Signature></des:solicitud></des:SolicitaDescarga></s:Body></s:Envelope>';
    }
    return $xml;
  }

  public static function response($data)
  {
    $obj = (object)[];
    if (isset($data["Body"]["Fault"])) {
      $obj->faultcode = $data["Body"]["Fault"]["faultcode"];
      $obj->faultstring = $data["Body"]["Fault"]["faultstring"];
    } else {
      $obj->IdSolicitud = $data["Body"]["SolicitaDescargaResponse"]["SolicitaDescargaResult"]["@attributes"]["IdSolicitud"];
      $obj->CodEstatus = $data["Body"]["SolicitaDescargaResponse"]["SolicitaDescargaResult"]["@attributes"]["CodEstatus"];
      $obj->Mensaje = $data["Body"]["SolicitaDescargaResponse"]["SolicitaDescargaResult"]["@attributes"]["Mensaje"];
    }
    return $obj;
  }
}
