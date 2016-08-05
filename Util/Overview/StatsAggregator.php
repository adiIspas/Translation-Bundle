<?php

namespace Lexik\Bundle\TranslationBundle\Util\Overview;

use Lexik\Bundle\TranslationBundle\Manager\LocaleManagerInterface;
use Lexik\Bundle\TranslationBundle\Storage\StorageInterface;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Class StatsAggregator
 * @package Lexik\Bundle\TranslationBundle\Util\Overview
 */
class StatsAggregator
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var LocaleManagerInterface
     */
    private $localeManager;

    /**
     * @param StorageInterface       $storage
     * @param LocaleManagerInterface $localeManager
     */
    public function __construct(StorageInterface $storage, LocaleManagerInterface $localeManager)
    {
        $this->storage = $storage;
        $this->localeManager = $localeManager;
    }

    /**
     * @return array
     */
    public function getStats()
    {
        $method = 'GET';
        $uri_1 = 'http://localhost:8080/app_dev.php/api/count/domains';
        $uri_2 = 'http://localhost:8080/app_dev.php/api/count/';
        $uri_3 = 'http://localhost:8080/app_dev.php/api/locales';

        $responseDomains = $this->getResponseFromUrl($method, $uri_1);
        $countByDomains = json_decode($responseDomains->getBody(true), true);

        $stats = array();

        foreach ($countByDomains as $domain => $total) {
            $stats[$domain] = array();

            $responseLocalesByDomain = $this->getResponseFromUrl($method, $uri_2 . $domain);
            $byLocale = json_decode($responseLocalesByDomain->getBody(true), true);

            $responseLocales = $this->getResponseFromUrl($method, $uri_3);
            $locales = json_decode($responseLocales->getBody(true), true);

            foreach ($locales as $locale) {
                $localeCount = isset($byLocale[$locale]) ? $byLocale[$locale] : 0;

                $stats[$domain][$locale] = array(
                    'keys'       => $total,
                    'translated' => $localeCount,
                    'completed'  => ($total > 0) ? floor(($localeCount / $total) * 100) : 0,
                );
            }
        }
        return $stats;
    }

    private function getResponseFromUrl($method, $uri, $headers = null, $body = null, $options = array())
    {
        $client = new Client();

        try {
            /** @var Response $response */
            $response = $client->createRequest(
                $method,
                $uri,
                $headers,
                $body,
                $options
            )->send();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $request  = $e->getRequest();
            if ($response instanceof Response) {
                $message = json_decode($response->getBody(true), true);
                if (isset($message['errors'])) {
                    $ex = new AuthClientErrorResponseException(key($message['errors']));
                    $ex->setResponse($response);
                    $ex->setRequest($request);
                    throw $ex;
                }
            }
            throw $e;
        }

        return $response;
    }
}