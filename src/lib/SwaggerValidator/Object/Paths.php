<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace SwaggerValidator\Object;

/**
 * Description of Paths
 *
 * @author Nabbar
 */
class Paths extends \SwaggerValidator\Common\CollectionSwagger
{

    public function __construct()
    {

    }

    public function jsonUnSerialize(\SwaggerValidator\Common\Context $context, $jsonData)
    {
        if (!is_object($jsonData)) {
            $this->buildException('Mismatching type of JSON Data received', $context);
        }

        if (!($jsonData instanceof \stdClass)) {
            $this->buildException('Mismatching type of JSON Data received', $context);
        }

        foreach (get_object_vars($jsonData) as $key => $value) {

            if (substr($key, 0, strlen(\SwaggerValidator\Common\FactorySwagger::KEY_CUSTOM_PATTERN)) == \SwaggerValidator\Common\FactorySwagger::KEY_CUSTOM_PATTERN) {
                continue;
            }

            $value      = $this->extractNonRecursiveReference($context, $value);
            $this->$key = \SwaggerValidator\Common\FactorySwagger::getInstance()->jsonUnSerialize($context->setDataPath($key), $this->getCleanClass(__CLASS__), $key, $value);
        }

        \SwaggerValidator\Common\Context::logDecode($context->getDataPath(), get_class($this), __METHOD__, __LINE__);
    }

    public function validate(\SwaggerValidator\Common\Context $context)
    {
        foreach ($this->keys() as $key) {
            if (is_object($this->$key) && ($this->$key instanceof \SwaggerValidator\Object\PathItem)) {
                continue;
            }

            if (is_object($this->$key) && method_exists($this->$key, 'validate')) {
                $this->$key->validate($context->setDataPath($key));
            }
        }

        $requestPath   = explode('/', $context->getRequestPath());
        $listFindRoute = array();

        foreach ($this->keys() as $key) {
            if (!is_object($this->$key) || !($this->$key instanceof \SwaggerValidator\Object\PathItem)) {
                continue;
            }

            if (substr($key, 0, 1) != '/') {
                continue;
            }

            $route = explode('/', $key);

            if (count($requestPath) != count($route)) {
                continue;
            }

            $findRoute = array(
                'base'   => $key,
                'params' => 0,
            );

            for ($i = 0; $i < count($route); $i++) {
                if (substr($route[$i], 0, 1) == '{' && substr($route[$i], -1, 1) == '}') {
                    $findRoute['params'] ++;
                    continue;
                }
                if ($route[$i] != $requestPath[$i]) {
                    $findRoute = null;
                    break;
                }
            }

            if ($findRoute !== null) {
                $listFindRoute[$findRoute['base']] = $findRoute['params'];
            }
        }

        $findRoute = null;
        $min       = null;

        foreach ($listFindRoute as $key => $value) {
            if ($findRoute === null || $value < $min) {
                $min       = $value;
                $findRoute = $key;
            }
        }

        if ($findRoute === null) {
            return $context->setValidationError(\SwaggerValidator\Common\Context::VALIDATION_TYPE_ROUTE_ERROR, 'Route not found', __METHOD__, __LINE__);
        }

        $context->setRoutePath($findRoute);
        \SwaggerValidator\Common\Context::logValidate($context->getDataPath(), get_class($this), __METHOD__, __LINE__);
        return $this->$findRoute->validate($context->setDataPath($findRoute));
    }

    public function getModel(\SwaggerValidator\Common\Context $context, $paramsResponses = array())
    {
        $parameters = \SwaggerValidator\Common\FactorySwagger::KEY_PARAMETERS;
        $responses  = \SwaggerValidator\Common\FactorySwagger::KEY_RESPONSES;
        $consumes   = \SwaggerValidator\Common\FactorySwagger::KEY_CONSUMES;
        $produces   = \SwaggerValidator\Common\FactorySwagger::KEY_PRODUCES;
        $result     = array();

        if (!array_key_exists($parameters, $paramsResponses)) {
            $paramsResponses[$parameters] = array();
        }

        if (isset($this->$parameters) && is_object($this->$parameters) && ($this->$parameters instanceof \SwaggerValidator\Object\Parameters)) {
            $this->$parameters->getModel($context->setDataPath($parameters), $paramsResponses[$parameters]);
        }

        if (!array_key_exists($responses, $paramsResponses)) {
            $paramsResponses[$responses] = array();
        }

        if (isset($this->$responses) && is_object($this->$responses) && ($this->$responses instanceof \SwaggerValidator\Object\Responses)) {
            $this->$responses->getModel($context->setDataPath($responses), $paramsResponses[$responses]);
        }

        if (isset($this->$consumes) && is_array($this->$consumes)) {
            $paramsResponses[$consumes] = $this->$consumes;
        }

        if (isset($this->$produces) && is_array($this->$produces)) {
            $paramsResponses[$produces] = $this->$produces;
        }

        foreach ($this->keys() as $key) {
            if (!is_object($this->$key) || !($this->$key instanceof \SwaggerValidator\Object\PathItem)) {
                continue;
            }

            if (substr($key, 0, 1) != '/') {
                continue;
            }

            $result[$key] = $this->$key->getModel($context->setDataPath($key), $paramsResponses);
        }

        \SwaggerValidator\Common\Context::logModel($context->getDataPath(), __METHOD__, __LINE__);
        return $result;
    }

}
