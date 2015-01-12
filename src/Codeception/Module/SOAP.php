<?php

namespace Codeception\Module;

/**
 * Module for testing SOAP WSDL web services.
 * Send requests and check if response matches the pattern.
 *
 * ## Status
 *
 * * Maintainer: **samuel**
 * * Stability: **stable**
 * * Contact: samuel.moncarey@addemar.com
 *
 * ## Configuration
 *
 * * wsdl *required* - soap wsdl
 *
 * ## Public Properties
 *
 * * xmlrequest - last soap request (DOMDocument)
 * * xmlresponse - last soap response (DOMDocument)
 * * response - last soap response value|object
 *
 */

use Codeception\Module;
use Codeception\TestCase;
use Codeception\Util\Soap as SoapUtils;
use SoapClient;
use SoapHeader;
use SoapVar;
use DOMDocument;

/**
 * Class SOAP
 * @package Codeception\Module
 */
class SOAP extends Module
{

    /**
     * @var array
     */
    protected $config = array('schema_url' => 'http://schemas.xmlsoap.org/soap/envelope/');
    /**
     * @var array
     */
    protected $requiredFields = array('wsdl');
    /**
     * @var SoapClient
     */
    public $client = null;

    /**
     * @var SoapHeader[]
     */
    public $soapHeaders = array();

    /**
     * @var DOMDocument
     */
    public $xmlRequest = null;
    /**
     * @var DOMDocument
     */
    public $xmlResponse = null;
    /**
     * @var mixed
     */
    public $response;
    /**
     * @var int
     */
    private $responseStatusCode;

    /**
     * @param TestCase $test
     */
    public function _before(TestCase $test)
    {
        $this->client = new SoapClient($this->config['wsdl'], array('trace'=> true, 'exceptions'=> false));
    }

    /**
     * @return DOMDocument
     */
    public function grabXMLRequest() {
        return $this->xmlRequest;
    }

    /**
     * @return DOMDocument
     */
    public function grabXMLResponse() {
        return $this->xmlResponse;
    }

    /**
     * @return mixed
     */
    public function grabResponse() {
        return $this->response;
    }

    /**
     * @param $header
     * @param array $params
     */
    public function haveSoapHeader($header, $params = array())
    {
        $namespace = $this->config['schema_url'];
        $soapVar = new SoapVar($params, SOAP_ENC_OBJECT);
        $this->soapHeaders[] = new SoapHeader($namespace, $header, $soapVar);
    }


    /**
     * @param string $action
     * @param array $params
     */
    public function sendSoapRequest($action, $params = array())
    {
        $this->response = $this->client->__soapCall($action, $params, array('trace'=>true, 'exceptions'=> false), $this->soapHeaders);
        $responseHeaders = explode("\n", $this->client->__getLastResponseHeaders());
        foreach ($responseHeaders as $header) {
            if(preg_match("/^HTTP\/1.1/", $header)) {
                $this->responseStatusCode = (intval(substr($header,9,3)));
            }
        }
        $this->xmlRequest = DOMDocument::loadXML($this->client->__getLastRequest());
        $this->xmlResponse = DOMDocument::loadXML($this->client->__getLastResponse());
    }

    public function seeResponseCodeIs($code) {
        if (!is_null($this->responseStatusCode)) {
            $this->assertEquals($this->responseStatusCode, $code);
        }
        else {
            throw new \Exception('There is no response available yet');
        }
    }

    /**
     * Checks XML response equals provided XML.
     * Comparison is done by canonicalizing both xml`s.
     *
     * Parameters can be passed either as DOMDocument, DOMNode, XML string, or array (if no attributes).
     *
     * Example:
     *
     * ``` php
     * <?php
     * $I->seeSoapResponseEquals("<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope><SOAP-ENV:Body><result>1</result></SOAP-ENV:Envelope>");
     *
     * $dom = new \DOMDocument();
     * $dom->load($file);
     * $I->seeSoapRequestIncludes($dom);
     *
     * ```
     *
     * @param $xml
     */
    public function seeSoapResponseEquals($xml)
    {
        $xml = SoapUtils::toXml($xml);
        \PHPUnit_Framework_Assert::assertEquals($this->xmlResponse->C14N(), $xml->C14N());
    }

    /**
     * Checks XML response includes provided XML.
     * Comparison is done by canonicalizing both xml`s.
     * Parameter can be passed either as XmlBuilder, DOMDocument, DOMNode, XML string, or array (if no attributes).
     *
     * Example:
     *
     * ``` php
     * <?php
     * $I->seeSoapResponseIncludes("<result>1</result>");
     * $I->seeSoapRequestIncludes(\Codeception\Utils\Soap::response()->result->val(1));
     *
     * $dom = new \DDOMDocument();
     * $dom->load('template.xml');
     * $I->seeSoapRequestIncludes($dom);
     * ?>
     * ```
     *
     * @param $xml
     */
    public function seeSoapResponseIncludes($xml)
    {
        $xml = $this->canonicalize($xml);
        \PHPUnit_Framework_Assert::assertContains($xml, $this->xmlResponse->C14N(), "found in XML Response");
    }


