<?php
/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

namespace NGenius\Http;

class TransferFactory
{
    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var array
     */
    private $body = array();

    /**
     * @var api curl uri
     */
    private $uri = '';

    /**
     * @var method
     */
    private $method;

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        if (is_array($request['request'])) {
            return $this->setBody($request['request']['data'])
                ->setMethod($request['request']['method'])
                ->setHeaders(array(
                    '0' => 'Authorization: Bearer ' . $request['token'],
                    '1' => 'Content-Type: application/vnd.ni-payment.v2+json',
                    '2' => 'Accept: application/vnd.ni-payment.v2+json'
                ))
                ->setUri($request['request']['uri']);
        }
    }

    /**
     * Set header for transfer object
     *
     * @param array $headers
     * @return Transferfactory
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set body for transfer object
     *
     * @param array $body
     * @return Transferfactory
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set method for transfer object
     *
     * @param array $method
     * @return Transferfactory
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Set uri for transfer object
     *
     * @param array $uri
     * @return Transferfactory
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * Retrieve method from transfer object
     *
     * @return string
     */
    public function getMethod()
    {
        return (string) $this->method;
    }

    /**
     * Retrieve header from transfer object
     *
     * @return Transferfactory
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Retrieve body from transfer object
     *
     * @return Transferfactory
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Retrieve uri from transfer object
     *
     * @return string
     */
    public function getUri()
    {
        return (string) $this->uri;
    }
}
