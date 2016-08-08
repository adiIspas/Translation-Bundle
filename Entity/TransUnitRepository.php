<?php

namespace Lexik\Bundle\TranslationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\TranslationBundle\Model\File as ModelFile;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Repository for TransUnit entity.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class TransUnitRepository extends EntityRepository
{
    /**
     * Returns all domain available in database.
     *
     * @return array
     */
    public function getAllDomainsByLocale()
    {
        return $this->createQueryBuilder('tu')
            ->select('te.locale, tu.domain')
            ->leftJoin('tu.translations', 'te')
            ->addGroupBy('te.locale')
            ->addGroupBy('tu.domain')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns all domains for each locale.
     *
     * @return array
     */
    public function getAllByLocaleAndDomain($locale, $domain)
    {
        return $this->createQueryBuilder('tu')
            ->select('tu, te')
            ->leftJoin('tu.translations', 'te')
            ->where('tu.domain = :domain')
            ->andWhere('te.locale = :locale')
            ->setParameter('domain', $domain)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns all trans unit with translations for the given domain and locale.
     *
     * @return array
     */
    public function getAllDomains()
    {
        $this->loadCustomHydrator();

        $domains = $this->createQueryBuilder('tu')
            ->select('DISTINCT tu.domain')
            ->orderBy('tu.domain', 'ASC')
            ->getQuery()
            ->getResult('SingleColumnArrayHydrator');

        return $domains;
    }

    /**
     * Returns some trans units with their translations.
     *
     * @param array $locales
     * @param int   $rows
     * @param int   $page
     * @param array $filters
     * @return array
     */
    public function getTransUnitList(array $locales = null, $rows = 20, $page = 1, array $filters = null)
    {

//        var_dump($filters);
//        echo "<hr>";
//        var_dump($locales);
//        echo "<hr>";
//        var_dump($rows);
//        echo "<hr>";
//        var_dump($page);
//        echo "<hr>";


//         CE SE INTAMPLA AICI TREBUIE SA FIE PE SERVER SI SA RETURNEZE O LISTA DE TRANSUNITS
//        $output = fopen("logs.log", "a+");
//        $log_message = 'Functia getTransUnitList() - extrage din baza de date traducerile';
//        fwrite($output, $log_message . PHP_EOL);
//
//        $this->loadCustomHydrator();
//
//        $sortColumn = isset($filters['sidx']) ? $filters['sidx'] : 'id';
//        $order = isset($filters['sord']) ? $filters['sord'] : 'ASC';
//
//        $builder = $this->createQueryBuilder('tu')
//            ->select('tu.id');
//
//        $this->addTransUnitFilters($builder, $filters);
//        $this->addTranslationFilter($builder, $locales, $filters);
//
//        $ids = $builder->orderBy(sprintf('tu.%s', $sortColumn), $order)
//            ->setFirstResult($rows * ($page - 1))
//            ->setMaxResults($rows)
//            ->getQuery()
//            ->getResult('SingleColumnArrayHydrator');
//
//        $transUnits = array();
//
//        if (count($ids) > 0) {
//            $qb = $this->createQueryBuilder('tu');
//
//            $transUnits = $qb->select('tu, te')
//                ->leftJoin('tu.translations', 'te')
//                ->andWhere($qb->expr()->in('tu.id', $ids))
//                ->andWhere($qb->expr()->in('te.locale', $locales))
//                ->orderBy(sprintf('tu.%s', $sortColumn), $order)
//                ->getQuery()
//                ->getSQL();
//        }
//
//
  //      var_dump($transUnits);
//
//        return $transUnits;
//
        $method = 'POST';
        $uri = 'http://localhost:8080/app_dev.php/api/all_translations';

        $body = array();
        $body['filters'] = $filters;
        $body['locales'] = $locales;
        $body['rows'] = $rows;
        $body['page'] = $page;

        $responseTranslations = $this->getResponseFromUrl($method, $uri, null, $body);
        $translations = json_decode($responseTranslations->getBody(true), true);

        var_dump($translations);

        return $translations;

    }

    /**
     * Count the number of trans unit.
     *
     * @param array $locales
     * @param array $filters
     * @return int
     */
    public function count(array $locales = null,  array $filters = null)
    {
        $method = 'GET';
        $uri = 'http://localhost:8080/app_dev.php/api/count';
        
        $responseDomains = $this->getResponseFromUrl($method, $uri);
        $count = json_decode($responseDomains->getBody(true), true);

        return $count;
    }

    /**
     * @return array
     */
    public function countByDomains()
    {
        // AICI INTOARCE NUMARUL DE TRADUCERII PE DOMENIU
        //|\\
        return $this->createQueryBuilder('tu')
            ->select('COUNT(DISTINCT tu.id) AS number, tu.domain')
            ->groupBy('tu.domain')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns all translations for the given file.
     *
     * @param ModelFile $file
     * @param boolean   $onlyUpdated
     * @return array
     */
    public function getTranslationsForFile(ModelFile $file, $onlyUpdated)
    {
        $builder = $this->createQueryBuilder('tu')
            ->select('tu.key, te.content')
            ->leftJoin('tu.translations', 'te')
            ->where('te.file = :file')
            ->setParameter('file', $file->getId())
            ->orderBy('te.id', 'asc');

        if ($onlyUpdated) {
            $builder->andWhere($builder->expr()->gt('te.updatedAt', 'te.createdAt'));
        }

        $results = $builder->getQuery()->getArrayResult();

        $translations = array();
        foreach ($results as $result) {
            $translations[$result['key']] = $result['content'];
        }

        return $translations;
    }

    /**
     * Add conditions according to given filters.
     *
     * @param QueryBuilder $builder
     * @param array        $filters
     */
    protected function addTransUnitFilters(QueryBuilder $builder, array $filters = null)
    {
        if (isset($filters['_search']) && $filters['_search']) {
            if (!empty($filters['domain'])) {
                $builder->andWhere($builder->expr()->like('tu.domain', ':domain'))
                    ->setParameter('domain', sprintf('%%%s%%', $filters['domain']));
            }

            if (!empty($filters['key'])) {
                $builder->andWhere($builder->expr()->like('tu.key', ':key'))
                    ->setParameter('key', sprintf('%%%s%%', $filters['key']));
            }
        }
    }

    /**
     * Add conditions according to given filters.
     *
     * @param QueryBuilder $builder
     * @param array        $locales
     * @param array        $filters
     */
    protected function addTranslationFilter(QueryBuilder $builder, array $locales = null, array $filters = null)
    {
        if (null !== $locales) {
            $qb = $this->createQueryBuilder('tu');
            $qb->select('DISTINCT tu.id')
                ->leftJoin('tu.translations', 't')
                ->where($qb->expr()->in('t.locale', $locales));

            foreach ($locales as $locale) {
                if (!empty($filters[$locale])) {
                    $qb->andWhere($qb->expr()->like('t.content', ':content'))
                        ->setParameter('content', sprintf('%%%s%%', $filters[$locale]));

                    $qb->andWhere($qb->expr()->eq('t.locale', ':locale'))
                        ->setParameter('locale', sprintf('%s', $locale));
                }
            }

            $ids = $qb->getQuery()->getResult('SingleColumnArrayHydrator');

            if (count($ids) > 0) {
                $builder->andWhere($builder->expr()->in('tu.id', $ids));
            }
        }
    }

    /**
     * Load custom hydrator.
     */
    protected function loadCustomHydrator()
    {
        $config = $this->getEntityManager()->getConfiguration();
        $config->addCustomHydrationMode('SingleColumnArrayHydrator', 'Lexik\Bundle\TranslationBundle\Util\Doctrine\SingleColumnArrayHydrator');
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
