<?php
/**
 * Copyright 2017 SURFnet B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace SpBundle\Controller;

use DOMDocument;
use SAML2_Certificate_PrivateKeyLoader;
use SAML2_Configuration_PrivateKey;
use SAML2_DOMDocumentFactory;
use SAML2_Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\RegistrationService;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\Exception\AuthnFailedSamlResponseException;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\SAML2\AuthnRequest;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use XMLSecurityKey;

/**
 * Demo SP.
 *
 * @Route(service="sp.controller")
 */
final class SPController extends Controller
{
    private $identityProvider;
    private $serviceProvider;
    private $postBinding;

    public function __construct(
        ServiceProvider $serviceProvider,
        IdentityProvider $identityProvider,
        PostBinding $postBinding
    ) {
        $this->identityProvider = $identityProvider;
        $this->serviceProvider = $serviceProvider;
        $this->postBinding = $postBinding;
    }

    /**
     * @Route("/demo/sp", name="sp_demo")
     *
     * See @see RegistrationService for a more clean example.
     */
    public function demoSpAction(Request $request)
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->render('SpBundle:default:sp.html.twig');
        }
        $authnRequest = AuthnRequestFactory::createNewRequest($this->serviceProvider, $this->identityProvider);

        // Set nameId when we want to authenticate.
        if ($request->get('action') === 'authenticate') {
            $authnRequest->setSubject($request->get('NameID'));
        }

        // Build request query parameters.
        $requestAsXml = $authnRequest->getUnsignedXML();
        $encodedRequest = base64_encode(gzdeflate($requestAsXml));
        $queryParams = [AuthnRequest::PARAMETER_REQUEST => $encodedRequest];
        $relayState = $request->get(AuthnRequest::PARAMETER_RELAY_STATE);
        if (!empty($relayState)) {
            $queryParams[AuthnRequest::PARAMETER_RELAY_STATE] = $relayState;
        }

        // Create redirect response.
        $query = $this->signRequestQuery($queryParams);
        $url = sprintf('%s?%s', $this->identityProvider->getSsoUrl(), $query);
        $response = new RedirectResponse($url);

        // Set Stepup request id header.
        $stepupRequestId = $request->get('X-Stepup-Request-Id');
        if (!empty($stepupRequestId)) {
            $response->headers->set('X-Stepup-Request-Id', $stepupRequestId);
        }

        return $response;
    }

    /**
     * @Route("/demo/sp/acs", name="sp_demo_acs")
     *
     * See @see RegistrationService for a more clean example.
     */
    public function assertionConsumerServiceAction(Request $request)
    {
        $xmlResponse = $request->request->get('SAMLResponse');
        $xml = base64_decode($xmlResponse);
        try {
            $response = $this->postBinding->processResponse($request, $this->identityProvider, $this->serviceProvider);

            return $this->render('SpBundle:default:acs.html.twig', [
                'requestId' => $response->getId(),
                'nameId' => $response->getNameId(),
                'issuer' => $response->getIssuer(),
                'relayState' => $request->get(AuthnRequest::PARAMETER_RELAY_STATE, ''),
                'authenticatingAuthority' => $response->getAuthenticatingAuthority(),
                'xml' => $this->toFormattedXml($xml),
            ]);
        } catch (AuthnFailedSamlResponseException $e) {
            $samlResponse = $this->toUnsignedErrorResponse($xml);

            return $this->render('SpBundle:default:acs-error-response.html.twig', [
                'error' => $e->getMessage(),
                'status' => $samlResponse->getStatus(),
                'requestId' => $samlResponse->getId(),
                'issuer' => $samlResponse->getIssuer(),
                'relayState' => $request->get(AuthnRequest::PARAMETER_RELAY_STATE, ''),
                'xml' => $this->toFormattedXml($xml),
            ]);
        }
    }

    /**
     * Formats xml.
     *
     * @param string $xml
     *
     * @return string
     */
    private function toFormattedXml($xml)
    {
        $domxml = new DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml);

        return $domxml->saveXML();
    }

    /**
     * Sign AuthnRequest query parameters.
     *
     * @param array $queryParams
     * @return string
     */
    private function signRequestQuery(array $queryParams)
    {
        /** @var \XMLSecurityKey $securityKey */
        $securityKey = $this->loadServiceProviderPrivateKey();
        $queryParams[AuthnRequest::PARAMETER_SIGNATURE_ALGORITHM] = $securityKey->type;
        $toSign = http_build_query($queryParams);
        $signature = $securityKey->signData($toSign);

        return $toSign.'&Signature='.urlencode(base64_encode($signature));
    }

    /**
     * Loads the private key from the service provider.
     *
     * @return XMLSecurityKey
     */
    private function loadServiceProviderPrivateKey()
    {
        $keyLoader = new SAML2_Certificate_PrivateKeyLoader();
        $privateKey = $keyLoader->loadPrivateKey(
            $this->serviceProvider->getPrivateKey(SAML2_Configuration_PrivateKey::NAME_DEFAULT)
        );
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey->getKeyAsString());

        return $key;
    }

    /**
     * @param string $xml
     *
     * @return SAML2_Response
     */
    private function toUnsignedErrorResponse($xml)
    {
        $previous = libxml_disable_entity_loader(true);
        $asXml = SAML2_DOMDocumentFactory::fromString($xml);
        libxml_disable_entity_loader($previous);

        return SAML2_Response::fromXML($asXml->documentElement);
    }
}
