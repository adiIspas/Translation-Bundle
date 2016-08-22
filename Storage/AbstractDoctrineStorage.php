<?php

namespace Lexik\Bundle\TranslationBundle\Storage;

use Symfony\Bridge\Doctrine\ManagerRegistry;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Common doctrine storage logic.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
abstract class AbstractDoctrineStorage implements StorageInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var string
     */
    protected $managerName;

    /**
     * @var array
     */
    protected $classes;

    /**
     * @param ManagerRegistry $registry
     * @param array           $managerName
     * @param array           $classes
     */
    public function __construct(ManagerRegistry $registry, $managerName, array $classes)
    {
        $this->registry = $registry;
        $this->managerName = $managerName;
        $this->classes = $classes;
    }

    /**
     * Returns the File repository.
     *
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    protected function getManager()
    {
        return $this->registry->getManager($this->managerName);
    }

    /**
     * Returns the TransUnit repository.
     *
     * @return object
     */
    protected function getTransUnitRepository()
    {
        return $this->getManager()->getRepository($this->classes['trans_unit']);
    }

    /**
     * Returns the File repository.
     *
     * @return object
     */
    protected function getFileRepository()
    {
        return $this->getManager()->getRepository($this->classes['file']);
    }

    /**
     * {@inheritdoc}
     */
    public function persist($entity)
    {
        $this->getManager()->persist($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($entity)
    {
        $this->getManager()->remove($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function flush($entity = null)
    {
        $this->getManager()->flush($entity);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($entityName = null)
    {
        $this->getManager()->clear($entityName);
    }

    /**
     * {@inheritdoc}
     */
    public function getModelClass($name)
    {
        if (!isset($this->classes[$name])) {
            throw new \RuntimeException(sprintf('No class defined for name "%s".', $name));
        }

        return $this->classes[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilesByLocalesAndDomains(array $locales, array $domains)
    {
        return $this->getFileRepository()->findForLocalesAndDomains($locales, $domains);
    }

    /**
     * {@inheritdoc}
     */
    public function getFileByHash($hash)
    {
        return $this->getFileRepository()->findOneBy(array('hash' => $hash));
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitDomains()
    {
        $method = 'GET';
        $uri = 'http://trans-server.local/app_dev.php/api/domains';

        $responseDomains = $this->getResponseFromUrl($method, $uri);
        $domains = json_decode($responseDomains->getBody(true), true);

        return $domains;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitById($id)
    {

        $method = 'GET';
        $uri = 'http://trans-server.local/app_dev.php/api/find_by_id/' . $id;

        $responseDomains = $this->getResponseFromUrl($method, $uri);
        $transUnit = json_decode($responseDomains->getBody(true), true);

        return $transUnit[0];

        //return $this->getTransUnitRepository()->findOneById($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitByKeyAndDomain($key, $domain)
    {
        $key = mb_substr($key, 0, 255, 'UTF-8');

        $fields = array(
            'key'    => $key,
            'domain' => $domain,
        );

        return $this->getTransUnitRepository()->findOneBy($fields);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitDomainsByLocale()
    {
        return $this->getTransUnitRepository()->getAllDomainsByLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitsByLocaleAndDomain($locale, $domain)
    {
        return $this->getTransUnitRepository()->getAllByLocaleAndDomain($locale, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransUnitList(array $locales = null, $rows = 20, $page = 1, array $filters = null)
    {
        return $this->getTransUnitRepository()->getTransUnitList($locales, $rows, $page, $filters);
    }

    /**
     * {@inheritdoc}
     */
    public function countTransUnits(array $locales = null, array $filters = null)
    {
        return $this->getTransUnitRepository()->count($locales, $filters);
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslationsFromFile($file, $onlyUpdated)
    {
        return $this->getTransUnitRepository()->getTranslationsForFile($file, $onlyUpdated);
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
