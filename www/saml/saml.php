<?php

include_once  __DIR__ . '/../../vendor/fr3d/xmlseclibs/xmlseclibs.php';

function utils_xml_create($xml, $preserveWhiteSpace = FALSE) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = $preserveWhiteSpace;
        $dom->loadXML($xml);
        $dom->formatOutput = TRUE;
        return $dom;
}

function utils_xml_sign($dom, $keyfile, $certfile) {
        // remove whitespace without breaking signature
        $dom = utils_xml_create($dom->saveXML(), TRUE);
        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $root = $dom->getElementsByTagName('Assertion')->item(0);
        assert('$root instanceof DOMElement');
        $insert_into = $dom->getElementsByTagName('Assertion')->item(0);
        $insert_before = $insert_into->getElementsByTagName('Subject')->item(0);
        $dsig->addReferenceList(array($root), XMLSecurityDSig::SHA1,
                        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
                        array('id_name' => 'ID'));
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        $objKey->loadKey($keyfile, TRUE);
        $dsig->sign($objKey);
        $cert = $certfile;
        $contents = file_get_contents($cert);
        $dsig->add509Cert($contents, TRUE);
        $dsig->insertSignature($insert_into, $insert_before);
        return $dom;
}

function utils_xml_sign_generic($dom, $key, $id_name, $root_name, $insert_into_name = NULL, $insert_before_name = NULL, $cert = NULL) {
    // remove whitespace without breaking signature
    $dom = utils_xml_create($dom->saveXML(), TRUE);
    $dsig = new XMLSecurityDSig();
    $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $root = $dom->getElementsByTagName($root_name)->item(0);
    assert('$root instanceof DOMElement');
    $insert_into = isset($insert_into_name) ? $dom->getElementsByTagName($insert_into_name)->item(0) : NULL;
    if (isset($insert_into)) {
        assert('$insert_into instanceof DOMElement');
        $insert_before = isset($insert_before_name) ? $insert_into->getElementsByTagName($insert_before_name)->item(0) : $insert_into->firstChild;
    } else {
        $insert_into = $root;
        $insert_before = NULL;
    }
    $dsig->addReferenceList(array($root), XMLSecurityDSig::SHA1,
        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
        array('id_name' => $id_name));
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
    $objKey->loadKey($key, TRUE);
    $dsig->sign($objKey);
    if (isset($cert)) {
        if (is_array($cert)) $cert = $cert[0];
        $contents = file_get_contents($cert);
        if ($contents === FALSE) {
            throw new Exception('Could not read certificate file: ' . $cert);
        }
        $dsig->add509Cert($contents, TRUE);
    }
    $dsig->insertSignature($insert_into, $insert_before);
    return $dom;
}