    /**
     * Checks XML response equals provided XML.
     * Comparison is done by canonicalizing both xml`s.
     *
     * Parameter can be passed either as XmlBuilder, DOMDocument, DOMNode, XML string, or array (if no attributes).
     *
     * @param $xml
     */
    public function dontSeeSoapResponseEquals($xml)
    {
        $xml = SoapUtils::toXml($xml);
        \PHPUnit_Framework_Assert::assertXmlStringNotEqualsXmlString($this->xmlResponse->C14N(), $xml->C14N());
    }


    /**
     * Checks XML response does not include provided XML.
     * Comparison is done by canonicalizing both xml`s.
     * Parameter can be passed either as XmlBuilder, DOMDocument, DOMNode, XML string, or array (if no attributes).
     *
     * @param $xml
     */
    public function dontSeeSoapResponseIncludes($xml)
    {
        $xml = $this->canonicalize($xml);
        \PHPUnit_Framework_Assert::assertNotContains($xml, $this->xmlResponse->C14N(), "found in XML Response");
    }

    /**
     * Checks XML response contains provided structure.
     * Response elements will be compared with XML provided.
     * Only nodeNames are checked to see elements match.
     *
     * Example:
     *
     * ``` php
     * <?php
     *
     * $I->seeResponseContains("<user><query>CreateUser<name>Davert</davert></user>");
     * $I->seeSoapResponseContainsStructure("<query><name></name></query>");
     * ?>
     * ```
     *
     * Use this method to check XML of valid structure is returned.
     * This method does not use schema for validation.
     * This method does not require path from root to match the structure.
     *
     * @param $xml
     */
    public function seeSoapResponseContainsStructure($xml) {
        $xml = SoapUtils::toXml($xml);
        $this->debugSection("Structure", $xml->saveXML());
        $root = $xml->firstChild;

        $this->debugSection("Structure Root", $root->nodeName);

        $els = $this->xmlResponse->getElementsByTagName($root->nodeName);

        if (empty($els)) return \PHPUnit_Framework_Assert::fail("Element {$root->nodeName} not found in response");

        $matches = false;
        foreach ($els as $node) {
            $matches |= $this->structureMatches($root, $node);
        }
        \PHPUnit_Framework_Assert::assertTrue((bool)$matches, "this structure is in response");

    }

    /**
     * Checks XML response with XPath locator
     *
     * ``` php
     * <?php
     * $I->seeSoapResponseContainsXPath('//root/user[@id=1]');
     * ?>
     * ```
     *
     * @param $xpath
     */
    public function seeSoapResponseContainsXPath($xpath)
    {
        $path = new \DOMXPath($this->xmlResponse);
        $res = $path->query($xpath);
        if ($res === false) $this->fail("XPath selector is malformed");
        $this->assertGreaterThan(0, $res->length);
    }

    /**
     * Checks XML response doesn't contain XPath locator
     *
     * ``` php
     * <?php
     * $I->dontSeeSoapResponseContainsXPath('//root/user[@id=1]');
     * ?>
     * ```
     *
     * @param $xpath
     */
    public function dontSeeSoapResponseContainsXPath($xpath)
    {
        $path = new \DOMXPath($this->xmlResponse);
        $res = $path->query($xpath);
        if ($res === false) $this->fail("XPath selector is malformed");
        $this->assertEquals(0, $res->length);
    }

    /**
     * Finds and returns text contents of element.
     * Element is matched by either CSS or XPath
     *
     * @version 1.1
     * @param $cssOrXPath
     * @return string
     */
    public function grabTextContentFrom($cssOrXPath) {
        $el = $this->matchElement($cssOrXPath);
        return $el->textContent;
    }

    /**
     * Finds and returns attribute of element.
     * Element is matched by either CSS or XPath
     *
     * @version 1.1
     * @param $cssOrXPath
     * @param $attribute
     * @return string
     */
    public function grabAttributeFrom($cssOrXPath, $attribute) {
        $el = $this->matchElement($cssOrXPath);
        if (!$el->hasAttribute($attribute)) $this->fail("Attribute not found in element matched by '$cssOrXPath'");
        return $el->getAttribute($attribute);
    }

    /**
     * @param $cssOrXPath
     * @return \DOMElement
     */
    protected function matchElement($cssOrXPath)
    {
        $xpath = new \DOMXpath($this->xmlResponse);
        try {
            $selector = \Symfony\Component\CssSelector\CssSelector::toXPath($cssOrXPath);
            $els = $xpath->query($selector);
            if ($els) return $els->item(0);
        } catch (\Symfony\Component\CssSelector\Exception\ParseException $e) {}
        $els = $xpath->query($cssOrXPath);
        if ($els) {
            return $els->item(0);
        }
        $this->fail("No node matched CSS or XPath '$cssOrXPath'");
    }

    /**
     * @param $xml
     * @return string
     */
    protected function canonicalize($xml)
    {
        $xml = SoapUtils::toXml($xml)->C14N();
        return $xml;
    }


    /**
     * @param $schema
     * @param $xml
     * @return bool
     */
    protected function structureMatches($schema, $xml)
    {
        foreach ($schema->childNodes as $node1) {
            $matched = false;
            foreach ($xml->childNodes as $node2) {
                if ($node1->nodeName == $node2->nodeName) {
                    $matched = $this->structureMatches($node1, $node2);
                    if ($matched) break;
                }
            }
            if (!$matched) return false;
        }
        return true;
    }

}
