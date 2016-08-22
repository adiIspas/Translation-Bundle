<?php

namespace Lexik\Bundle\TranslationBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Repository for TransUnit entity.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class FileRepository extends EntityRepository
{
    /**
     * Returns all files matching a given locale and a given domains.
     *
     * @param array $locales
     * @param array $domains
     * @return array
     */
    public function findForLocalesAndDomains(array $locales, array $domains)
    {
//        $builder = $this->createQueryBuilder('f');
//
//        if (count($locales) > 0) {
//            $builder->andWhere($builder->expr()->in('f.locale', $locales));
//        }
//
//        if (count($domains) > 0) {
//            $builder->andWhere($builder->expr()->in('f.domain', $domains));
//        }
//
//        $files = $builder->getQuery()->getResult();

        $method = 'POST';
        $uri = 'http://trans-server.local/app_dev.php/api/find_for_locales_and_domains';

        $body['locales'] = !empty($locales) ?: '';
        $body['domains'] = !empty($domains) ?: '';

        $responseFind = $this->getResponseFromUrl($method, $uri, null, $body);
        $results = json_decode($responseFind->getBody(true), true);

        $files = array();
        foreach ($results as $result) {
            $file = new File();

            $file->setId($result['id']);
            $file->setLocale($result['locale']);
            $file->setDomain($result['domain']);
            $file->setHash($result['hash']);
            $file->setExtention($result['extention']);
            $file->setPath($result['path']);

            $files[] = $file;
        }

        return $files;
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
