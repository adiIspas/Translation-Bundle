<?php

namespace Lexik\Bundle\TranslationBundle\Storage;

use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Doctrine ORM storage class.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class DoctrineORMStorage extends AbstractDoctrineStorage
{
    /**
     * Returns true if translation tables exist.
     *
     * @return boolean
     */
    public function translationsTablesExist()
    {
        $em = $this->getManager();

        $tables = array(
            $em->getClassMetadata($this->getModelClass('trans_unit'))->table['name'],
            $em->getClassMetadata($this->getModelClass('translation'))->table['name'],
        );

        $schemaManager = $em->getConnection()->getSchemaManager();

        return $schemaManager->tablesExist($tables);
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestUpdatedAt()
    {
        $method = 'GET';
        $uri = 'http://trans-server.local/api/latest_updated';

        $responseDomains = $this->getResponseFromUrl($method, $uri);
        $latestUpdated = json_decode($responseDomains->getBody(true), true);

        return $latestUpdated;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountTransUnitByDomains()
    {
        $results = $this->getTransUnitRepository()->countByDomains();

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['domain']] = (int) $row['number'];
        }

        return $counts;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountTranslationByLocales($domain)
    {
        $results = $this->getTranslationRepository()->countByLocales($domain);

        $counts = array();
        foreach ($results as $row) {
            $counts[$row['locale']] = (int) $row['number'];
        }

        return $counts;
    }

    /**
     * Returns the TransUnit repository.
     *
     * @return object
     */
    protected function getTranslationRepository()
    {
        return $this->getManager()->getRepository($this->classes['translation']);
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
